<?php

namespace DataAccounting\Override;

use DataAccounting\Content\DataAccountingContent;
use Html;
use InvalidArgumentException;
use MediaWiki\Content\Renderer\ContentRenderer;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionRenderer;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use ParserOptions;
use ParserOutput;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Very ugly total override of RevisionRenderer
 * Its here to customize how different slots are represented
 *
 * TODO: Check for updates in RevisionRenderer
 * This will probably be fixed in the original class relatively soon
 */
class MultiSlotRevisionRenderer extends RevisionRenderer {

	/** @var LoggerInterface */
	private $saveParseLogger;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var SlotRoleRegistry */
	private $roleRegistery;

	/** @var ContentRenderer */
	private $contentRenderer;

	/** @var string|bool */
	private $dbDomain;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param SlotRoleRegistry $roleRegistry
	 * @param ContentRenderer $contentRenderer
	 * @param bool|string $dbDomain DB domain of the relevant wiki or false for the current one
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		SlotRoleRegistry $roleRegistry,
		ContentRenderer $contentRenderer,
		$dbDomain = false
	) {
		parent::__construct( $loadBalancer, $roleRegistry, $contentRenderer, $dbDomain );
		$this->loadBalancer = $loadBalancer;
		$this->roleRegistery = $roleRegistry;
		$this->dbDomain = $dbDomain;
		$this->contentRenderer = $contentRenderer;
		$this->saveParseLogger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $saveParseLogger
	 */
	public function setLogger( LoggerInterface $saveParseLogger ) {
		$this->saveParseLogger = $saveParseLogger;
	}

	/**
	 * @param RevisionRecord $rev
	 * @param ParserOptions|null $options
	 * @param Authority|null $forPerformer User for privileged access. Default is unprivileged
	 *        (public) access, unless the 'audience' hint is set to something else RevisionRecord::RAW.
	 * @param array $hints Hints given as an associative array. Known keys:
	 *      - 'use-master' Use primary DB when rendering for the parser cache during save.
	 *        Default is to use a replica.
	 *      - 'audience' the audience to use for content access. Default is
	 *        RevisionRecord::FOR_PUBLIC if $forUser is not set, RevisionRecord::FOR_THIS_USER
	 *        if $forUser is set. Can be set to RevisionRecord::RAW to disable audience checks.
	 *      - 'known-revision-output' a combined ParserOutput for the revision, perhaps from
	 *        some cache. the caller is responsible for ensuring that the ParserOutput indeed
	 *        matched the $rev and $options. This mechanism is intended as a temporary stop-gap,
	 *        for the time until caches have been changed to store RenderedRevision states instead
	 *        of ParserOutput objects.
	 * @phan-param array{use-master?:bool,audience?:int,known-revision-output?:ParserOutput} $hints
	 *
	 * @return RenderedRevision|null The rendered revision, or null if the audience checks fails.
	 */
	public function getRenderedRevision(
		RevisionRecord $rev,
		ParserOptions $options = null,
		Authority $forPerformer = null,
		array $hints = []
	) {
		if ( $rev->getWikiId() !== $this->dbDomain ) {
			throw new InvalidArgumentException( 'Mismatching wiki ID ' . $rev->getWikiId() );
		}

		$audience = $hints['audience']
			?? ( $forPerformer ? RevisionRecord::FOR_THIS_USER : RevisionRecord::FOR_PUBLIC );

		if ( !$rev->audienceCan( RevisionRecord::DELETED_TEXT, $audience, $forPerformer ) ) {
			// Returning null here is awkward, but consistent with the signature of
			// RevisionRecord::getContent().
			return null;
		}

		if ( !$options ) {
			$options = ParserOptions::newCanonical(
				$forPerformer ? $forPerformer->getUser() : 'canonical'
			);
		}

		$usePrimary = $hints['use-master'] ?? false;

		$dbIndex = $usePrimary
			? DB_PRIMARY // use latest values
			: DB_REPLICA; // T154554

		$options->setSpeculativeRevIdCallback( function () use ( $dbIndex ) {
			return $this->getSpeculativeRevId( $dbIndex );
		} );
		$options->setSpeculativePageIdCallback( function () use ( $dbIndex ) {
			return $this->getSpeculativePageId( $dbIndex );
		} );

		if ( !$rev->getId() && $rev->getTimestamp() ) {
			// This is an unsaved revision with an already determined timestamp.
			// Make the "current" time used during parsing match that of the revision.
			// Any REVISION* parser variables will match up if the revision is saved.
			$options->setTimestamp( $rev->getTimestamp() );
		}

		$renderedRevision = new RenderedRevision(
			$rev,
			$options,
			$this->contentRenderer,
			function ( RenderedRevision $rrev, array $hints ) {
				return $this->combineSlotOutput( $rrev, $hints );
			},
			$audience,
			$forPerformer
		);

		$renderedRevision->setSaveParseLogger( $this->saveParseLogger );

		if ( isset( $hints['known-revision-output'] ) ) {
			$renderedRevision->setRevisionParserOutput( $hints['known-revision-output'] );
		}

		return $renderedRevision;
	}

	private function getSpeculativeRevId( $dbIndex ) {
		// Use a separate primary DB connection in order to see the latest data, by avoiding
		// stale data from REPEATABLE-READ snapshots.
		$flags = ILoadBalancer::CONN_TRX_AUTOCOMMIT;

		$db = $this->loadBalancer->getConnectionRef( $dbIndex, [], $this->dbDomain, $flags );

		return 1 + (int)$db->selectField(
				'revision',
				'MAX(rev_id)',
				[],
				__METHOD__
			);
	}

	private function getSpeculativePageId( $dbIndex ) {
		// Use a separate primary DB connection in order to see the latest data, by avoiding
		// stale data from REPEATABLE-READ snapshots.
		$flags = ILoadBalancer::CONN_TRX_AUTOCOMMIT;

		$db = $this->loadBalancer->getConnectionRef( $dbIndex, [], $this->dbDomain, $flags );

		return 1 + (int)$db->selectField(
				'page',
				'MAX(page_id)',
				[],
				__METHOD__
			);
	}

	/**
	 * This implements the layout for combining the output of multiple slots.
	 *
	 * @todo Use placement hints from SlotRoleHandlers instead of hard-coding the layout.
	 *
	 * @param RenderedRevision $rrev
	 * @param array $hints see RenderedRevision::getRevisionParserOutput()
	 *
	 * @return ParserOutput
	 */
	private function combineSlotOutput( RenderedRevision $rrev, array $hints = [] ) {
		$revision = $rrev->getRevision();
		$slots = $revision->getSlots()->getSlots();

		$withHtml = $hints['generate-html'] ?? true;

		// short circuit if there is only the main slot
		if ( array_keys( $slots ) === [ SlotRecord::MAIN ] ) {
			return $rrev->getSlotParserOutput( SlotRecord::MAIN, $hints );
		}

		// move main slot to front
		if ( isset( $slots[SlotRecord::MAIN] ) ) {
			$slots = [ SlotRecord::MAIN => $slots[SlotRecord::MAIN] ] + $slots;
		}

		$combinedOutput = new ParserOutput( null );
		$slotOutput = [];

		$options = $rrev->getOptions();
		$options->registerWatcher( [ $combinedOutput, 'recordOption' ] );

		foreach ( $slots as $role => $slot ) {
			$out = $rrev->getSlotParserOutput( $role, $hints );
			$slotOutput[$role] = $out;

			// XXX: should the SlotRoleHandler be able to intervene here?
			$combinedOutput->mergeInternalMetaDataFrom( $out );
			$combinedOutput->mergeTrackingMetaDataFrom( $out );
		}

		if ( $withHtml ) {
			$html = '';
			$daSlots = [];
			/** @var ParserOutput $out */
			foreach ( $slotOutput as $role => $out ) {
				if ( !$slots[$role]->getContent() instanceof DataAccountingContent ) {
					$html .= $out->getRawText();
					$combinedOutput->mergeHtmlMetaDataFrom( $out );
					continue;
				}

				$roleHandler = $this->roleRegistery->getRoleHandler( $role );

				// TODO: put more fancy layout logic here, see T200915.
				$layout = $roleHandler->getOutputLayoutHints();
				$display = $layout['display'] ?? 'section';

				if ( $display === 'none' ) {
					continue;
				}

				$daSlots[$role] = [
					'rendered' => $out->getRawText(),
					'content' => $slots[$role]->getContent()
				];
				$combinedOutput->mergeHtmlMetaDataFrom( $out );
			}

			if ( !empty( $daSlots ) ) {
				$html .= $this->wrapSpecialSlotOutput( $daSlots );
			}
			$combinedOutput->setText( $html );
		}

		$options->registerWatcher( null );
		return $combinedOutput;
	}

	/**
	 * @param array $slots
	 * @return string
	 */
	private function wrapSpecialSlotOutput( array $slots ) {
		$hasVisible = false;
		$accordion = Html::element( 'hr' );
		$accordion .= Html::openElement( 'div', [ 'id' => 'da-slots-cnt' ] );
		$accordion .= Html::element(
			'h5', [],
			\Message::newFromKey( 'da-revision-renderer-da-data' )->text()
		);
		foreach ( $slots as $role => $data ) {
			/** @var DataAccountingContent $content */
			$content = $data['content'];
			if ( !$content->shouldShow() ) {
				continue;
			}
			$hasVisible = true;
			$header = Html::element( 'button', [
				'class' => 'btn btn-link',
				'data-toggle' => 'collapse',
				'data-target' => "#da-$role",
				'aria-expanded' => false,
				'aria-controls' => "#da-$role",
			], $content->getSlotHeader() );

			if ( $content->getItemCount() > 0 ) {
				$header .= Html::element( 'span', [
					'class' => 'badge badge-primary'
				], $content->getItemCount() );
			}
			if ( $content->requiresAction() ) {
				$header .= Html::element( 'span', [
					'class' => 'badge badge-warning'
				], '!' );
			}
			$card = Html::rawElement(
				'div', [ 'class' => 'card' ],
				Html::rawElement(
					'div', [ 'class' => 'card-header', 'id' => "da-header-$role" ],
					Html::rawElement( 'h5', [ 'class' => 'mb-0' ], $header )
				)
			);

			$accordion .= $card;
			$accordion .= Html::rawElement(
				'div', [
					'class' => 'collapse', 'id' => "da-$role",
					'aria-labelledby' => "da-header-$role", 'data-parent' => '#da-slots-cnt'
				],
				Html::rawElement( 'div', [ 'class' => 'card-body' ], $data['rendered'] )
			);
		}

		$accordion .= Html::closeElement( 'div' );

		return $hasVisible ? $accordion : '';
	}

}
