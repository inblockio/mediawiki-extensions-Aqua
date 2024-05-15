<?php

namespace DataAccounting;

use CommentStoreComment;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use Exception;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use Message;
use MWException;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;
use WikitextContent;

class RevisionManipulator {

	/** @var ILoadBalancer */
	private $lb;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var VerificationEngine */
	private $verificationEngine;

	/** @var WikiPageFactory */
	private $wikipageFactory;

	/**
	 * @param ILoadBalancer $lb
	 * @param RevisionStore $revisionStore
	 * @param VerificationEngine $verificationEngine
	 * @param WikiPageFactory $wpf
	 */
	public function __construct(
		ILoadBalancer $lb, RevisionStore $revisionStore, VerificationEngine $verificationEngine, WikiPageFactory $wpf
	) {
		$this->lb = $lb;
		$this->revisionStore = $revisionStore;
		$this->verificationEngine = $verificationEngine;
		$this->wikipageFactory = $wpf;
	}

	/**
	 * @param array $revisionIds
	 *
	 * @return void
	 * @throws Exception
	 */
	public function deleteRevisions( array $revisionIds ) {
		$this->assertRevisionsOfSamePage( $revisionIds );
		$firstToDelete = min( $revisionIds );
		$firstToDelete = $this->revisionStore->getRevisionById( $firstToDelete );
		$nowLatest = $this->revisionStore->getPreviousRevision( $firstToDelete );
		if ( !$nowLatest ) {
			throw new Exception( 'After deleting requested revisions, no revision remains' );
		}
		$this->rawDeleteRevisions( $revisionIds );
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$dbw->update(
			'page',
			[ 'page_latest' => $nowLatest->getId() ],
			[ 'page_id' => $firstToDelete->getPageId() ],
			__METHOD__
		);

		foreach ( $revisionIds as $revisionId ) {
			$this->verificationEngine->getLookup()->deleteForRevId( $revisionId );
		}
		$this->verificationEngine->buildAndUpdateVerificationData(
			$this->verificationEngine->getLookup()->verificationEntityFromRevId( $nowLatest->getId() ),
			$nowLatest
		);
	}

	public function squashRevisions( array $revisionIds ) {
		$this->assertRevisionsOfSamePage( $revisionIds );
		// 1. Get the content of the latest revisions
		$latest = max( $revisionIds );
		$latest = $this->revisionStore->getRevisionById( $latest );
		if ( !$latest->isCurrent() ) {
			throw new Exception( 'Latest requested revision is not current' );
		}

		$firstToDelete = min( $revisionIds );
		$firstToDelete = $this->revisionStore->getRevisionById( $firstToDelete );
		$lastRemaining = $this->revisionStore->getPreviousRevision( $firstToDelete );

		$pageIdentity = $latest->getPage();
		$timestamp = $latest->getTimestamp();
		$actor = $latest->getUser();
		$roles = $latest->getSlotRoles();
		$contents = [];
		foreach ( $roles as $role ) {
			$contents[$role] = $latest->getContent( $role );
		}

		// 2. Delete all revisions that are about to be merged
		$this->rawDeleteRevisions( $revisionIds );

		// 3. Insert a new revision with the content of the latest revision
		$revRecord = new MutableRevisionRecord( $pageIdentity );
		$revRecord->setTimestamp( $timestamp );
		$revRecord->setUser( $actor );
		if ( $lastRemaining ) {
			$revRecord->setParentId( $lastRemaining->getId() );
		}
		$comment = CommentStoreComment::newUnsavedComment(
			Message::newFromKey( 'dataaccounting-squash-revisions-comment' )
				->params( count( $revisionIds ), implode( ',', $revisionIds ) )->inContentLanguage()->text()
		);
		$revRecord->setComment( $comment );
		$revRecord->setMinorEdit( false );
		$revRecord->setPageId( $pageIdentity->getId() );

		foreach ( $contents as $role => $content ) {
			$revRecord->setContent( $role, $content );
		}

		$revRecord = $this->revisionStore->insertRevisionOn( $revRecord, $this->lb->getConnection( DB_PRIMARY ) );
		$dbw = $this->lb->getConnection( DB_PRIMARY );

		// 4. Set new revision as the latest revision of the page
		$dbw->update(
			'page',
			[ 'page_latest' => $revRecord->getId() ],
			[ 'page_id' => $pageIdentity->getId() ],
			__METHOD__
		);
		// 5. Delete verification data for the deleted revisions
		foreach ( $revisionIds as $revisionId ) {
			$this->verificationEngine->getLookup()->deleteForRevId( $revisionId );
		}
		// 6. Update verification data for the new revision
		$this->verificationEngine->buildAndUpdateVerificationData(
			$this->verificationEngine->getLookup()->verificationEntityFromRevId( $revRecord->getId() ),
			$revRecord
		);
	}

