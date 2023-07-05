<?php

namespace DataAccounting;

use DataAccounting\Inbox\InboxImporter;
use DataAccounting\Inbox\Pager;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use Html;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\DeletePage;
use MediaWiki\Revision\RevisionLookup;
use Message;
use MWException;
use SpecialPage;
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

	/**
	 * @param TitleFactory $titleFactory
	 * @param VerificationEngine $verificationEngine
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		TitleFactory $titleFactory, VerificationEngine $verificationEngine, RevisionLookup $revisionLookup
	) {
		parent::__construct( 'Inbox', 'read' );
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
		$this->revisionLookup = $revisionLookup;
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

	private function tryCompare( $pageName ) {
		$title = $this->titleFactory->makeTitle( NS_INBOX, $pageName );
		if ( !$title->exists() ) {
			$this->getOutput()->addWikiMsg( 'da-specialinbox-not-found', $pageName );
			return;
		}
		$this->remote = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $title );
		$this->local = $this->getTargetEntity( $this->remote );

		if ( !$this->local ) {
			$this->outputDirectMerge( $this->remote );
			return;
		}
		$this->outputCompare( $this->remote, $this->local );
	}

	private function getTargetEntity( ?VerificationEntity $entity ): ?VerificationEntity {
		if ( !$entity ) {
			return null;
		}
		$genesis = $entity->getHash( VerificationEntity::GENESIS_HASH );
		return $this->verificationEngine->getLookup()->verificationEntityFromQuery( [
			VerificationEntity::GENESIS_HASH => $genesis,
			'page_id != ' . $entity->getTitle()->getArticleID()
		] );
	}

	private function outputDirectMerge( VerificationEntity $draftEntity ) {
		$this->getOutput()->addWikiMsg(
			'da-specialinbox-direct-merge', $draftEntity->getTitle()->getText()
		);
		$this->outputForm( $draftEntity );
	}

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
		$this->outputForm( $draft, $tree['change-type'] );
		$this->getOutput()->addModules( 'ext.DataAccounting.inbox.compare' );
	}

	/**
	 * @return InboxImporter
	 */
	private function getInboxImporter(): InboxImporter {
		if ( $this->inboxImporter === null ) {
			$this->inboxImporter = new InboxImporter( $this->verificationEngine, $this->revisionLookup );
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
	private function outputForm( VerificationEntity $remote, ?string $changeType = 'new' ) {
		$canImport = $changeType !== 'local' && $changeType !== 'none';
		$form = HTMLForm::factory(
			'ooui',
			[
				'title' => [
					'type' => 'hidden',
					'default' => $this->getPageTitle( $remote->getTitle()->getText() ),
				],
				'action' => [
					'type' => 'hidden',
					'default' => $canImport ? 'direct-merge' : 'discard',
				]
			],
			$this->getContext()
		);
		$form->setId( 'da-specialinbox-merge-form' );
		$form->setMethod( 'POST' );
		if ( !$canImport ) {
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
		$postData = $this->getRequest()->getPostValues();
		$action = isset( $postData['discard'] ) ? 'discard' : $formData['action'];

		if ( !$this->remote ) {
			return $this->msg( 'da-specialinbox-merge-error-no-subject' );
		}
		if ( $action === 'discard' ) {
			return $this->doDiscard();
		}
		if ( $action === 'direct-merge' ) {
			return $this->doDirectMerge();
		}
		if ( $action === 'merge-remote' ) {
			return $this->doMergeRemote();
		}

		return false;
	}

	/**
	 * @return bool|Message
	 */
	private function doDirectMerge() {
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
	 * @return bool|Message
	 */
	private function doMergeRemote() {
		$targetTitle = $this->titleFactory->newFromText( $this->remote->getTitle()->getDBkey() );
		if ( !$targetTitle ) {
			return $this->msg( 'da-specialinbox-merge-error-invalid target' );
		}
		$inboxImporter = $this->getInboxImporter();
		$status = $inboxImporter->importRemote( $this->remote, $targetTitle, $this->getUser() );
		if ( !$status->isOK() ) {
			return $this->msg( $status->getMessage() );
		}
		$this->getOutput()->redirect( $targetTitle->getFullURL() );
		return true;
	}
}
