<?php

 namespace DataAccounting\Transfer;

 use DataAccounting\Verification\VerificationEngine;
 use DataAccounting\Verification\Entity\VerificationEntity;
 use DataAccounting\Verification\WitnessingEngine;
 use DeferredUpdates;
 use HashConfig;
 use MediaWiki\Content\IContentHandlerFactory;
 use MediaWiki\MediaWikiServices;
 use MediaWiki\Page\DeletePageFactory;
 use MediaWiki\Page\MovePageFactory;
 use MediaWiki\Revision\RevisionRecord;
 use MediaWiki\Revision\RevisionStore;
 use Message;
 use MWContentSerializationException;
 use MWException;
 use MWUnknownContentModelException;
 use OldRevisionImporter;
 use RepoGroup;
 use Status;
 use StatusValue;
 use Title;
 use TitleFactory;
 use UploadRevisionImporter;
 use User;
 use WikiRevision;

 class Importer {
	public const COLLISION_AVOIDANCE_STRATEGY_DELETE_SHORTER = 'delete-shorter';
	public const COLLISION_AVOIDANCE_STRATEGY_MOVE_SHORTER = 'move-shorter';

	/** @var VerificationEngine */
	private VerificationEngine $verificationEngine;
	/** @var WitnessingEngine */
	private WitnessingEngine $witnessingEngine;
	/** @var OldRevisionImporter */
	private OldRevisionImporter $revisionImporter;
	/** @var UploadRevisionImporter */
	private UploadRevisionImporter $uploadRevisionImporter;
	/** @var IContentHandlerFactory */
	private IContentHandlerFactory $contentHandlerFactory;
	/** @var RevisionStore */
	private RevisionStore $revisionStore;
	/** @var RepoGroup */
	private RepoGroup $repoGroup;
	/** @var TitleFactory */
	private TitleFactory $titleFactory;
	/** @var MovePageFactory */
	private MovePageFactory $movePageFactory;
	private DeletePageFactory $deletePageFactory;

	 /**
	  * @param VerificationEngine $verificationEngine
	  * @param WitnessingEngine $witnessingEngine
	  * @param OldRevisionImporter $revisionImporter
	  * @param UploadRevisionImporter $uploadRevisionImporter
	  * @param IContentHandlerFactory $contentHandlerFactory
	  * @param RevisionStore $revisionStore
	  * @param RepoGroup $repoGroup
	  * @param TitleFactory $titleFactory
	  * @param MovePageFactory $movePageFactory
	  * @param DeletePageFactory $deletePageFactory
	  */
	public function __construct(
		VerificationEngine $verificationEngine, WitnessingEngine $witnessingEngine,
		OldRevisionImporter $revisionImporter, UploadRevisionImporter $uploadRevisionImporter,
		IContentHandlerFactory $contentHandlerFactory, RevisionStore $revisionStore,
		RepoGroup $repoGroup, TitleFactory $titleFactory, MovePageFactory $movePageFactory,
		DeletePageFactory $deletePageFactory
	) {
		$this->verificationEngine = $verificationEngine;
		$this->witnessingEngine = $witnessingEngine;
		$this->revisionImporter = $revisionImporter;
		$this->uploadRevisionImporter = $uploadRevisionImporter;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->revisionStore = $revisionStore;
		$this->repoGroup = $repoGroup;
		$this->titleFactory = $titleFactory;
		$this->movePageFactory = $movePageFactory;
		$this->deletePageFactory = $deletePageFactory;
	}

	 /**
	  * @param TransferRevisionEntity $revisionEntity
	  * @param TransferContext $context Contains result of `get_hash_chain_info`
	  * @param User $actor
	  * @return Status
	  * @throws MWContentSerializationException
	  * @throws MWException
	  * @throws MWUnknownContentModelException
	  */
	public function importRevision(
		TransferRevisionEntity $revisionEntity, TransferContext $context, User $actor
	): Status {
		if ( isset( $revisionEntity->getContent()['file'] ) ) {
			$status = $this->doImportUpload( $revisionEntity, $context );
		} else {
			$status = $this->doImportRevision( $revisionEntity, $context );
		}

		return $status;
	}

	 /**
	  * @param TransferRevisionEntity $revisionEntity
	  * @param TransferContext $context
	  * @return Status
	  * @throws MWException
	  * @throws MWUnknownContentModelException
	  * @throws MWContentSerializationException
	  */
	 private function doImportRevision(
		TransferRevisionEntity $revisionEntity, TransferContext $context
	 ): Status {
		 $revision = $this->prepRevision( $revisionEntity, $context );

		 if ( isset( $revisionEntity->getContent()['minor'] ) ) {
			 $revision->setMinor( true );
		 }

		 $this->revisionImporter->import( $revision );

		 $status = $this->newRevisionStatus( $revision );
		 if ( $status->isOK() ) {
			 return $this->buildVerification( $revisionEntity, $context );
		 }

		 return $status;
	 }

	 /**
	  * @param TransferRevisionEntity $revisionEntity
	  * @param TransferContext $context
	  * @throws MWContentSerializationException
	  * @throws MWException
	  * @throws MWUnknownContentModelException
	  * @return Status
	  */
	 private function doImportUpload(
		TransferRevisionEntity $revisionEntity, TransferContext $context
	 ): StatusValue {
		 $revision = $this->prepRevision( $revisionEntity, $context );
		 $fileInfo = $revisionEntity->getContent()['file'];
		 $revision->setFilename( $fileInfo['filename'] );
		 if ( isset( $fileInfo['archivename'] ) ) {
			 $revision->setArchiveName( $fileInfo['archivename'] );
		 }

		 $tempDir = wfTempDir();
		 $tempName = $fileInfo['filename'];
		 if ( isset( $fileInfo['archivename'] ) ) {
			 $tempName = $fileInfo['archivename'];
		 }
		 $file = $tempDir . '/' . $tempName;
		 if ( !is_writable( $tempDir ) ) {
			throw new MWException( 'Cannot write to temp file to ' . $tempDir );
		 }
		 file_put_contents( $file, base64_decode( $fileInfo['data'] ) );
		 $revision->setFileSrc( $file, true );
		 $revision->setSize( intval( $fileInfo['size'] ) );
		 $revision->setComment( $fileInfo['comment'] );
		 $this->uploadRevisionImporter->setNullRevisionCreation( false );

		 $status = $this->uploadRevisionImporter->import( $revision );
		 if ( !$status->isOK() ) {
			return $status;
		 }
		 $dbw = $this->repoGroup->getRepo( 'local' )->getPrimaryDB();
		 $importer = $this;
		 $verificationUpdate = new \AutoCommitUpdate(
			 $dbw,
			 __METHOD__,
			 static function() use ( $revisionEntity, $context, $importer ) {
				 $importer->buildVerification( $revisionEntity, $context );
			 }
		 );

		 $dbw->onTransactionCommitOrIdle(
			// Revision for the file page will only be created in a deferred update,
			// and the update itself will only be added on DB tx commit,
			// so we need to hook into the same DB connection, listen to tx commit and run updates
			function () use ( $verificationUpdate ) {
				// Downside is that we dont have any checks here, if this fails,
				// noone will know, as this happens after our code has already completed
				DeferredUpdates::addUpdate( $verificationUpdate, DeferredUpdates::PRESEND );
			},
			 __METHOD__
		 );

		 return $this->doImportRevision( $revisionEntity, $context );
	 }

	 /**
	  * @param TransferRevisionEntity $revisionEntity
	  * @param TransferContext $context
	  * @return WikiRevision
	  * @throws MWContentSerializationException
	  * @throws MWException
	  * @throws MWUnknownContentModelException
	  */
	 private function prepRevision(
		TransferRevisionEntity $revisionEntity, TransferContext $context
	 ): WikiRevision {
		 $revision = new WikiRevision( new HashConfig() );

		 $revId = $revisionEntity->getContent()['rev_id'] ?? 0;
		 if ( $revId ) {
			 $revision->setID( $revId );
		 }

		 $revision->setTitle( $context->getTitle() );

		 $contents = $this->getContents( $revisionEntity, $context->getTitle() );
		 foreach ( $contents as $role => $content ) {
			 $revision->setContent( $role, $content );
		 }

		 $revision->setTimestamp(
			 $revisionEntity->getMetadata()['time_stamp'] ?? \MWTimestamp::now( TS_MW )
		 );
		 if ( isset( $revisionEntity->getContent()['comment'] ) ) {
			 $revision->setComment( $revisionEntity->getContent()['comment'] );
		 }
		 // TODO: This needs fixing. Question is which user will be attributed with edit.
		 // If its actual user who created the revision in the first place, that info needs to
		 // be passed in `get_revision` and that user must exist in target wiki
		 // If its some "admin" user on the target, that username needs to be defined somewhere
		 // For the moment, take first user created on wiki (which is admin)
		 $user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( 1 );
		 $revision->setUserObj( $user );

		 return $revision;
	 }

	 /**
	  * @param TransferRevisionEntity $transferEntity
	  * @param TransferContext $context
	  * @return Status
	  */
	 public function buildVerification(
		TransferRevisionEntity $transferEntity, TransferContext $context
	 ) {
		$verificationEntity = $this->verificationEngine->getLookup()->verificationEntityFromTitle(
			$context->getTitle()
		);
		if ( !$verificationEntity ) {
			// Do nothing if entry does not exist => TODO: Why?
			return Status::newGood();
		}

		$witness = $transferEntity->getWitness();
		if ( $witness !== null ) {
			$this->processWitness( $transferEntity, $verificationEntity );
		}

		$res = $this->verificationEngine->getLookup()->updateEntity(
			$verificationEntity, $this->compileVerificationData( $transferEntity, $context )
		);
		if ( !$res ) {
			return Status::newFatal( "Could not store verification data" );
		}

		return Status::newGood();
	 }

	 /**
	  * @param TransferRevisionEntity $transferRevisionEntity
	  * @param VerificationEntity $verificationEntity
	  * @throws MWException
	  */
	 private function processWitness(
		TransferRevisionEntity $transferRevisionEntity,
		VerificationEntity $verificationEntity
	 ) {
		$witnessInfo = $transferRevisionEntity->getWitness();
		$structuredMerkleProof = $witnessInfo['structured_merkle_proof'];
		// This is important because insertWitnessEvent expects $witnessInfo to
		// not contain structured_merkle_proof.
		unset( $witnessInfo['structured_merkle_proof'] );

		$witnessEntity = $this->witnessingEngine->getLookup()->witnessEventFromQuery( [
			'witness_event_verification_hash' => $witnessInfo['witness_event_verification_hash']
		] );

		if ( !$witnessEntity ) {
			// If the witness event with the said
			// witness_event_verification_hash doesn't exist, we create it.
			// TODO Otherwise, we should skip it instead of updating it. But
			// this is just a minor perf tweak.
			$witnessInfo['source'] = 'imported';
			$witnessInfo['domain_snapshot_title'] = 'N/A';
			// We have to unset the witness_event_id, because there could be
			// collision with the existing local witness_event_id's.
			unset( $witnessInfo["witness_event_id"] );
			$localWitnessEventId = $this->witnessingEngine->getLookup()->insertWitnessEvent(
				$witnessInfo
			);
			if ( !$localWitnessEventId ) {
				// Not expected to happen in practice
				throw new MWException( 'Cannot insert witness event' );
			}
		} else {
			$localWitnessEventId = $witnessEntity->get( 'witness_event_id' );
		}

		// Patch revision_verification table to use the local version of
		// witness_event_id instead of from the foreign version.
		$res = $this->verificationEngine->getLookup()->updateEntity( $verificationEntity, [
			'witness_event_id' => $localWitnessEventId,
		] );
		if ( !$res ) {
			throw new MWException( "Cannot store witness ID to verification entity" );
		}

		$revisionVerificationHash = $transferRevisionEntity->getMetadata()['verification_hash'] ?? null;
		$proofEntity = $this->witnessingEngine->getLookup()->merkleTreeFromQuery( [
			'left_leaf=\'' . $revisionVerificationHash . '\'' .
			' OR right_leaf=\'' . $revisionVerificationHash . '\''
		] );

		if ( !$proofEntity ) {
			// TODO: Why latest? doesnt this apply only if new "witness_event" was inserted?
			$lastWitnessEventId = $this->witnessingEngine->getLookup()->getLastWitnessEventId();

			foreach ( $structuredMerkleProof as $row ) {
				$row["witness_event_id"] = $lastWitnessEventId;
				$this->witnessingEngine->getLookup()->insertMerkleTreeNode( $row );
			}
		}
	 }

	 /**
	  * @param TransferRevisionEntity $entity
	  * @param Title $title
	  * @return array
	  * @throws MWException
	  * @throws MWContentSerializationException
	  * @throws MWUnknownContentModelException
	  */
	 private function getContents( TransferRevisionEntity $entity, Title $title ) {
		$slotRoleRegistry = MediaWikiServices::getInstance()->getSlotRoleRegistry();
		$contents = [];
		foreach ( $entity->getContent()['content'] as $role => $text ) {
			if ( !$slotRoleRegistry->isDefinedRole( $role ) ) {
				throw new MWException( "Required role \"$role\" is not defined" );
			}
			$model = $slotRoleRegistry->getRoleHandler( $role )->getDefaultModel( $title );
			$content = $this->contentHandlerFactory->getContentHandler( $model )->unserializeContent( $text );
			$contents[$role] = $content;
		}

		return $contents;
	 }

	 /**
	  * see comment on static::buildVerification
	  *
	  * @param TransferRevisionEntity $revisionEntity
	  * @param TransferContext $context
	  * @return array
	  */
	 private function compileVerificationData(
		TransferRevisionEntity $revisionEntity, TransferContext $context
	 ): array {
		 $revisionSignature = $revisionEntity->getSignature() ?? [];
		 $signatureData = [
			 'signature' => $revisionSignature['signature'] ?? '',
			 'signature_hash' => $revisionSignature['signature_hash'] ?? '',
			 'public_key' => $revisionSignature['public_key'] ?? '',
			 'wallet_address' => $revisionSignature['wallet_address'] ?? '',
		 ];
		return array_merge( [
			'domain_id' => $revisionEntity->getMetadata()['domain_id'],
			'genesis_hash' => $context->getGenesisHash(),
			'verification_hash' => $revisionEntity->getMetadata()['verification_hash'],
			'time_stamp' => $revisionEntity->getMetadata()['time_stamp'],
			'verification_context' => json_encode( $revisionEntity->getVerificationContext() ),
			'previous_verification_hash' => $revisionEntity->getMetadata()['previous_verification_hash'],
			'content_hash' => $revisionEntity->getContent()['content_hash'],
			'metadata_hash' => $revisionEntity->getMetadata()['metadata_hash'],
			'source' => 'import'
		], $signatureData );
	 }

	 private function newRevisionStatus( WikiRevision $revision ) {
		 $newRevision = $this->revisionStore->getRevisionByTimestamp(
			 $revision->getTitle(), $revision->getTimestamp()
		 );

		 if ( $newRevision instanceof RevisionRecord ) {
			 return Status::newGood();
		 }

		return Status::newFatal(
			Message::newFromKey( 'da-import-fail-revision' )->params( $revision->getTitle(), $revision->getID() )
		);
	 }

	 /**
	  * TODO: Separate this out to a new mechanism
	  * @param User $actor
	  * @param TransferContext $context
	  * @param string|null $strategy
	  * @return Status
	  */
	 public function checkAndFixCollision(
		User $actor, TransferContext $context, $strategy = self::COLLISION_AVOIDANCE_STRATEGY_DELETE_SHORTER
	 ): Status {
		if ( !$context->getTitle()->exists() ) {
			return Status::newGood();
		}

		$ownChainHeight = $this->verificationEngine->getPageChainHeight( $context->getTitle() );
		if ( $ownChainHeight === 0 ) {
			return Status::newGood();
		}

		switch ( $strategy ) {
			case static::COLLISION_AVOIDANCE_STRATEGY_DELETE_SHORTER:
				if ( $ownChainHeight < $context->getChainHeight() ) {
					return $this->collisionDelete( $context->getTitle(), $actor );
				}
				break;
			case static::COLLISION_AVOIDANCE_STRATEGY_MOVE_SHORTER:
				if ( $ownChainHeight < $context->getChainHeight() ) {
					return $this->collisionMove( $context->getTitle(), $ownChainHeight, $actor );
				}
				break;
		}

		 return Status::newGood( [
			 'collision' => Message::newFromKey(
				 'da-import-collision-skip'
			 )->params(
				 $context->getTitle()->getPrefixedDBkey()
			 )->parse(),
			 'skip' => true
		 ] );
	 }

	 /**
	  * @param Title $title
	  * @param User $actor
	  * @return Status
	  */
	 private function collisionDelete( Title $title, User $actor ): Status {
		$deletePage = $this->deletePageFactory->newDeletePage(
			$title->toPageIdentity(), $actor
		);

		$status = $deletePage->deleteUnsafe(
			Message::newFromKey( 'da-import-collision-delete-reason' )->text()
		);
		if ( !$status->isOK() ) {
			return $status;
		}

		 return Status::newGood( [
			 'collision' => Message::newFromKey(
				 'da-import-collision-delete'
			 )->params(
				 $title->getPrefixedDBkey()
			 )->parse()
		 ] );
	 }

	 /**
	  * @param Title $title
	  * @param int $localChainHeight
	  * @param User $actor
	  * @return Status
	  */
	 private function collisionMove( Title $title, $localChainHeight, User $actor ): Status {
		 // Move/rename the existing page on MW, and let the page that is
		 // about to be imported has the original title instead.
		 $now = date( 'Y-m-d-H-i-s', time() );
		 $newTitle = $title->getPrefixedDBkey() . "_Branch_ChainHeight_{$localChainHeight}_$now";
		 $newTitle = $this->titleFactory->newFromText( $newTitle );
		 $mp = $this->movePageFactory->newMovePage( $title, $newTitle );
		 $reason = Message::newFromKey( 'da-import-collision-move-reason' )->text();
		 $createRedirect = false;

		 $status = $mp->moveIfAllowed(
			 $actor,
			 $reason,
			 $createRedirect
		 );
		 if ( !$status->isOK() ) {
			 return $status;
		 }
		 return Status::newGood( [
			 'collision' => Message::newFromKey(
				 'da-import-collision-move'
			 )->params(
				 $title->getPrefixedDBkey(),
				 $newTitle->getPrefixedDBkey()
			 )->parse()
		 ] );
	 }
 }
