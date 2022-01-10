<?php

namespace DataAccounting\Hook;

use CommentStoreComment;
use DataAccounting\Content\FileHashContent;
use DataAccounting\Content\TransclusionHashes;
use DataAccounting\Util\TransclusionHashExtractor;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Storage\Hook\MultiContentSaveHook;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use RepoGroup;
use Status;
use TitleFactory;
use WikiPage;

class AddTransclusionHashesOnSave implements MultiContentSaveHook, DASaveRevisionAddSlotsHook {
	/** @var TransclusionHashes|null */
	private ?TransclusionHashes $transclusionHashesContent = null;
	/** @var WikiPage|null */
	private ?WikiPage $wikiPage = null;
	private ?FileHashContent $fileHashContent = null;
	/** @var TitleFactory */
	private TitleFactory $titleFactory;
	/** @var VerificationEngine */
	private VerificationEngine $verificationEngine;
	private RepoGroup $repoGroup;

	/**
	 * @param TitleFactory $titleFactory
	 * @param VerificationEngine $verificationEngine
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		TitleFactory $titleFactory, VerificationEngine $verificationEngine, RepoGroup $repoGroup
	) {
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
		$this->repoGroup = $repoGroup;
	}

	/**
	 *
	 * @param PageUpdater $updater
	 */
	public function onDASaveRevisionAddSlots( PageUpdater $updater, WikiPage $wikiPage ) {
		if ( $wikiPage->getTitle()->getNamespace() === 6942 ) {
			// Do not scan domain snapshots
			return;
		}
		$this->wikiPage = $wikiPage;
		if ( $wikiPage->getTitle()->getNamespace() === NS_FILE ) {
			$this->fileHashContent = new FileHashContent( '' );
			$updater->setContent( FileHashContent::SLOT_ROLE_FILE_HASH, $this->fileHashContent );
		}

		// This happens before saving of revision is initiated
		// We create an empty content for the hashes and store reference to it
		$this->transclusionHashesContent = new TransclusionHashes( '' );
		// We set it to the appropriate revision slot
		$updater->setContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES, $this->transclusionHashesContent );
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
		if ( $this->fileHashContent ) {
			$file = $this->repoGroup->findFile( $this->wikiPage->getTitle(), [ 'bypassCache' => true ] );
			if ( $file && $file->isLocal() ) {
				$this->fileHashContent->setHashFromFile( $file );
			}
		}

		if ( !$this->transclusionHashesContent ) {
			return;
		}
		// At this point we are in the middle of saving, all content slots for this edit must already
		// be inserted, and page was just parsed (but not saved yet)
		$po = $renderedRevision->getSlotParserOutput( SlotRecord::MAIN );
		$extractor = new TransclusionHashExtractor(
			$renderedRevision->getRevision()->getPageAsLinkTarget(),
			$po, $this->titleFactory, $this->verificationEngine
		);
		$hashmap = $extractor->getHashmap();
		// Now, with access to the PO of the main slot, we can extract included pages/files
		// and add the to the hashes slot, using the content which we previously added to the revision
		$this->transclusionHashesContent->setHashmap( $hashmap );
	}
}
