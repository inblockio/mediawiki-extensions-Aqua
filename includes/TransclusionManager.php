<?php

namespace DataAccounting;

use CommentStoreComment;
use DataAccounting\Content\TransclusionHashes;
use File;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\PageUpdaterFactory;
use MediaWiki\Storage\RevisionStore;
use MediaWiki\User\UserIdentity;
use Title;
use TitleFactory;

class TransclusionManager {
	public const STATE_NEW_VERSION = 'new-version';
	public const STATE_HASH_CHANGED = 'hash-changed';
	public const STATE_UNCHANGED = 'unchanged';
	public const STATE_INVALID = 'invalid';

	/** @var TitleFactory */
	private TitleFactory $titleFactory;
	/** @var HashLookup */
	private HashLookup $hashLookup;
	/** @var RevisionStore */
	private RevisionStore $revisionStore;
	/** @var PageUpdaterFactory */
	private PageUpdaterFactory $pageUpdaterFactory;
	/** @var WikiPageFactory */
	private WikiPageFactory $wikiPageFactory;

	/**
	 * @param TitleFactory $titleFactory
	 * @param HashLookup $hashLookup
	 * @param PageUpdaterFactory $pageUpdaterFactory
	 */
	public function __construct(
		TitleFactory $titleFactory, HashLookup $hashLookup, RevisionStore $revisionStore,
		PageUpdaterFactory $pageUpdaterFactory, WikiPageFactory $wikiPageFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->hashLookup = $hashLookup;
		$this->revisionStore = $revisionStore;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Get hash status of each included resource
	 * This only checks the hashes of target pages, does
	 * not do full verification of the content
	 *
	 * @param RevisionRecord $revision
	 * @return array
	 */
	public function getTransclusionState( RevisionRecord $revision ): array {
		$states = [];
		$hashContent = $this->getTransclusionHashesContent( $revision );
		if ( !$hashContent ) {
			return [];
		}
		$transclusions = $hashContent->getResourceHashes();
		foreach ( $transclusions as $transclusion ) {
			$title = $this->titleFactory->makeTitle( $transclusion->ns, $transclusion->dbkey );
			$latestHash = $this->hashLookup->getLatestHashForTitle( $title );
			$state = [
				'titleObject' => $title,
			];
			if ( $latestHash !== $transclusion->{HashLookup::HASH_TYPE_VERIFICATION} ) {
				$state['state'] = static::STATE_NEW_VERSION;
			} else {
				$state['state'] = static::STATE_UNCHANGED;
			}
			if ( $transclusion->{HashLookup::HASH_TYPE_VERIFICATION} !== null ) {
				$hashExists = $this->hashLookup->getRevisionForHash( $transclusion->{HashLookup::HASH_TYPE_VERIFICATION} ) !== null;
				if ( !$hashExists ) {
					$state['state'] = static::STATE_HASH_CHANGED;
				}
				$resourceRevision = $this->revisionStore->getRevisionById(
					$transclusion->revid, 0, $title
				);
				if ( !$resourceRevision ) {
					$state['state'] = static::STATE_INVALID;
				}
			}

			$states[] = $state;
		}

		return $states;
	}

	/**
	 * @param RevisionRecord $revision
	 * @return TransclusionHashes|null if slot not set
	 */
	public function getTransclusionHashesContent( RevisionRecord $revision ): ?TransclusionHashes {
		$content = $revision->getContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES );
		if ( !$content instanceof TransclusionHashes ) {
			return null;
		}
		return $content;
	}

	/**
	 * @param string $hash
	 * @param File $file
	 * @return File|null
	 */
	public function getFileForHash( string $hash, File $file ): ?File {
		$revision = $this->hashLookup->getRevisionForHash( $hash );
		if ( !$revision ) {
			return null;
		}
		if ( $revision->isCurrent() ) {
			return $file;
		}

		$oldFiles = $file->getHistory();
		foreach( $oldFiles as $oldFile ) {
			if ( $oldFile->getTimestamp() === $revision->getTimestamp() ) {
				return $oldFile;
			}
		}
		return null;
	}

	/**
	 * @param string $hash
	 * @param string $type
	 * @return RevisionRecord|null
	 */
	public function getRevisionForHash( string $hash, $type = HashLookup::HASH_TYPE_VERIFICATION ): ?RevisionRecord {
		return $this->hashLookup->getRevisionForHash( $hash, $type );
	}

	/**
	 * @param RevisionRecord $revision
	 * @param string $resourcePage
	 * @return bool
	 */
	public function updateResource( RevisionRecord $revision, $resourcePage, UserIdentity $user ) {
		$hashContent = $this->getTransclusionHashesContent( $revision );
		if ( !$hashContent instanceof TransclusionHashes || !$hashContent->isValid() ) {
			return false;
		}
		$resourceTitle = $this->titleFactory->newFromText( $resourcePage );
		if ( !( $resourceTitle instanceof Title ) ) {
			return false;
		}
		$resourceHash = $hashContent->getHashForResource( $resourceTitle );
		if ( $resourceHash === false ) {
			return false;
		}

		// Probably not strictly necessary to create a new content
		$content = new TransclusionHashes( $hashContent->getText() );
		$latestVerificationHash = $this->hashLookup->getLatestHashForTitle(
			$resourceTitle, HashLookup::HASH_TYPE_VERIFICATION
		);
		if ( $latestVerificationHash === null ) {
			return false;
		}
		$latestContentHash = $this->hashLookup->getLatestHashForTitle(
			$resourceTitle, HashLookup::HASH_TYPE_CONTENT
		);
		if ( $latestContentHash === null ) {
			return false;
		}
		$wikipage = $this->wikiPageFactory->newFromTitle( $revision->getPage() );
		if ( $wikipage === null ) {
			return false;
		}
		$content->updateHashForResource( $resourceTitle, $latestVerificationHash );
		$content->updateHashForResource( $resourceTitle, $latestContentHash, HashLookup::HASH_TYPE_CONTENT );
		$pageUpdater = $this->pageUpdaterFactory->newPageUpdater( $wikipage, $user );
		$pageUpdater->setContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES, $content );

		$pageUpdater->subscribersOff();
		$newRevision = $pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'Update of resource hash for ' . $resourceTitle->getPrefixedText()
			)
		);
		$pageUpdater->subscribersOn();

		return $newRevision instanceof RevisionRecord;
	}
}
