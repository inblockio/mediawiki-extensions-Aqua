<?php

namespace DataAccounting\Inbox;

use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use Status;
use Title;
use User;

class InboxImporter {

	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var TreeBuilder|null */
	private $treeBuilder = null;
	/** @var RevisionLookup */
	private $revisionLookup;

	/**
	 * @param VerificationEngine $verificationEngine
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct( VerificationEngine $verificationEngine, RevisionLookup $revisionLookup ) {
		$this->verificationEngine = $verificationEngine;
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * @param VerificationEntity $source
	 * @param Title $target
	 * @param User $actor
	 *
	 * @return Status
	 */
	public function importDirect( VerificationEntity $source, Title $target, User $actor ): Status {
		return $this->doMoveTitle( $source->getTitle(), $target, $actor, 'DataAccounting: Importing from inbox' );
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
	 * @param VerificationEntity $remote
	 * @param Title $target
	 * @param User $user
	 *
	 * @return Status
	 */
	public function importRemote( VerificationEntity $remote, Title $target, User $user ): Status {
		$status = $this->doDeleteTitle( $target, $user, 'DataAccounting: Merged from import' );
		$status->merge(
			$this->doMoveTitle( $remote->getTitle(), $target, $user, 'DataAccounting: Merged from import' )
		);

		return $status;
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
	 * @param string|null $comment
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
	 * @param Title $from
	 * @param Title $to
	 * @param User $user
	 * @param string|null $comment
	 *
	 * @return Status
	 *
	 */
	private function doMoveTitle( Title $from, Title $to, User $user, string $comment = '' ): Status {
		$movePage = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage(
			$from,
			$to
		);
		return $movePage->move( $user, $comment, false );
	}

}
