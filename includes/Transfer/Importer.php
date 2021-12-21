<?php

 namespace DataAccounting\Transfer;

 use DataAccounting\Verification\VerificationEngine;
 use MediaWiki\MediaWikiServices;
 use Title;
 use WikiRevision;

 class Importer {
 	/** @var VerificationEngine */
 	private VerificationEngine $verificationEngine;

	 /**
	  * @param VerificationEngine $verificationEngine
	  */
 	public function __construct( VerificationEngine $verificationEngine ) {
 		$this->verificationEngine = $verificationEngine;
	}

	public function importRevision( TransferRevisionEntity $revisionEntity, TransferContext $context ) {
 		$this->doImportRevision( $revisionEntity, $context );
		$this->buildVerification( $revisionEntity, $context );
	}

	 private function doImportRevision(
	 	TransferRevisionEntity $revisionEntity, TransferContext $context
	 ) {
		 $revision = new WikiRevision( new \HashConfig() );

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

		 if ( isset( $revisionEntity->getContent()['minor'] ) ) {
			 $revision->setMinor( true );
		 }

		 $revImporter = MediaWikiServices::getInstance()->getWikiRevisionOldRevisionImporter();
		 $revImporter->import( $revision );
	 }

	 private function buildVerification(
	 	TransferRevisionEntity $revisionEntity, TransferContext $context
	 ) {
		 $verificationEntity = $this->verificationEngine->getLookup()->verificationEntityFromTitle(
		 	$context->getTitle()
		 );
 		if ( !$verificationEntity ) {
 			throw new \MWException( "Import of verification data failed" );
		}
 		$this->verificationEngine->getLookup()->updateEntity( $verificationEntity, $this->compileVerificationData( $revisionEntity, $context ) );
		 /*$table = 'revision_verification';
		 $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		 $dbw = $lb->getConnectionRef( DB_PRIMARY );

		 if ( $verificationInfo !== null ) {
			 $verificationInfo['page_title'] = $title;
			 $verificationInfo['source'] = 'imported';
			 unset( $verificationInfo["rev_id"] );

			 $res = $dbw->select(
				 $table,
				 [ 'revision_verification_id', 'rev_id', 'page_title', 'source' ],
				 [ 'page_title' => $title ],
				 __METHOD__,
				 [ 'ORDER BY' => 'revision_verification_id' ]
			 );
			 $last_row = [];
			 foreach ( $res as $row ) {
				 $last_row = $row;
			 }
			 if ( empty( $last_row ) ) {
				 // Do nothing if empty
				 return;
			 }

			 // Witness-specific
			 // TODO move this to processWitness
			 if ( isset( $verificationInfo['witness'] ) ) {
				 $witnessInfo = $verificationInfo['witness'];
				 $structured_merkle_proof = json_decode( $witnessInfo['structured_merkle_proof'], true );
				 unset( $witnessInfo['structured_merkle_proof'] );

				 //Check if witness_event_verification_hash is already present,
				 //if so skip import into witness_events

				 $rowWitness = $dbw->selectRow(
					 'witness_events',
					 [ 'witness_event_id', 'witness_event_verification_hash' ],
					 [ 'witness_event_verification_hash' => $witnessInfo['witness_event_verification_hash'] ]
				 );
				 if ( !$rowWitness ) {
					 $witnessInfo['source'] = 'imported';
					 $witnessInfo['domain_manifest_title'] = 'N/A';
					 $dbw->insert(
						 'witness_events',
						 $witnessInfo,
					 );
					 $local_witness_event_id = getMaxWitnessEventId( $dbw );
					 if ( $local_witness_event_id === null ) {
						 $local_witness_event_id = 1;
					 }
				 } else {
					 $local_witness_event_id = $rowWitness->witness_event_id;
				 }

				 // Patch revision_verification table to use the local version of
				 // witness_event_id instead of from the foreign version.
				 $dbw->update(
					 'revision_verification',
					 [ 'witness_event_id' => $local_witness_event_id ],
					 [ 'revision_verification_id' => $last_row->revision_verification_id ],
				 );

				 // Check if merkle tree proof is present, if so skip, if not
				 // import AND attribute to the correct witness_id
				 $revision_verification_hash = $verificationInfo['verification_hash'];

				 $rowProof = $dbw->selectRow(
					 'witness_merkle_tree',
					 [ 'witness_event_id' ],
					 [
						 'left_leaf=\'' . $revision_verification_hash . '\'' .
						 ' OR right_leaf=\'' . $revision_verification_hash . '\''
					 ]
				 );

				 if ( !$rowProof ) {
					 $latest_witness_event_id = $dbw->selectRow(
						 'witness_events',
						 [ 'max(witness_event_id) as witness_event_id' ],
						 ''
					 )->witness_event_id;

					 foreach ( $structured_merkle_proof as $row ) {
						 $row["witness_event_id"] = $latest_witness_event_id;
						 $dbw->insert(
							 'witness_merkle_tree',
							 $row,
						 );
					 }
				 }

				 // This unset is important, otherwise the dbw->update for
				 // revision_verification accidentally includes witness.
				 unset( $verificationInfo["witness"] );
			 }
			 // End of witness-specific

			 $dbw->update(
				 $table,
				 $verificationInfo,
				 [ 'revision_verification_id' => $last_row->revision_verification_id ],
				 __METHOD__
			 );
		 } else {
			 $dbw->delete(
				 $table,
				 [ 'page_title' => $title ]
			 );
		 }*/
	 }

	 private function getContents( TransferRevisionEntity $entity, Title $title ) {
 		$slotRoleRegistry = MediaWikiServices::getInstance()->getSlotRoleRegistry();
 		$contents = [];
 		foreach ( $entity->getContent()['content'] as $role => $text ) {
 			if ( !$slotRoleRegistry->isDefinedRole( $role ) ) {
 				throw new \MWException( "Required role \"$role\" is not defined" );
			}
 			$model = $slotRoleRegistry->getRoleHandler( $role )->getDefaultModel( $title );
 			$content = MediaWikiServices::getInstance()->getContentHandlerFactory()
				->getContentHandler( $model )->unserializeContent( $text );
 			$contents[$role] = $content;
		}

 		return $contents;
	 }

	 private function compileVerificationData( TransferRevisionEntity $revisionEntity, TransferContext $context ) {

	 }
 }
