<?php

namespace DataAccounting\Verification;

use DataAccounting\Verification\Entity\WitnessEventEntity;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\MovePageFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Storage\PageUpdaterFactory;
use MediaWiki\Revision\SlotRecord;
use TitleFactory;
use User;

class WitnessingEngine {
	/** @var WitnessLookup */
	private $lookup;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var WikiPageFactory */
	private $wikiPageFactory;
	/** @var MovePageFactory */
	private $movePageFactory;
	/** @var PageUpdaterFactory */
	private $pageUpdaterFactory;
	/** @var RevisionStore */
	private $revisionStore;

	/**
	 * @param WitnessLookup $lookup
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param MovePageFactory $movePageFactory
	 * @param PageUpdaterFactory $pageUpdaterFactory
	 * @param RevisionStore $revisionStore
	 */
	public function __construct(
		WitnessLookup $lookup, TitleFactory $titleFactory, WikiPageFactory $wikiPageFactory,
		MovePageFactory $movePageFactory, PageUpdaterFactory $pageUpdaterFactory, RevisionStore $revisionStore
	) {
		$this->lookup = $lookup;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->movePageFactory = $movePageFactory;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @return WitnessLookup
	 */
	public function getLookup(): WitnessLookup {
		return $this->lookup;
	}

	public function addReceiptToDomainSnapshot( User $user, WitnessEventEntity $entity ) {
		$dm = "DomainSnapshot {$entity->get( 'witness_event_id' )}";
		if ( "Data Accounting:$dm" !== $entity->get( 'domain_snapshot_title' ) ) {
			throw new HttpException( "Domain Snapshot title is inconsistent.", 400 );
		}

		//6942 is custom namespace. See namespace definition in extension.json.
		$tentativeTitle = $this->titleFactory->makeTitle( 6942, $dm );
		$page = $this->wikiPageFactory->newFromTitle( $tentativeTitle );

		$pageText = $page->getContent()->getText();
		// We create a new content using the old content, and append $text to it.
		$newText = $pageText . $this->compileReceiptText( $entity );

		// TODO: We should not just assume page has Wikitext content
		$newContent = new \WikitextContent( $newText );
		$updater = $this->pageUpdaterFactory->newPageUpdater( $page, $user );
		$updater->setContent( SlotRecord::MAIN, $newContent );
		$newRevision = $updater->saveRevision(
			\CommentStoreComment::newUnsavedComment( "Domain Snapshot witnessed" )
		);

		if ( !$newRevision instanceof RevisionRecord ) {
			throw new \MWException( 'Could not store receipt to Domain Snapshot' );
		}

		// Rename from tentative title to final title.
		$domainSnapshotVH = $entity->get( 'domain_snapshot_genesis_hash' );
		$finalTitle = $this->titleFactory->makeTitle( 6942, "DomainSnapshot:$domainSnapshotVH" );
		$movePage = $this->movePageFactory->newMovePage( $tentativeTitle, $finalTitle );
		$mp = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage( $tentativeTitle, $finalTitle );
		$reason = "Changed from tentative title to final title";
		$createRedirect = false;
		$movePage->move( $user, $reason, $createRedirect );
		$this->getLookup()->updateWitnessEventEntity( $entity, [
			'domain_snapshot_title' => $finalTitle->getPrefixedText(),
		] );
	}

	/**
	 * @param WitnessEventEntity $entity
	 * @return string
	 */
	private function compileReceiptText( WitnessEventEntity $entity ) {
		$text = "\n<h1> Witness Event Publishing Data </h1>\n";
		$text .= "<p> This means, that the Witness Event Verification Hash has been written to a Witness Network and has been Timestamped.\n";

		$text .= "* Witness Event: {$entity->get( 'witness_event_id' )}\n";
		$text .= "* Domain ID: {$entity->get( 'domain_id' )}\n";
		// We don't include witness hash.
		$text .= "* Page Domain Snapshot verification Hash: {$entity->get( 'domain_snapshot_genesis_hash' )}\n";
		$text .= "* Merkle Root: {$entity->get( 'merkle_root' )}\n";
		$text .= "* Witness Event Verification Hash: {$entity->get( 'witness_event_verification_hash' )}\n";
		$text .= "* Witness Network: {$entity->get( 'witness_network' )}\n";
		$text .= "* Smart Contract Address: {$entity->get( 'smart_contract_address' )}\n";
		$text .= "* Transaction Hash: {$entity->get( 'witness_event_transaction_hash' )}\n";
		$text .= "* Sender Account Address: {$entity->get( 'sender_account_address' )}\n";

		return $text;
	}
}