	/**
	 * @param Title $source
	 * @param Title $target
	 * @param RevisionRecord $maxRev
	 * @param UserIdentity $user
	 * @return void
	 * @throws MWException
	 */
	public function forkPage( Title $source, Title $target, RevisionRecord $maxRev, UserIdentity $user ) {
		$revision = $this->revisionStore->getFirstRevision( $source );
		if ( !$revision ) {
			throw new Exception( 'No revisions found for source page' );
		}
		// Set first revision of source as the parent for the first revision of the target
		$revisionParents = [
			$revision->getId() =>
				$this->verificationEngine->getLookup()->verificationEntityFromRevId( $revision->getId() ),
		];
		$revisions = [ $revision ];
		do {
			$revision = $this->revisionStore->getNextRevision( $revision );
			if ( !$revision ) {
				break;
			}
			$revisions[] = $revision;
			if ( $revision->getId() === $maxRev->getId() ) {
				break;
			}
		} while ( true );

		$this->insertRevisions( $revisions, $target, $user, $revisionParents );
	}

	/**
	 * @param RevisionRecord[] $revisions
	 * @param Title $target
	 * @param UserIdentity $user
	 * @param array $revisionParents
	 * @return VerificationEntity[]
	 * @throws MWException
	 */
	private function insertRevisions(
		array $revisions, Title $target, UserIdentity $user, array $revisionParents
	): array {
		$createdEntities = [];
		foreach ( $revisions as $revision ) {
			$newRev = $this->doInsertRevision( $revision, $target, $user );
			if ( !$newRev ) {
				throw new Exception( 'Failed to save revision' );
			}
			$parentEntity = $revisionParents[$revision->getId()] ?? null;
			$createdEntities[] = $this->verificationEngine->buildAndUpdateVerificationData(
				$this->verificationEngine->getLookup()->verificationEntityFromRevId( $newRev->getId() ),
				$newRev, $parentEntity
			);
		}
		// Remove nulls
		return array_filter( $createdEntities );
	}

