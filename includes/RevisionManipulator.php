<?php

namespace DataAccounting;

use CommentStoreComment;
use DataAccounting\Verification\VerificationEngine;
use Exception;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Message;
use Wikimedia\Rdbms\ILoadBalancer;

class RevisionManipulator {

	/** @var ILoadBalancer */
	private $lb;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var VerificationEngine */
	private $verificationEngine;

	/**
	 * @param ILoadBalancer $lb
	 * @param RevisionStore $revisionStore
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct(
		ILoadBalancer $lb, RevisionStore $revisionStore, VerificationEngine $verificationEngine
	) {
		$this->lb = $lb;
		$this->revisionStore = $revisionStore;
		$this->verificationEngine = $verificationEngine;
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


}