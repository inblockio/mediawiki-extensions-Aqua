<?php

namespace DataAccounting\Verification;

use DataAccounting\Verification\Entity\VerificationEntity;
use DateTime;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
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
	/** @var array */
	private array $hashTypes = [
		VerificationEntity::VERIFICATION_HASH, VerificationEntity::CONTENT_HASH,
		VerificationEntity::GENESIS_HASH, VerificationEntity::METADATA_HASH,
		VerificationEntity::SIGNATURE_HASH, VerificationEntity::PREVIOUS_VERIFICATION_HASH,
		VerificationEntity::FORK_HASH, VerificationEntity::MERGE_HASH
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
		$revision = $this->revisionStore->getRevisionById( (int)$row->rev_id );
		if ( !( $revision instanceof RevisionRecord ) ) {
			return null;
		}
		$hashes = $this->extractHashesFromRow( $row );
		$time = DateTime::createFromFormat( 'YmdHis', $row->time_stamp );
		if ( !( $time instanceof DateTime ) ) {
			return null;
		}
		$verificationContext = $row->verification_context;
		if ( !$verificationContext ) {
			$verificationContext = [];
		}
		if ( is_string( $verificationContext ) ) {
			$verificationContext = json_decode( $verificationContext, 1 );
		}

		return new VerificationEntity(
			$title, $revision, $row->domain_id ?? '', $hashes, $time,
			$verificationContext, $row->signature, $row->public_key, $row->wallet_address,
			(int)$row->witness_event_id, $row->source ?? 'default'
		);
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	public function isValidHashType( string $type ): bool {
		return in_array( $type, $this->hashTypes );
	}

	/**
	 * @param stdClass $row
	 * @return array
	 */
	private function extractHashesFromRow( stdClass $row ) {
		$hashes = [];
		foreach ( $this->hashTypes as $hashType ) {
			$hashes[$hashType] = $row->$hashType ?? '';
		}

		return $hashes;
	}
}
