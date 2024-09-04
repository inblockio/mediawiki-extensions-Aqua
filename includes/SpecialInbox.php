<?php

namespace DataAccounting;

use DataAccounting\Inbox\HTMLDiffFormatter;
use DataAccounting\Inbox\InboxImporter;
use DataAccounting\Inbox\Pager;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use Html;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use Message;
use SpecialPage;
use TextContent;
use Title;
use TitleFactory;

class SpecialInbox extends SpecialPage {

	/** @var TitleFactory */
	private $titleFactory;
	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var VerificationEntity|null */
	private $remote;
	/** @var VerificationEntity|null */
	private $local;
	/** @var InboxImporter|null */
	private $inboxImporter = null;
	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var RevisionManipulator */
	private $revisionManipulator;

	/**
	 * @param TitleFactory $titleFactory
	 * @param VerificationEngine $verificationEngine
	 * @param RevisionLookup $revisionLookup
	 * @param RevisionManipulator $revisionManipulator
	 */
	public function __construct(
		TitleFactory $titleFactory, VerificationEngine $verificationEngine,
		RevisionLookup $revisionLookup, RevisionManipulator $revisionManipulator
	) {
		parent::__construct( 'Inbox', 'edit' );
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
		$this->revisionLookup = $revisionLookup;
		$this->revisionManipulator = $revisionManipulator;
	}

