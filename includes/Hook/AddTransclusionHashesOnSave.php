<?php

namespace DataAccounting\Hook;

use CommentStoreComment;
use DataAccounting\Content\FileVerificationContent;
use DataAccounting\Content\TransclusionHashes;
use DataAccounting\Util\TransclusionHashExtractor;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Storage\Hook\MultiContentSaveHook;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\SlotRecord;
use MediaWiki\User\UserIdentity;
use Status;
use TitleFactory;
use WikiPage;

class AddTransclusionHashesOnSave implements MultiContentSaveHook, DASaveRevisionAddSlotsHook {
	/** @var TransclusionHashes|null */
	private ?TransclusionHashes $content = null;
	/** @var WikiPage|null */
	private ?WikiPage $wikiPage = null;
	/** @var TitleFactory */
	private TitleFactory $titleFactory;
	/** @var VerificationEngine */
	private VerificationEngine $verificationEngine;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory, VerificationEngine $verificationEngine ) {
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
	}

	/**
	 * // TODO: Issue: This will allow null edits and clear out hashes!
	 *
	 * @param PageUpdater $updater
	 */
	public function onDASaveRevisionAddSlots( PageUpdater $updater, WikiPage $wikiPage ) {
		$this->wikiPage = $wikiPage;
		if ( $wikiPage->getTitle()->getNamespace() === NS_FILE ) {
			$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
			$file = $repoGroup->findFile( $wikiPage->getTitle() );
			if ( $file && $file->isLocal() ) {
				$content = FileVerificationContent::newFromFile( $file );
				if ( $content ) {
					$updater->setContent( FileVerificationContent::SLOT_ROLE_FILE_VERIFICATION, $content );
				}
			}
		}

		// This happens before saving of revision is initiated
		// We create an empty content for the hashes and store reference to it
		$this->content = new TransclusionHashes( '[]' );
		// We set it to the appropriate revision slot
		$updater->setContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES, $this->content );
	}

	/**
	 * @param RenderedRevision $renderedRevision
	 * @param UserIdentity $user
	 * @param CommentStoreComment $summary
	 * @param int $flags
	 * @param Status $status
	 * @return bool|void
	 */
	public function onMultiContentSave( $renderedRevision, $user, $summary, $flags, $status ) {
		if ( !$this->content ) {
			return;
		}
		// At this point we are in the middle of saving, all content slots for this edit must already
		// be inserted, and page was just parsed (but not saved yet)
		$po = $renderedRevision->getSlotParserOutput( SlotRecord::MAIN );
		$extractor = new TransclusionHashExtractor( $po, $this->titleFactory, $this->verificationEngine );
		$hashmap = $extractor->getHashmap();
		// Now, with access to the PO of the main slot, we can extract included pages/files
		// and add the to the hashes slot, using the content which we previously added to the revision
		$this->content->setHashmap( $hashmap );
	}
}
