<?php

namespace DataAccounting;

use CommentStoreComment;
use DataAccounting\Content\SignatureContent;
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
use Wikimedia\Rdbms\IDatabase;
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

	/**
	 * @param array $revisionIds
	 * @return void
	 * @throws MWException
	 */
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
	 * @param Title $target
	 * @param RevisionRecord $revision
	 * @param UserIdentity $user
	 * @return void
	 * @throws MWException
	 */
	public function forkPage( Title $target, RevisionRecord $revision, UserIdentity $user ) {
		$parentEntity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $revision->getId() );
		if ( !$parentEntity ) {
			throw new Exception( 'Source page has no verification data' );
		}

		// Create new page, settings the last source revision as parent
		$wp = $this->wikipageFactory->newFromTitle( $target );
		$updater = $wp->newPageUpdater( $user );
		$roles = $revision->getSlotRoles();
		foreach ( $roles as $role ) {
			$content = $revision->getContent( $role );
			$updater->setContent( $role, $content );
		}
		$newRev = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				Message::newFromKey( 'dataaccounting-fork-page-comment' )
					->params( $this->shortenHash( $parentEntity->getHash() ) )
			),
			EDIT_SUPPRESS_RC | EDIT_INTERNAL
		);
		if ( !$newRev ) {
			throw new Exception( 'Failed to save revision' );
		}
		$this->verificationEngine->buildAndUpdateVerificationData(
			$this->verificationEngine->getLookup()->verificationEntityFromRevId( $newRev->getId() ),
			$newRev, $parentEntity, null, $parentEntity->getHash()
		);
	}

	/**
	 * @param Title $local
	 * @param Title $remote
	 * @param UserIdentity $user
	 * @param string|null $mergedText
	 * @return void
	 * @throws MWException
	 */
	public function mergePages(
		Title $local, Title $remote, UserIdentity $user, ?string $mergedText
	) {
		define( 'DA_MERGE', 1 );
		wfDebug( "Starting page merge: {$remote->getPrefixedText()} => {$local->getPrefixedText()}", 'da' );
		$localEntities = $this->getVerificationEntitiesForMerge( $local );
		$remoteEntities = $this->getVerificationEntitiesForMerge( $remote );
		$commonParent = $this->getCommonParent( $localEntities, $remoteEntities );
		if ( !$commonParent ) {
			throw new Exception( 'No common parent found' );
		}
		$this->assertSameGenesis( $localEntities, $remoteEntities );
		// Last entry in the local entities is the last revision
		// that was not forked, and is the parent of the first forked revision
		$lastLocal = end( $localEntities );

		wfDebug( "Last local revision is: " . $lastLocal->getRevision()->getId(), 'da' );
		$wp = $this->wikipageFactory->newFromTitle( $local );
		$lastInserted = null;
		$db = $this->lb->getConnection( DB_PRIMARY );

		$db->startAtomic( __METHOD__ );
		foreach ( $remoteEntities as $hash => $remoteEntity ) {
			if ( isset( $localEntities[$hash] ) ) {
				// Exists locally
				continue;
			}
			$parent = $lastInserted ?? $lastLocal;
			$isFork = $remoteEntity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ) ===
				$commonParent->getHash();
			$this->moveRevision( $db, $remoteEntity, $parent, $commonParent, $local, $isFork, __METHOD__ );
			wfDebug( "Moving revision " . $remoteEntity->getRevision()->getId() . ',isFork: ' . $isFork, 'da' );
			$lastInserted = $remoteEntity;
		}
		// Insert merged revision
		$updater = $wp->newPageUpdater( $user );
		$text = $mergedText ?? $lastInserted->getRevision()->getContent( SlotRecord::MAIN )->getText();
		$this->assertMergedContentDifferent( $lastLocal, $text, $db, __METHOD__ );
		wfDebug( "Creating new revision with text: " . $text, 'da' );
		$updater->setContent( SlotRecord::MAIN, new WikitextContent( $text ) );
		foreach ( $lastInserted->getRevision()->getSlotRoles() as $role ) {
			if ( $role === SlotRecord::MAIN ) {
				// Main role is already set by setting text
				continue;
			}
			if ( $role === SignatureContent::SLOT_ROLE_SIGNATURE ) {
				// Do not transfer signatures
				continue;
			}
			// Insert other slots
			$updater->setContent( $role, $lastInserted->getRevision()->getContent( $role ) );
		}
		$mergedRev = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'Merged from ' . $remote->getPrefixedText() )
		);
		if ( !$mergedRev ) {
			wfDebug( "Failed to create new revision" . $updater->getStatus()->getMessage(), 'da' );
			$db->cancelAtomic( __METHOD__ );
			throw new Exception( 'Failed to save merged revision' );
		}

		wfDebug( "Updating verification data", 'da' );
		// Last local revision is the official parent, since remote changes are branched-off
		// Set `merge-hash` on it as well, to bring remote chain back to local
		$this->verificationEngine->buildAndUpdateVerificationData(
			$this->verificationEngine->getLookup()->verificationEntityFromRevId( $mergedRev->getId() ),
			$mergedRev, $lastLocal, $lastInserted->getHash()
		);
		wfDebug( "Cleanup", 'da' );
		// Clean up entries from the remote page whose revisions have now been massacred
		$db->delete(
			'page',
			[ 'page_id' => $remote->getArticleID() ],
			__METHOD__
		);
		$db->delete(
			'revision_verification',
			[ 'page_id' => $remote->getArticleID() ],
			__METHOD__
		);
		wfDebug( 'Merge complete' );

		$db->endAtomic( __METHOD__ );
	}

	/**
	 * @param \Database $db
	 * @param VerificationEntity $entity
	 * @param VerificationEntity $parent
	 * @param VerificationEntity $commonParent
	 * @param Title $local
	 * @param bool $isFork
	 * @param string $mtd
	 * @return void
	 * @throws Exception
	 */
	private function moveRevision(
		IDatabase $db, VerificationEntity $entity, VerificationEntity $parent, VerificationEntity $commonParent,
		Title $local, bool $isFork, string $mtd
	) {
		// Move revision record
		$revData = [ 'rev_page' => $local->getArticleID() ];
		if ( $isFork ) {
			$revData['rev_parent_id'] = $parent->getRevision()->getId();
		}
		$res = $db->update( 'revision', $revData, [ 'rev_id' => $entity->getRevision()->getId() ] );
		if ( !$res ) {
			$db->cancelAtomic( $mtd );
			throw new Exception( 'Failed to move revision' );
		}
		// Move verification entity
		$data = [
			'page_id' => $local->getArticleID(),
			'page_title' => $local->getPrefixedDBkey(),
		];
		if ( $isFork ) {
			$data[VerificationEntity::FORK_HASH] = $commonParent->getHash();
			$data[VerificationEntity::PREVIOUS_VERIFICATION_HASH] = $parent->getHash();
		}
		wfDebug( "Setting verification data on moved revision: " . json_encode( $data ), 'da' );
		if ( !$db->update( 'revision_verification', $data, [ 'rev_id' => $entity->getRevision()->getId() ] ) ) {
			$db->cancelAtomic( $mtd );
			throw new Exception( 'Failed to move verification entity' );
		}
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

	/**
	 * @param string $hash
	 * @return string
	 */
	private function shortenHash( string $hash ): string {
		// Get first 5 and last 5 characters
		return substr( $hash, 0, 5 ) . '...' . substr( $hash, -5 );
	}

	/**
	 * @param VerificationEntity $lastLocal
	 * @param string $text
	 * @param IDatabase $db
	 * @param string $mtd
	 * @return void
	 * @throws Exception
	 */
	private function assertMergedContentDifferent(
		VerificationEntity $lastLocal, string $text, IDatabase $db, string $mtd
	) {
		if ( $text === $lastLocal->getRevision()->getContent( SlotRecord::MAIN )->getText() ) {
			$db->cancelAtomic( $mtd );
			throw new Exception( 'Merged content is the same as the last local revision. Nothing to merge' );
		}
	}
}