	/**
	 * @param RevisionRecord $revision
	 * @param Title $target
	 * @param UserIdentity $user
	 * @return RevisionRecord|null
	 * @throws MWException
	 */
	private function doInsertRevision( RevisionRecord $revision, Title $target, UserIdentity $user ): ?RevisionRecord {
		$wp = $this->wikipageFactory->newFromTitle( $target );
		$updater = $wp->newPageUpdater( $user );
		$roles = $revision->getSlotRoles();
		foreach ( $roles as $role ) {
			$content = $revision->getContent( $role );
			$updater->setContent( $role, $content );
		}

		return $updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'Forked from ' . $revision->getId() ),
			EDIT_SUPPRESS_RC | EDIT_INTERNAL
		);
	}

	/**
	 * @param Title $local
	 * @param Title $remote
	 * @param UserIdentity $user
	 * @param string $mergedText
	 * @return void
	 * @throws MWException
	 */
	public function mergePages( Title $local, Title $remote, UserIdentity $user, string $mergedText ) {
		$localEntities = $this->getVerificationEntitiesForMerge( $local );
		$remoteEntities = $this->getVerificationEntitiesForMerge( $remote );
		$commonParent = $this->getCommonParent( $localEntities, $remoteEntities );
		if ( !$commonParent ) {
			throw new Exception( 'No common parent found' );
		}
		$this->assertSameGenesis( $localEntities, $remoteEntities );

		$wp = $this->wikipageFactory->newFromTitle( $local );
		$lastInserted = null;
		foreach ( $remoteEntities as $hash => $remoteEntity ) {
			if ( isset( $localEntities[$hash] ) ) {
				error_log( "Skipping $hash" );
				// Exists locally
				continue;
			}
			error_log( "Inserting $hash" );
			$newLocalRev = $this->doInsertRevision( $remoteEntity->getRevision(), $local, $user );
			error_log( "CREATED REV: " . ( $newLocalRev ? $newLocalRev->getId() : 'failed' ) );
			$remoteEntity = $this->moveVerificationEntity( $remoteEntity, $newLocalRev );
			if ( !$remoteEntity ) {
				throw new Exception( 'Failed to move verification entity' );
			}
			error_log( "RELOADED ENTITY" );
			$lastInserted = $remoteEntity;
		}
		// Insert merged revision
		$updater = $wp->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, new WikitextContent( $mergedText ) );
		$mergedRev = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'Merged from ' . $remote->getPrefixedText() )
		);
		if ( !$mergedRev ) {
			throw new Exception( 'Failed to save merged revision' );
		}
		// Last local revision is the official parent, since remote changes are branched-off
		$lastLocal = array_pop( $localEntities );
		$this->verificationEngine->buildAndUpdateVerificationData(
			$this->verificationEngine->getLookup()->verificationEntityFromRevId( $mergedRev->getId() ),
			$mergedRev, $lastLocal, $lastInserted->getHash()
		);
	}

	/**
	 * @param VerificationEntity $entity
	 * @param RevisionRecord $newRevision
	 * @return VerificationEntity|null
	 * @throws Exception
	 */
	private function moveVerificationEntity(
		VerificationEntity $entity, RevisionRecord $newRevision
	): ?VerificationEntity {
		$newVE = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $newRevision->getId() );
		error_log( "RETRIEVE ENTITY FOR REV: {$newRevision->getId()}" );
		if ( !$newVE ) {
			error_log( "NOT" );
			throw new Exception( 'Failed to get verification entity for new revision' );
		}
		// Do switching, replace newly inserted VE for new revision with old one,
		// but update data so that it points to the new revision
		$insertData = [
			'page_id' => $newVE->getTitle()->getArticleID(),
			'page_title' => $newVE->getTitle()->getPrefixedDBkey()
		];
		error_log( "UPDATING" );
		error_log( var_export( $insertData, 1 ) );
		$db = $this->lb->getConnection( DB_PRIMARY );
		// Delete new one
		error_log( "DELETE NEW ONE" );
		$this->verificationEngine->getLookup()->deleteForRevId( $newRevision->getId() );
		// Update old VE, so that it points to the new revision
		$db->update(
			'revision_verification',
			$insertData,
			[ 'rev_id' => $entity->getRevision()->getId() ],
		);
		error_log( "QUERY: " . $db->lastQuery() );

		// Reload updated VE
		return $this->verificationEngine->getLookup()->verificationEntityFromRevId( $newRevision->getId() );
	}

	/**
	 * @param Title $title
	 * @return VerificationEntity[]
	 */
	private function getVerificationEntitiesForMerge( Title $title ) {
		$revs = $this->verificationEngine->getLookup()->getAllRevisionIds( $title );
		$entities = [];
		foreach ( $revs as $rev ) {
			$entity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $rev );
			if ( !$entity ) {
				continue;
			}
			$entities[$entity->getHash()] = $entity;
		}

		return $entities;
	}

	/**
	 * @param array $local
	 * @param array $remote
	 * @return void
	 * @throws Exception
	 */
	private function assertSameGenesis( array $local, array $remote ) {
		if ( empty( $local ) || empty( $remote ) ) {
			throw new Exception( 'No revisions found in source or target page' );
		}
		$localGenesis = $local[array_key_first( $local )]->getHash( VerificationEntity::GENESIS_HASH );
		$remoteGenesis = $remote[array_key_first( $remote )]->getHash( VerificationEntity::GENESIS_HASH );
		if ( $localGenesis !== $remoteGenesis ) {
			throw new Exception( 'Source and target pages have different genesis hashes' );
		}
	}

	/**
	 * Execute actual deletion
	 *
	 * @param array $revisionIds
	 *
	 * @return void
	 */
	protected function rawDeleteRevisions( array $revisionIds ) {
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		// Delete revisions
		$dbw->delete( 'revision', [ 'rev_id' => $revisionIds ], __METHOD__ );
		$dbw->delete( 'ip_changes', [ 'ipc_rev_id' => $revisionIds ], __METHOD__ );
	}

	/**
	 * @param array $revisionIds
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function assertRevisionsOfSamePage( array $revisionIds ) {
		$pageId = null;
		foreach ( $revisionIds as $id ) {
			$rev = $this->revisionStore->getRevisionById( $id );
			if ( !$rev ) {
				throw new Exception( "Revision $id does not exist" );
			}
			if ( $pageId === null ) {
				$pageId = $rev->getPageId();
				continue;
			}
			if ( $pageId !== $rev->getPageId() ) {
				throw new Exception( 'Requested revisions are not of the same page' );
			}
		}
	}

	/**
	 * @param array $localEntities
	 * @param array $remoteEntities
	 * @return VerificationEntity|null
	 */
	private function getCommonParent( array $localEntities, array $remoteEntities ): ?VerificationEntity {
		$localHashes = array_keys( $localEntities );
		$remoteHashes = array_keys( $remoteEntities );
		$common = array_intersect( $localHashes, $remoteHashes );
		if ( empty( $common ) ) {
			return null;
		}
		$last = array_pop( $common );
		return $localEntities[$last];
	}

}
