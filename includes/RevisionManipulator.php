<?php

namespace DataAccounting;

use CommentStoreComment;
use DataAccounting\Content\SignatureContent;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use Exception;
use MediaWiki\Extension\SecurePoll\User\Auth;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Message;
use MWException;
use Throwable;
use Title;
use User;
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

	/** @var DeletePageFactory */
	private $deletePageFactory;

	/** @var \TitleFactory */
	private $titleFactory;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param ILoadBalancer $lb
	 * @param RevisionStore $revisionStore
	 * @param VerificationEngine $verificationEngine
	 * @param WikiPageFactory $wpf
	 * @param DeletePageFactory $deletePageFactory
	 * @param \TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		ILoadBalancer $lb, RevisionStore $revisionStore,
		VerificationEngine $verificationEngine, WikiPageFactory $wpf,
		DeletePageFactory $deletePageFactory, \TitleFactory $titleFactory, UserFactory $userFactory
	) {
		$this->lb = $lb;
		$this->revisionStore = $revisionStore;
		$this->verificationEngine = $verificationEngine;
		$this->wikipageFactory = $wpf;
		$this->deletePageFactory = $deletePageFactory;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
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
	 * @param string $hash
	 * @param Authority $user
	 * @return void
	 * @throws Exception
	 */
	public function deleteFromHash( string $hash, Authority $user ) {
		$entity = $this->verificationEngine->getLookup()->verificationEntityFromHash( $hash );
		if ( !$entity ) {
			throw new Exception( 'No revision found with the given hash', 404 );
		}
		$isFirst = $entity->getHash( VerificationEntity::GENESIS_HASH ) === $hash;
		if ( $isFirst ) {
			$this->deletePage( $entity->getRevision()->getPage(), $user );
			return;
		}
		$revision = $entity->getRevision();
		$revisionsToDelete = [];
		while ( $revision ) {
			$revisionsToDelete[] = $revision->getId();
			$revision = $this->revisionStore->getNextRevision( $revision );
		}
		$this->deleteRevisions( $revisionsToDelete );
	}

	/**
	 * @param PageIdentity $page
	 * @param Authority $user
	 * @return void
	 * @throws Exception
	 */
	private function deletePage( PageIdentity $page, Authority $user ) {
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			throw new Exception( 'Failed to get title from page identity' );
		}
		$deletePage = $this->deletePageFactory->newDeletePage( $title->toPageIdentity(), $user );
		$status = $deletePage->deleteUnsafe( 'Deleted by Guardian' );
		if ( !$status->isOK() ) {
			throw new Exception(
				'Tried to delete first revision of the page, therefore deleting whole page, which failed', 500
			);
		}
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

	private function getEntities( Title $local, Title $remote ) {
		define( 'DA_MERGE', 1 );
		wfDebug( "Starting page merge: {$remote->getPrefixedText()} => {$local->getPrefixedText()}", 'da' );
		$localEntities = $this->getVerificationEntitiesForMerge( $local );
		$remoteEntities = $this->getVerificationEntitiesForMerge( $remote );
		$commonParent = $this->getCommonParent( $localEntities, $remoteEntities );
		if ( !$commonParent ) {
			throw new Exception( 'No common parent found' );
		}
		$this->assertSameGenesis( $localEntities, $remoteEntities );
		return [ $localEntities, $remoteEntities, $commonParent ];
	}

	private function moveRevisions( array $remoteEntities, $commonParent, Title $local ): ?VerificationEntity {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$lastInserted = null;
		foreach ( $remoteEntities as $hash => $remoteEntity ) {
			if ( isset( $localEntities[$hash] ) ) {
				// Exists locally
				continue;
			}
			$parent = $lastInserted ?? $commonParent;
			$isFork = $remoteEntity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ) ===
				$commonParent->getHash();
			$this->moveRevision( $db, $remoteEntity, $parent, $commonParent, $local, $isFork, __METHOD__ );
			wfDebug( "Moving revision " . $remoteEntity->getRevision()->getId() . ',isFork: ' . $isFork, 'da' );
			$lastInserted = $remoteEntity;
		}
		$db->update(
			'page',
			[ 'page_latest' => $lastInserted->getRevision()->getId() ],
			[ 'page_id' => $local->getArticleID() ],
			__METHOD__
		);
		return $lastInserted;
	}

	public function moveChain( Title $local, Title $remote ) {
		[ $localEntities, $remoteEntities, $commonParent ] = $this->getEntities( $local, $remote );
		$this->moveRevisions( $remoteEntities, $commonParent, $local );
		$db = $this->lb->getConnection( DB_PRIMARY )->delete(
			'page',
			[ 'page_id' => $remote->getArticleID() ],
			__METHOD__
		);
		$this->verificationEngine->getLookup()->clearEntriesForPage( $remote );
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
		$db = $this->lb->getConnection( DB_PRIMARY );
		[ $localEntities, $remoteEntities, $commonParent ] = $this->getEntities( $local, $remote );
		// Last entry in the local entities is the last revision
		// that was not forked, and is the parent of the first forked revision
		$lastLocal = end( $localEntities );
		$lastRemote = end( $remoteEntities );
		$text = $mergedText ?? $lastRemote->getRevision()->getContent( SlotRecord::MAIN )->getText();
		$slotsToCopy = [];
		$roles = $lastRemote->getRevision()->getSlotRoles();
		foreach ( $roles as $role ) {
			if ( $role === SlotRecord::MAIN ) {
				// Main role is already set by setting text
				continue;
			}
			$slotsToCopy[$role] = $lastRemote->getRevision()->getContent( $role );
		}
		$this->assertMergedContentDifferent( $lastLocal, $text, $slotsToCopy );

		wfDebug( "Last local revision is: " . $lastLocal->getRevision()->getId(), 'da' );
		$wp = $this->wikipageFactory->newFromTitle( $local );

		$db->startAtomic( __METHOD__ );
		try {
			$lastInserted = $this->moveRevisions( $remoteEntities, $commonParent, $local );
		} catch ( Throwable $ex ) {
			$db->cancelAtomic( __METHOD__ );
			throw $ex;
		}

		// Insert merged revision
		$updater = $wp->newPageUpdater( $user );
		wfDebug( "Creating new revision with text: " . $text, 'da' );
		$updater->setContent( SlotRecord::MAIN, new WikitextContent( $text ) );
		foreach ( $slotsToCopy as $copyRole => $copyContent ) {
			$updater->setContent( $copyRole, $copyContent );
		}
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
		$this->verificationEngine->getLookup()->clearEntriesForPage( $remote );
		wfDebug( 'Merge complete' );

		$db->endAtomic( __METHOD__ );
	}

	/**
	 * @param Title $source
	 * @param Title $target
	 * @return void
	 * @throws Exception
	 */
	public function movePage( Title $source, Title $target ) {
		wfDebug( "Starting page move: {$source->getPrefixedText()} => {$target->getPrefixedText()}", 'da' );
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->startAtomic( __METHOD__ );
		$db->update(
			'page',
			[ 'page_title' => $target->getDBkey(), 'page_namespace' => $target->getNamespace() ],
			[ 'page_id' => $source->getArticleID() ],
			__METHOD__
		);
		$db->update(
			'revision_verification',
			[ 'page_title' => $target->getPrefixedText() ],
			[ 'page_id' => $source->getArticleID() ],
			__METHOD__
		);

		$db->endAtomic( __METHOD__ );
	}

	/**
	 * @param \Database $db
	 * @param VerificationEntity $entity
	 * @param VerificationEntity|null $parent
	 * @param VerificationEntity|null $commonParent
	 * @param Title $local
	 * @param bool $isFork
	 * @param string $mtd
	 * @return void
	 * @throws Exception
	 */
	private function moveRevision(
		IDatabase $db, VerificationEntity $entity, ?VerificationEntity $parent, ?VerificationEntity $commonParent,
		Title $local, bool $isFork, string $mtd
	) {
		// Move revision record
		$revData = [ 'rev_page' => $local->getArticleID() ];
		if ( $isFork ) {
			$revData['rev_parent_id'] = $parent->getRevision()->getId();
		}
		$res = $db->update( 'revision', $revData, [ 'rev_id' => $entity->getRevision()->getId() ] );
		if ( !$res ) {
			throw new Exception( 'Failed to move revision' );
		}
		// Move verification entity
		$data = [
			'page_id' => $local->getArticleID(),
			'page_title' => $local->getPrefixedText(),
		];
		if ( $isFork ) {
			$data[VerificationEntity::FORK_HASH] = $commonParent->getHash();
			$data[VerificationEntity::PREVIOUS_VERIFICATION_HASH] = $parent->getHash();
			$data[VerificationEntity::METADATA_HASH] = $this->verificationEngine->getHasher()->getHashSum(
				$entity->getDomainId() . $entity->getRevision()->getTimestamp() . $parent->getHash()
			);
		}
		wfDebug( "Setting verification data on moved revision: " . json_encode( $data ), 'da' );
		if ( !$db->update( 'revision_verification', $data, [ 'rev_id' => $entity->getRevision()->getId() ] ) ) {
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
	 * @return void
	 * @throws Exception
	 */
	private function assertMergedContentDifferent( VerificationEntity $lastLocal, string $text, array $slots ) {
		if ( $text === $lastLocal->getRevision()->getContent( SlotRecord::MAIN )->getText() && empty( $slots ) ) {
			throw new Exception( 'Merged content is the same as the last local revision. Nothing to merge' );
		}
	}
}
