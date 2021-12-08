<?php

namespace DataAccounting;

use CommentStoreComment;
use DataAccounting\Content\TransclusionHashes;
use File;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\PageUpdaterFactory;
use MediaWiki\User\UserIdentity;
use Title;
use TitleFactory;

class TransclusionManager {
	public const STATE_NEW_VERSION = 'new-version';
	public const STATE_HASH_CHANGED = 'hash-changed';
	public const STATE_UNCHANGED = 'unchanged';

	/** @var TitleFactory */
	private TitleFactory $titleFactory;
	/** @var HashLookup */
	private HashLookup $hashLookup;
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
		TitleFactory $titleFactory, HashLookup $hashLookup,
		PageUpdaterFactory $pageUpdaterFactory, WikiPageFactory $wikiPageFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->hashLookup = $hashLookup;
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
		$transclusions = $this->getTransclusionHashes( $revision );
		foreach ( $transclusions as $transclusion ) {
			$title = $this->titleFactory->makeTitle( $transclusion->ns, $transclusion->dbkey );
			$latestHash = $this->hashLookup->getLatestHashForTitle( $title );
			$state = [
				'titleObject' => $title,
				'hash' => $transclusion->hash,
			];
			if ( $latestHash !== $transclusion->hash ) {
				$state['state'] = static::STATE_NEW_VERSION;
				$state['newHash'] = $latestHash;
			} else {
				$state['state'] = static::STATE_UNCHANGED;
			}
			if ( $transclusion->hash !== null ) {
				$hashExists = $this->hashLookup->getRevisionForHash( $transclusion->hash ) !== null;
				if ( !$hashExists ) {
					$state['state'] = static::STATE_HASH_CHANGED;
					$state['newHash'] = $this->hashLookup->getHashForRevision( $revision );
				}
			}

			$states[] = $state;
		}

		return $states;
	}

	/**
	 * @param RevisionRecord $revision
	 * @return array
	 */
	public function getTransclusionHashes( RevisionRecord $revision ): array {
		$content = $revision->getContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES );
		if ( !$content instanceof TransclusionHashes ) {
			return [];
		}
		return $content->getResourceHashes();
	}

	/**
	 * @param array $hashes
	 * @param LinkTarget|PageReference $title
	 * @return string|null|false if resource is not listed
	 */
	public function getHashForTitle( array $hashes, $title ): ?string {
		foreach ( $hashes as $hashEntity ) {
			if ( $title->getNamespace() === $hashEntity->ns && $title->getDBkey() === $hashEntity->dbkey ) {
				return $hashEntity->hash;
			}
		}

		return false;
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
	 * @return RevisionRecord|null
	 */
	public function getRevisionForHash( string $hash ): ?RevisionRecord {
		return $this->hashLookup->getRevisionForHash( $hash );
	}

	/**
	 * @param RevisionRecord $revision
	 * @param string $resourcePage
	 * @return bool
	 */
	public function updateResource( RevisionRecord $revision, $resourcePage, UserIdentity $user ) {
		$hashes = $this->getTransclusionHashes( $revision );
		$resourceTitle = $this->titleFactory->newFromText( $resourcePage );
		if ( !( $resourceTitle instanceof Title ) ) {
			return false;
		}
		$resourceHash = $this->getHashForTitle( $hashes, $resourceTitle );
		if ( $resourceHash === false ) {
			return false;
		}

		$oldContent = $revision->getContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES );
		if ( !$oldContent instanceof TransclusionHashes || !$oldContent->isValid() ) {
			return false;
		}
		$content = new TransclusionHashes( $oldContent->getText() );
		$latestHash = $this->hashLookup->getLatestHashForTitle( $resourceTitle );
		if ( $latestHash === null ) {
			return false;
		}
		$wikipage = $this->wikiPageFactory->newFromTitle( $revision->getPage() );
		if ( $wikipage === null ) {
			return false;
		}
		$content->updateHashForResource( $resourceTitle, $latestHash );
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
