<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\TransclusionHashes;
use DataAccounting\Hasher\DbRevisionVerificationRepo;
use DataAccounting\Util\TransclusionHashExtractor;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\Hook\MultiContentSaveHook;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\SlotRecord;
use TitleFactory;

class AddTransclusionHashesOnSave implements MultiContentSaveHook, DASaveRevisionAddSlotsHook {
	/** @var TransclusionHashes|null */
	private $content = null;
	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
	}

	/**
	 * // TODO: Issue: This will allow null edits and clear out hashes!
	 *
	 * @param PageUpdater $updater
	 */
	public function onDASaveRevisionAddSlots( PageUpdater $updater ) {
		// This happens before saving of revision is initiated
		// We create an empty content for the hashes and store reference to it
		$this->content = new TransclusionHashes( '[]' );
		// We set it to the appropriate revision slot
		$updater->setContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES, $this->content );
	}

	/**
	 * @param \MediaWiki\Revision\RenderedRevision $renderedRevision
	 * @param \MediaWiki\User\UserIdentity $user
	 * @param \CommentStoreComment $summary
	 * @param int $flags
	 * @param \Status $status
	 * @return bool|void
	 */
	public function onMultiContentSave( $renderedRevision, $user, $summary, $flags, $status ) {
		if ( !$this->content ) {
			return;
		}
		// At this point we are in the middle of saving, all content slots for this edit must already
		// be inserted, and page was just parsed (but not saved yet)
		$po = $renderedRevision->getSlotParserOutput( SlotRecord::MAIN );

		// TODO: this must be a service! Also, DataAccountingFactory should be a service
		$verificationRepo = new DbRevisionVerificationRepo(
			MediaWikiServices::getInstance()->getDBLoadBalancer()
		);
		$extractor = new TransclusionHashExtractor( $po, $this->titleFactory, $verificationRepo );
		$hashmap = $extractor->getHashmap();
		// Now, with access to the PO of the main slot, we can extract included pages/files
		// and add the to the hashes slot, using the content which we previously added to the revision
		$this->content->setHashmap( $hashmap );
	}
}
