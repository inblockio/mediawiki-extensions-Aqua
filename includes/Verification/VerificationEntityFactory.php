<?php

namespace DataAccounting\Verification;

use DateTime;
use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use stdClass;
use Title;
use TitleFactory;

/**
 * TODO: Logging
 * TODO: Caching
 */
class VerificationEntityFactory {
	/** @var TitleFactory */
	private $titleFactory;
	/** @var RevisionStore */
	private $revisionStore;

	private $hashTypes = [
		VerificationEntity::VERIFICATION_HASH, VerificationEntity::CONTENT_HASH,
		VerificationEntity::GENESIS_HASH, VerificationEntity::HASH_TYPE_METADATA,
		VerificationEntity::HASH_TYPE_SIGNATURE
	];

	/**
	 * VerificationEntityFactory constructor.
	 * @param TitleFactory $titleFactory
	 * @param RevisionStore $revisionStore
	 */
	public function __construct( TitleFactory $titleFactory, RevisionStore $revisionStore ) {
		$this->titleFactory = $titleFactory;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @param stdClass $row
	 * @return VerificationEntity|null
	 */
	public function newFromDbRow( stdClass $row ): ?VerificationEntity {
		$title = $this->titleFactory->newFromText( $row->page_title );
		if ( !( $title instanceof Title ) ) {
			return null;
		}
		$revision = $this->revisionStore->getRevisionById( $row->rev_id );
		if ( !( $revision instanceof RevisionRecord ) ) {
			return null;
		}
		$hashes = $this->extractHashes( $row );
		$time = DateTime::createFromFormat( 'YmdHis', $row->time_stamp );
		if ( !( $time instanceof DateTime ) ) {
			return null;
		}
		$verificationContext = ( $row->verification_context === '' ) ?
			[] : json_decode( $row->verification_context, 1 );

		return new VerificationEntity(
			$title, $revision, $row->domain_id, $hashes, $time, $verificationContext, $row->signature,
			$row->public_key, $row->wallet_address, (int)$row->witness_event_id
		);
	}

	/**
	 * @param stdClass $row
	 * @return array
	 */
	private function extractHashes( stdClass $row ) {
		$hashes = [];
		foreach ( $this->hashTypes as $hashType ) {
			$hashes[$hashType] = $row->$hashType ?? '';
		}

		return $hashes;
	}
}
