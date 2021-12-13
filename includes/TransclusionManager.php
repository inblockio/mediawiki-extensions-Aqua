<?php

namespace DataAccounting;

use CommentStoreComment;
use DataAccounting\Content\TransclusionHashes;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationEntity;
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
	public const STATE_UNCHANGED = 'unchanged';
	public const STATE_INVALID = 'invalid';

	/** @var TitleFactory */
	private TitleFactory $titleFactory;
	/** @var VerificationEngine */
	private VerificationEngine $verificationEngine;
	/** @var RevisionStore */
	private RevisionStore $revisionStore;
	/** @var PageUpdaterFactory */
	private PageUpdaterFactory $pageUpdaterFactory;
	/** @var WikiPageFactory */
	private WikiPageFactory $wikiPageFactory;

	/**
	 * @param TitleFactory $titleFactory
	 * @param VerificationEngine $verificationEngine
	 * @param PageUpdaterFactory $pageUpdaterFactory
	 */
	public function __construct(
		TitleFactory $titleFactory, VerificationEngine $verificationEngine, RevisionStore $revisionStore,
		PageUpdaterFactory $pageUpdaterFactory, WikiPageFactory $wikiPageFactory
	) {
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
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
			$state = [
				'titleObject' => $title,
				'state' => static::STATE_INVALID,
			];
			$latestEntity = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $title );
			if ( $transclusion->{VerificationEntity::HASH_TYPE_VERIFICATION} === null ) {
				// Title didnt exist at time of transclusion...
				if ( $latestEntity ) {
					//... but now exists
					$state['state'] = static::STATE_NEW_VERSION;
				} else {
					// ... still does not exist
					$state['state'] = static::STATE_UNCHANGED;
				}
			} else {
				$recordedEntity = $this->verificationEngine->getLookup()->getVerificationEntityFromQuery( [
					'rev_id' => $transclusion->revid,
					VerificationEntity::HASH_TYPE_GENESIS => $transclusion->{VerificationEntity::HASH_TYPE_GENESIS},
					VerificationEntity::HASH_TYPE_CONTENT => $transclusion->{VerificationEntity::HASH_TYPE_CONTENT},
				] );
				if ( $recordedEntity === null || $latestEntity === null ) {
					// Entity no longer exists in DB => something weird happening
					$state['state'] = static::STATE_INVALID;
				} elseif (
					$recordedEntity->getHash( VerificationEntity::HASH_TYPE_CONTENT ) ===
					$latestEntity->getHash( VerificationEntity::HASH_TYPE_CONTENT )
				) {
					$states['state'] = static::STATE_UNCHANGED;
				} elseif ( $recordedEntity->getRevision()->getId() < $latestEntity->getRevision()->getId() ) {
					$states['state'] = static::STATE_NEW_VERSION;
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
	 * @param \stdClass $resourceDetails
	 * @param File $file
	 * @return File|null
	 */
	public function getFileForResource( $resourceDetails, File $file ): ?File {
		$entity = $this->getVerificationEntityForResource( $resourceDetails );
		if ( !$entity ) {
			return null;
		}
		if ( $entity->getRevision()->isCurrent() ) {
			return $file;
		}

		$oldFiles = $file->getHistory();
		foreach ( $oldFiles as $oldFile ) {
			if ( $oldFile->getTimestamp() === $entity->getRevision()->getTimestamp() ) {
				return $oldFile;
			}
		}
		return null;
	}

	/**
	 * @param \stdClass $resourceDetails
	 * @return RevisionRecord|null
	 */
	public function getRevisionForResource( $resourceDetails ): ?RevisionRecord {
		$entity = $this->getVerificationEntityForResource( $resourceDetails );
		return ( $entity instanceof VerificationEntity ) ? $entity->getRevision() : null;
	}

	/**
	 * @param \stdClass $resourceDetails
	 * @return VerificationEntity|null
	 */
	private function getVerificationEntityForResource( $resourceDetails ): ?VerificationEntity {
		return $this->verificationEngine->getLookup()->getVerificationEntityFromQuery( [
			'rev_id' => $resourceDetails->revid,
			VerificationEntity::HASH_TYPE_GENESIS => $resourceDetails->{VerificationEntity::HASH_TYPE_GENESIS},
			VerificationEntity::HASH_TYPE_CONTENT => $resourceDetails->{VerificationEntity::HASH_TYPE_CONTENT},
		] );
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
		$resourceHash = $hashContent->getTransclusionDetails( $resourceTitle );
		if ( $resourceHash === false ) {
			// Resource not transcluded on this title, new transclusions can
			// only be added through normal edits
			return false;
		}

		// Probably not strictly necessary to create a new content
		$content = new TransclusionHashes( $hashContent->getText() );
		$entity = $this->verificationEngine->getLookup()->verificationEntityFromTitle(
			$resourceTitle
		);
		if ( $entity === null ) {
			return false;
		}
		$wikipage = $this->wikiPageFactory->newFromTitle( $revision->getPage() );
		if ( $wikipage === null ) {
			return false;
		}
		$content->updateHashForResource(
			$resourceTitle,
			$entity->getHash( VerificationEntity::HASH_TYPE_VERIFICATION ),
			VerificationEntity::HASH_TYPE_VERIFICATION
		);
		$content->updateHashForResource(
			$resourceTitle,
			$entity->getHash( VerificationEntity::HASH_TYPE_CONTENT ),
			VerificationEntity::HASH_TYPE_CONTENT
		);
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
