<?php

 namespace DataAccounting\Transfer;

 use DataAccounting\Verification\VerificationEngine;
 use DataAccounting\Verification\Entity\VerificationEntity;
 use DataAccounting\Verification\WitnessingEngine;
 use HashConfig;
 use MediaWiki\Content\IContentHandlerFactory;
 use MediaWiki\MediaWikiServices;
 use MediaWiki\Storage\RevisionRecord;
 use MediaWiki\Storage\RevisionStore;
 use MWContentSerializationException;
 use MWException;
 use MWUnknownContentModelException;
 use OldRevisionImporter;
 use Title;
 use UploadRevisionImporter;
 use WikiRevision;

 class Importer {
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

	 /**
	  * @param VerificationEngine $verificationEngine
	  * @param WitnessingEngine $witnessingEngine
	  * @param OldRevisionImporter $revisionImporter
	  * @param UploadRevisionImporter $uploadRevisionImporter
	  * @param IContentHandlerFactory $contentHandlerFactory
	  * @param RevisionStore $revisionStore
	  */
	public function __construct(
		VerificationEngine $verificationEngine, WitnessingEngine $witnessingEngine,
		OldRevisionImporter $revisionImporter, UploadRevisionImporter $uploadRevisionImporter,
		IContentHandlerFactory $contentHandlerFactory, RevisionStore $revisionStore
	) {
		$this->verificationEngine = $verificationEngine;
		$this->witnessingEngine = $witnessingEngine;
		$this->revisionImporter = $revisionImporter;
		$this->uploadRevisionImporter = $uploadRevisionImporter;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->revisionStore = $revisionStore;
	}

	 /**
	  * @param TransferRevisionEntity $revisionEntity
	  * @param TransferContext $context Contains result of `get_hash_chain_info`
	  * @throws MWException
	  */
	public function importRevision(
		TransferRevisionEntity $revisionEntity, TransferContext $context
	) {
		if ( isset( $revisionEntity->getContent()['file'] ) ) {
			$revision = $this->doImportUpload( $revisionEntity, $context );
		} else {
			$revision = $this->doImportRevision( $revisionEntity, $context );
		}

		if ( !$revision ) {
			throw new MWException( 'Could not import revision' );
		}
		$this->buildVerification( $revisionEntity, $context );
	}

	 /**
	  * @param TransferRevisionEntity $revisionEntity
	  * @param TransferContext $context
	  * @throws MWContentSerializationException
	  * @throws MWException
	  * @throws MWUnknownContentModelException
	  * @return RevisionRecord|null
	  */
	 private function doImportRevision(
		TransferRevisionEntity $revisionEntity, TransferContext $context
	 ): ?RevisionRecord {
		 $revision = $this->prepRevision( $revisionEntity, $context );

		 if ( isset( $revisionEntity->getContent()['minor'] ) ) {
			 $revision->setMinor( true );
		 }

		 $this->revisionImporter->import( $revision );
		 return $this->revisionStore->getRevisionByTimestamp(
			 $revision->getTitle(), $revision->getTimestamp()
		 );
	 }

	 /**
	  * @param TransferRevisionEntity $revisionEntity
	  * @param TransferContext $context
	  * @throws MWContentSerializationException
	  * @throws MWException
	  * @throws MWUnknownContentModelException
	  * @return RevisionRecord|null
	  */
	 private function doImportUpload(
	 	TransferRevisionEntity $revisionEntity, TransferContext $context
	 ): ?RevisionRecord {
		 $revision = $this->prepRevision( $revisionEntity, $context );
		 $fileInfo = $revisionEntity->getContent()['file'];
		 $revision->setFilename( $fileInfo['filename'] );
		 if ( isset( $fileInfo['archivename'] ) ) {
			 $revision->setArchiveName( $fileInfo['archivename'] );
		 }

		 $tempDir = wfTempDir();
		 $file = $tempDir . '/' . $fileInfo['filename'];
		 if ( !is_writable( $tempDir ) ) {
			throw new MWException( 'Cannot write to temp file to ' . $tempDir );
		 }
		 file_put_contents( $file, base64_decode( $fileInfo['data'] ) );
		 $revision->setFileSrc( $file, true );
		 $revision->setSize( intval( $fileInfo['size'] ) );
		 $revision->setComment( $fileInfo['comment'] );

		 $status = $this->uploadRevisionImporter->import( $revision );
		 if ( !$status->isOK() ) {
		 	$errors = implode( ',', $status->getErrors() );
			throw new MWException( 'Could not upload file: ' . $errors );
		 }

		 return $this->revisionStore->getRevisionByTimestamp(
			 $revision->getTitle(), $revision->getTimestamp()
		 );
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
	  * @throws MWException
	  */
	 private function buildVerification(
		TransferRevisionEntity $transferEntity, TransferContext $context
	 ) {
		$verificationEntity = $this->verificationEngine->getLookup()->verificationEntityFromTitle(
			$context->getTitle()
		);
		if ( !$verificationEntity ) {
			// Do nothing if entry does not exist => TODO: Why?
			return;
		}

		$witness = $transferEntity->getWitness();
		if ( $witness !== null ) {
			$this->processWitness( $transferEntity, $verificationEntity );
		}

		$res = $this->verificationEngine->getLookup()->updateEntity(
			$verificationEntity, $this->compileVerificationData( $transferEntity, $context )
		);
		if ( !$res ) {
			throw new MWException( 'Failed to store verification' );
		}
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
		$structuredMerkleProof = json_decode( $witnessInfo['structured_merkle_proof'], true );
		unset( $witnessInfo['structured_merkle_proof'] );

		$witnessEntity = $this->witnessingEngine->getLookup()->witnessEventFromQuery( [
			'witness_event_verification_hash' => $witnessInfo['witness_event_verification_hash']
		] );

		if ( !$witnessEntity ) {
			$witnessInfo['source'] = 'imported';
			$witnessInfo['domain_snapshot_title'] = 'N/A';
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
				$this->witnessingEngine->getLookup()->insertMerkleTree( $row );
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
		return [
			'domain_id' => $revisionEntity->getMetadata()['domain_id'],
			'genesis_hash' => $context->getGenesisHash(),
			'verification_hash' => $revisionEntity->getMetadata()['verification_hash'],
			'time_stamp' => $revisionEntity->getMetadata()['time_stamp'],
			'verification_context' => json_encode( $revisionEntity->getVerificationContext() ),
			'previous_verification_hash' => $revisionEntity->getMetadata()['previous_verification_hash'],
			'content_hash' => $revisionEntity->getContent()['content_hash'],
			'metadata_hash' => $revisionEntity->getMetadata()['metadata_hash'],
			'signature' => $revisionEntity->getSignature()['signature'],
			'signature_hash' => $revisionEntity->getSignature()['signature_hash'],
			'public_key' => $revisionEntity->getSignature()['public_key'],
			'wallet_address' => $revisionEntity->getSignature()['wallet_address'],
			'source' => 'import'
		];
	 }
 }
