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
		VerificationEntity::HASH_TYPE_VERIFICATION, VerificationEntity::HASH_TYPE_CONTENT,
		VerificationEntity::HASH_TYPE_GENESIS, VerificationEntity::HASH_TYPE_METADATA,
		VerificationEntity::HASH_TYPE_SIGNATURE
	];

	public function __construct( TitleFactory $titleFactory, RevisionStore $revisionStore ) {
		$this->titleFactory = $titleFactory;
		$this->revisionStore = $revisionStore;
	}

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

		return new VerificationEntity(
			$title, $revision, $hashes, $time, $row->signature,
			$row->public_key, $row->wallet_address, (int)$row->witness_event_id
		);
	}

	private function extractHashes( stdClass $row ) {
		$hashes = [];
		foreach ( $this->hashTypes as $hashType ) {
			$hashes[$hashType] = $row->$hashType ?? '';
		}

		return $hashes;
	}
}
