<?php

namespace DataAccounting;

use BlueSpice\PageAssignments\Api\Store\Page;
use DataAccounting\Inbox\InboxImporter;
use DataAccounting\Inbox\Pager;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use HTMLForm;
use SpecialPage;
use TitleFactory;

class SpecialInbox extends SpecialPage {

	/** @var TitleFactory */
	private $titleFactory;
	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var VerificationEntity|null */
	private $subject;
	/** @var VerificationEntity|null */
	private $target;
	/** @var InboxImporter|null */
	private $inboxImporter = null;

	/**
	 * @param TitleFactory $titleFactory
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct( TitleFactory $titleFactory, VerificationEngine $verificationEngine ) {
		parent::__construct( 'Inbox', 'read' );
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
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
		$this->subject = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $title );
		$this->target = $this->getTargetEntity( $this->subject );

		if ( !$this->target ) {
			$this->outputDirectMerge( $this->subject );
			return;
		}
		$this->outputCompare( $this->subject, $this->target );
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
		$form = HTMLForm::factory(
			'ooui',
			[
				'title' => [
					'type' => 'hidden',
					'default' => $this->getPageTitle( $draftEntity->getTitle()->getText() ),
				],
				'action' => [
					'type' => 'hidden',
					'default' => 'direct-merge',
				],
			],
			$this->getContext()
		);
		$form->setMethod( 'POST' );
		$form->setSubmitTextMsg( $this->msg( 'da-specialinbox-merge-submit' ) );
		$form->setSubmitCallback( [ $this, 'onDirectMerge' ] );
		$form->show();
	}

	private function outputCompare( VerificationEntity $draft, VerificationEntity $target ) {
		$this->getOutput()->addWikiMsg(
			'da-specialinbox-compare',
			$draft->getTitle()->getText()
		);
		$tree = $this->getInboxImporter()->getTreeBuilder()->buildPreImportTree(
			$draft->getTitle(), $target->getTitle()
		);

		$this->getOutput()->addHTML(
			\Html::element( 'div', [
				'id' => 'da-specialinbox-compare',
				'data-tree' => json_encode( $tree ),
				'data-draft' => $draft->getTitle()->getArticleID(),
				'data-target' => $target->getTitle()->getArticleID(),
			] )
		);
		$this->getOutput()->addModules( 'ext.DataAccounting.inbox.compare' );
	}

	public function onDirectMerge( $formData ) {
		if ( !$this->subject ) {
			return $this->msg( 'da-specialinbox-merge-error-no-subject' );
		}
		if ( $formData['action'] === 'direct-merge' ) {
			if ( $this->target ) {
				return $this->msg( 'da-specialinbox-merge-error-target-exists' );
			}
			$subjectTitle = $this->subject->getTitle();
			// Title same as the subject, but not in NS_INBOX
			$targetTitle = $this->titleFactory->newFromText( $subjectTitle->getDBkey() );
			if ( !$targetTitle ) {
				return $this->msg( 'da-specialinbox-merge-error-invalid target' );
			}
			$inboxImporter = $this->getInboxImporter();
			$importStatus = $inboxImporter->importDirect( $this->subject, $targetTitle, $this->getUser() );
			if ( !$importStatus->isOK() ) {
				return $this->msg( $importStatus->getMessage() );
			}
			$this->getOutput()->clearHTML();
			$this->getOutput()->addWikiMsg( 'da-specialinbox-direct-merge-success', $targetTitle->getPrefixedText() );
			return true;
		}

		return false;
	}

	/**
	 * @return InboxImporter
	 */
	private function getInboxImporter(): InboxImporter {
		if ( $this->inboxImporter === null ) {
			$this->inboxImporter = new InboxImporter( $this->verificationEngine );
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
}