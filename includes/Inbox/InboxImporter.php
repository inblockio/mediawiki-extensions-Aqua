<?php

namespace DataAccounting\Inbox;

use DataAccounting\RevisionManipulator;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MWException;
use Status;
use Throwable;
use Title;
use User;

class InboxImporter {

	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var TreeBuilder|null */
	private $treeBuilder = null;
	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var RevisionManipulator */
	private $revisionManipulator;

	/**
	 * @param VerificationEngine $verificationEngine
	 * @param RevisionLookup $revisionLookup
	 * @param RevisionManipulator $revisionManipulator
	 */
	public function __construct(
		VerificationEngine $verificationEngine, RevisionLookup $revisionLookup, RevisionManipulator $revisionManipulator
	) {
		$this->verificationEngine = $verificationEngine;
		$this->revisionLookup = $revisionLookup;
		$this->revisionManipulator = $revisionManipulator;
	}

	/**
	 * @param VerificationEntity $source
	 * @param Title $target
	 * @param User $actor
	 *
	 * @return Status
	 */
	public function importDirect( VerificationEntity $source, Title $target, User $actor ): Status {
		try {
			$this->revisionManipulator->movePage( $source->getTitle(), $target );
			return Status::newGood();
		} catch ( Throwable $ex ) {
			return Status::newFatal( $ex->getMessage() );
		}
	}

	/**
	 * @param Title $subjectTitle
	 * @param User $user
	 *
	 * @return Status
	 */
	public function discard( Title $subjectTitle, User $user ): Status {
		return $this->doDeleteTitle( $subjectTitle, $user, 'Discarded' );
	}

	/**
	 * @return TreeBuilder
	 */
	public function getTreeBuilder(): TreeBuilder {
		if ( $this->treeBuilder === null ) {
			$this->treeBuilder = new TreeBuilder( $this->verificationEngine, $this->revisionLookup );
		}
		return $this->treeBuilder;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $comment
	 *
	 * @return Status
	 */
	private function doDeleteTitle( Title $title, User $user, string $comment = '' ) {
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$title->toPageIdentity(),
			$user
		);
		$deletePage->setSuppress( true );

		return $deletePage->deleteUnsafe( $comment );
	}

	/**
	 * @param Title $local
	 * @param Title $remoteTitle
	 * @param User $user
	 * @param string|null $text
	 * @return void
	 * @throws MWException
	 */
	public function mergePages( Title $local, Title $remoteTitle, User $user, ?string $text ) {
		$this->revisionManipulator->mergePages( $local, $remoteTitle, $user, $text );
	}

	/**
	 * @param Title $local
	 * @param Title $remoteTitle
	 * @param User $user
	 * @return void
	 * @throws MWException
	 */
	public function mergePagesForceRemote( Title $local, Title $remoteTitle, User $user ) {
		// Merge remote into local, but force the latest text of remote as new content
		$this->revisionManipulator->moveChain( $local, $remoteTitle );
	}
}