	public function execute( $par ) {
		parent::execute( $par );
		if ( !$par ) {
			$this->outputList();
			return;
		}
		$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );
		$this->getOutput()->setPageTitle( $this->msg( 'da-specialinbox-do-import-title' ) );
		$this->tryCompare( $par );
	}

	/**
	 * @return int
	 */
	public function getInboxCount(): int {
		return $this->getPager()->getCount();
	}

	private function outputList() {
		$pager = $this->getPager();
		$this->getOutput()->addParserOutput( $pager->getBodyOutput() );
	}

	/**
	 * @param string $pageName
	 * @return void
	 */
	private function tryCompare( $pageName ) {
		$title = $this->titleFactory->makeTitle( NS_INBOX, $pageName );
		if ( !$title->exists() ) {
			$this->getOutput()->addWikiMsg( 'da-specialinbox-not-found', $pageName );
			return;
		}
		$this->remote = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $title );
		$this->local = $this->getTargetEntity( $this->remote );

		if ( !$this->local ) {
			// Abort if title collision with existing page, if genesis hashes are different
			$title = $this->titleFactory->newFromDBkey( $pageName );
			$hasTitleCollision = $title->exists();

			$this->outputDirectMerge( $this->remote, $hasTitleCollision );
			return;
		}
		$this->outputCompare( $this->remote, $this->local );
	}

	/**
	 * @param VerificationEntity|null $entity
	 * @return VerificationEntity|null
	 */
	private function getTargetEntity( ?VerificationEntity $entity ): ?VerificationEntity {
		if ( !$entity ) {
			return null;
		}
		$genesis = $entity->getHash( VerificationEntity::GENESIS_HASH );
		return $this->verificationEngine->getLookup()->verificationEntityFromQuery( [
			VerificationEntity::VERIFICATION_HASH => $genesis,
			'page_id != ' . $entity->getTitle()->getArticleID()
		] );
	}

	/**
	 * @param VerificationEntity $draftEntity
	 * @param bool $disableSubmit
	 *
	 * @return void
	 */
	private function outputDirectMerge( VerificationEntity $draftEntity, bool $hasTitleCollision = false ) {
		$msgKey = 'da-specialinbox-direct-merge';
		if ( $hasTitleCollision ) {
			$msgKey = 'da-specialinbox-direct-merge-title-collision';
		}

		$this->getOutput()->addWikiMsg( $msgKey, $draftEntity->getTitle()->getText() );
		$this->outputForm( $draftEntity, 'new', $hasTitleCollision );
	}

	/**
	 * @param VerificationEntity $draft
	 * @param VerificationEntity $target
	 * @return void
	 * @throws \MediaWiki\Diff\ComplexityException
	 */
	private function outputCompare( VerificationEntity $draft, VerificationEntity $target ) {
		$tree = $this->getInboxImporter()->getTreeBuilder()->buildPreImportTree(
			$draft->getTitle(), $target->getTitle(), $this->getLanguage(), $this->getUser()
		);
		$this->getOutput()->addHTML( $this->makeSummaryHeader( $tree, $draft, $target ) );
		$this->getOutput()->addHTML(
			Html::element( 'div', [
				'id' => 'da-specialinbox-compare',
				'data-tree' => json_encode( $tree ),
				'data-draft' => $draft->getTitle()->getArticleID(),
				'data-target' => $target->getTitle()->getArticleID(),
			] )
		);
		if ( $tree['change-type'] === 'both' ) {
			$diff = $this->makeDiff( $tree );
			if ( $diff ) {
				$this->getOutput()->addHTML( Html::rawElement( 'div', [
					'id' => 'da-specialinbox-compare-diff',
					'data-diff' => json_encode( $diff['diffData'] ),
				], $diff['formatted'] ) );
			}
		}
		$this->outputForm( $draft, $tree['change-type'] );
		$this->getOutput()->addModules( 'ext.DataAccounting.inbox.compare' );
	}

	/**
	 * @return InboxImporter
	 */
	private function getInboxImporter(): InboxImporter {
		if ( $this->inboxImporter === null ) {
			$this->inboxImporter = new InboxImporter(
				$this->verificationEngine, $this->revisionLookup, $this->revisionManipulator
			);
		}
		return $this->inboxImporter;
	}

	/**
	 * @return Pager
	 */
	private function getPager(): Pager {
		return new Pager(
			$this->getContext(), $this->getLinkRenderer(), $this->titleFactory,
			$this->verificationEngine, $this->getSpecialPageFactory()
		);
	}

	/**
	 * @param array $treeData
	 * @param VerificationEntity $draft
	 * @param VerificationEntity $target
	 * @return string
	 */
	private function makeSummaryHeader( array $treeData, VerificationEntity $draft, VerificationEntity $target ) {
		$targetLink = $this->getLinkRenderer()->makeLink(
			$target->getTitle(),
			$target->getTitle()->getPrefixedText()
		);
		$draftLink = $this->getLinkRenderer()->makeLink(
			$draft->getTitle(),
			$draft->getTitle()->getPrefixedText()
		);

		$content =
			Html::rawElement( 'span', [ 'class' => 'da-compare-node-graph da-compare-node-graph-remote' ] ) .
			$this->msg(
				'da-specialinbox-compare-remote-link'
			)->text() .
			$draftLink .
			'<br />' .
			Html::rawElement( 'span', [ 'class' => 'da-compare-node-graph da-compare-node-graph-local' ] ) .
			$this->msg(
				'da-specialinbox-compare-local-link'
			)->text() .
			$targetLink;

		$headerMessage = $this->msg(
			'da-specialinbox-compare-' . $treeData['change-type'], $draft->getTitle()->getText()
		)->parseAsBlock();

		return $headerMessage .
			Html::rawElement( 'div', [ 'class' => 'da-specialinbox-compare-summary' ], $content );
	}

	/**
	 * @param VerificationEntity $remote
	 * @param string|null $changeType
	 *
	 * @return void
	 */
	private function outputForm( VerificationEntity $remote, ?string $changeType = 'new', bool $disableSubmit = false ) {
		$somethingToImport = $changeType !== 'local' && $changeType !== 'none';
		$form = HTMLForm::factory(
			'ooui',
			[
				'title' => [
					'type' => 'hidden',
					'default' => $this->getPageTitle( $remote->getTitle()->getText() ),
				],
				'action' => [
					'type' => 'hidden',
					'default' => $somethingToImport ?
						( $changeType === 'remote' ? 'merge-remote' : 'direct-merge' ) :
						'discard',
				],
				'merge-type' => [
					'type' => 'hidden',
					'default' => ''
				],
				'combined-text' => [
					'type' => 'hidden',
					'default' => ''
				]
			],
			$this->getContext()
		);
		$form->setId( 'da-specialinbox-merge-form' );
		$form->setMethod( 'POST' );
		if ( !$somethingToImport || $disableSubmit ) {
			$form->setSubmitTextMsg( $this->msg( 'da-specialinbox-merge-discard' ) );
			$form->setSubmitName( 'discard' );
			$form->setSubmitDestructive();
		} else {
			$form->setSubmitTextMsg( $this->msg( 'da-specialinbox-merge-submit' ) );
			$form->setSubmitName( 'import' );
			$form->addButton( [
				'name' => 'discard',
				'value' => 'Discard',
				'type' => 'submit',
				'label-message' => 'da-specialinbox-merge-discard',
				'flags' => [ 'destructive' ],
			] );
		}
		$form->setSubmitCallback( [ $this, 'onAction' ] );
		$form->show();
	}

	/**
	 * @param array $formData
	 *
	 * @return bool|Message
	 */
	public function onAction( $formData ) {
		$postValues = $this->getRequest()->getPostValues();
		$shouldDiscard = $formData['action'] === 'discard' || $postValues['discard'] === 'Discard';
		if ( $shouldDiscard ) {
			return $this->doDiscard();
		}

		try {
			$remoteTitle = $this->remote->getTitle();
			if ( isset( $formData['action'] ) && $formData['action'] === 'merge-remote' ) {
				$this->inboxImporter->mergePagesForceRemote(
					$this->local->getTitle(), $remoteTitle, $this->getUser()
				);
			} else {
				$mergeType = $formData['merge-type'] ?? null;
				if ( !$mergeType ) {
					// Merge remote directly to an non-existing target
					return $this->importRemote();
				}
				switch ( $mergeType ) {
					case 'remote':
						$this->inboxImporter->mergePagesForceRemote(
							$this->local->getTitle(), $remoteTitle, $this->getUser()
						);
						break;
					case 'local':
						return $this->doDiscard();
					case 'combined':
						$text = $formData['combined-text'] ?? '';
						$this->inboxImporter->mergePages( $this->local->getTitle(), $remoteTitle, $this->getUser(), $text );
				}
			}
		} catch ( \Throwable $ex ) {
			return $this->msg( $ex->getMessage() );
		}

		$this->getOutput()->redirect( $this->local->getTitle()->getFullURL() );

		return false;
	}

	/**
	 * @return true
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * @return bool|Message
	 */
	private function importRemote() {
		if ( $this->local ) {
			return $this->msg( 'da-specialinbox-merge-error-target-exists' );
		}
		// Title same as the subject, but not in NS_INBOX
		$targetTitle = $this->titleFactory->newFromText( $this->remote->getTitle()->getDBkey() );
		if ( !$targetTitle ) {
			return $this->msg( 'da-specialinbox-merge-error-invalid target' );
		}
		$inboxImporter = $this->getInboxImporter();
		$status = $inboxImporter->importDirect( $this->remote, $targetTitle, $this->getUser() );
		if ( !$status->isOK() ) {
			return $this->msg( $status->getMessage() );
		}
		$this->getOutput()->redirect( $targetTitle->getFullURL() );
		return true;
	}

	/**
	 * @return bool|Message
	 */
	private function doDiscard() {
		$subjectTitle = $this->remote->getTitle();
		$inboxImporter = $this->getInboxImporter();
		$status = $inboxImporter->discard( $subjectTitle, $this->getUser() );
		if ( $status->isOK() ) {
			$this->getOutput()->redirect( $this->getPageTitle()->getFullURL() );
			return true;
		}
		return $this->msg( $status->getMessage() );
	}

	/**
	 * @param array $tree
	 * @return array|null
	 * @throws \MediaWiki\Diff\ComplexityException
	 */
	private function makeDiff( array $tree ): ?array {
		$diff = $this->getDiff( $tree['local'], $tree['remote'] );
		$diffFormatter = new HTMLDiffFormatter();

		if ( empty( $diff->getEdits() ) ) {
			return null;
		}
		return [
			'formatted' => $diffFormatter->format( $diff ),
			'diffData' => $diffFormatter->getArrayData(),
			'count' => $diffFormatter->getChangeCount()
		];
	}

	/**
	 * @param Title $local
	 * @param Title $remote
	 * @return \Diff
	 * @throws \MediaWiki\Diff\ComplexityException
	 */
	protected function getDiff( \Title $local, \Title $remote ) {
		$localContent = $this->getPageContentText( $local );
		$remoteContent = $this->getPageContentText( $remote );

		return new \Diff(
			explode( "\n", $localContent ),
			explode( "\n", $remoteContent )
		);
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	protected function getPageContentText( Title $title ): string {
		$wikipage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$content = $wikipage->getContent();
		return ( $content instanceof TextContent ) ? $content->getText() : '';
	}
}
