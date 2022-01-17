<?php

namespace DataAccounting\Content;

use DataAccounting\TransclusionManager;
use Html;
use JsonContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use Message;
use ParserOptions;
use ParserOutput;
use stdClass;
use Title;

class TransclusionHashes extends JsonContent implements DataAccountingContent {
	public const CONTENT_MODEL_TRANSCLUSION_HASHES = 'transclusion-hashes';
	public const SLOT_ROLE_TRANSCLUSION_HASHES = 'transclusion-hashes';

	/** @var bool */
	private $payAttention = false;

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, static::CONTENT_MODEL_TRANSCLUSION_HASHES );
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return $this->getText() === '' || parent::isValid();
	}

	/**
	 * @param array $hashmap
	 */
	public function setHashmap( array $hashmap ) {
		if ( !empty( $hashmap ) ) {
			$this->mText = json_encode( $hashmap );
		} else {
			$this->mText = '';
		}
	}

	/**
	 * @param Title $resourceTitle
	 * @param array $newData
	 * @return bool
	 */
	public function updateResource( Title $resourceTitle, $newData = [] ) {
		if ( !$this->isValid() ) {
			return false;
		}
		// Cannot change these...
		unset( $newData['dbkey'] );
		unset( $newData['ns'] );

		$data = [];
		$parseStatus = $this->getData();
		if ( $parseStatus->isOK() ) {
			$data = $parseStatus->getValue();
		}
		foreach ( $data as $resource ) {
			if (
				$resource->dbkey === $resourceTitle->getDBkey() &&
				$resource->ns === $resourceTitle->getNamespace()
			) {
				foreach ( $newData as $key => $value ) {
					$resource->$key = $value;
				}
				$this->mText = json_encode( $data );
				return true;
			}
		}

		return false;
	}

	/**
	 * @param LinkTarget|PageReference|Title $title
	 * @return stdClass|false if not listed
	 */
	public function getTransclusionDetails( $title ) {
		foreach ( $this->getResourceHashes() as $hashEntity ) {
			if (
				$title->getNamespace() === $hashEntity->ns && $title->getDBkey() === $hashEntity->dbkey ) {
				return $hashEntity;
			}
		}

		return false;
	}

	/**
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput $output
	 */
	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		if ( $title->getLatestRevID() !== $revId ) {
			$output->setText(
				Message::newFromKey( "da-transclusion-hash-ui-visit-latest" )->parseAsBlock()
			);
			return;
		}
		if ( !$this->isValid() ) {
			$output->setText( '' );
			return;
		}
		/** @var TransclusionManager $transclusionManager */
		$transclusionManager = MediaWikiServices::getInstance()->getService(
			'DataAccountingTransclusionManager'
		);
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revision = $revisionStore->getRevisionById( $revId );
		if ( $revision === null ) {
			return;
		}
		$states = $transclusionManager->getTransclusionState( $revision );
		$table = $this->drawTable( $states );
		$output->addModules( 'ext.dataAccounting.updateTransclusionHashes' );

		$outputText = $table;
		// TODO: Stupid dependency
		if ( \RequestContext::getMain()->getRequest()->getBool( 'debug' ) ) {
			$outputText .= $this->rootValueTable( $this->getData()->getValue() );
			$output->addModuleStyles( 'mediawiki.content.json' );
		}
		$output->setText( $outputText );
	}

	private function drawTable( array $states ): string {
		$table = Html::openElement( 'div', [
			'class' => 'container',
			'style' => 'width: 100%',
			'id' => 'transclusionResourceTable'
		] );
		foreach ( $states as $state ) {
			$table .= $this->drawRow( $state );
		}
		$table .= Html::closeElement( 'div' );

		return $table;
	}

	private function drawRow( array $state ): string {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$title = $state['titleObject'];
		$changeState = $state['state'];

		$item = Html::openElement( 'div', [ 'class' => 'card' ] );
		$item .= Html::openElement( 'div', [ 'class'  => 'card-body' ] );
		$item .= $linkRenderer->makeLink( $title );
		$badgeClasses = [ 'badge' ];
		if (
			$changeState === TransclusionManager::STATE_NEW_VERSION ||
			$changeState === TransclusionManager::STATE_NO_RECORD
		) {
			$this->payAttention = true;
			$badgeClasses[] = 'badge-warning';
		}
		if ( $changeState === TransclusionManager::STATE_UNCHANGED ) {
			$badgeClasses[] = 'badge-success';
		}
		if ( $changeState === TransclusionManager::STATE_INVALID ) {
			$this->payAttention = true;
			$badgeClasses[] = 'badge-danger';
		}
		$item .= Html::openElement( 'span', [
			'class' => implode( ' ', $badgeClasses ),
			'style' => 'margin-left: 10px',
		] );
		$item .= Message::newFromKey( "da-transclusion-hash-ui-state-{$changeState}" )->text();
		$item .= Html::closeElement( 'span' );
		if ( $changeState === TransclusionManager::STATE_NEW_VERSION ) {
			$item .= Html::element( 'a', [
				'href' => '#',
				'class' => 'da-included-resource-update',
				'data-resource-key' => $title->getPrefixedDbKey(),
				'style' => 'margin-left: 10px;'
			], Message::newFromKey( 'da-transclusion-hash-ui-update-version' )->text() );
		}
		$item .= Html::closeElement( 'div' );
		$item .= Html::closeElement( 'div' );

		return Html::rawElement( 'div', [ 'class' => 'da-translusion-hashes-item container' ], $item );
	}

	/**
	 * @return array
	 */
	public function getResourceHashes(): array {
		if ( !$this->isValid() ) {
			return [];
		}
		if ( !$this->getData()->isGood() ) {
			return [];
		}
		return (array)$this->getData()->getValue();
	}

	/**
	 * @inheritDoc
	 */
	public function getItemCount(): int {
		return count( json_decode( $this->getText(), 1 ) );
	}

	/**
	 * @inheritDoc
	 */
	public function requiresAction(): bool {
		return $this->payAttention;
	}

	/**
	 * @inheritDoc
	 */
	public function getSlotHeader(): string {
		return Message::newFromKey( 'da-transclusion-hashes-slot-header' );
	}

	/**
	 * @inheritDoc
	 */
	public function shouldShow(): bool {
		return !empty( $this->getData()->getValue() );
	}
}
