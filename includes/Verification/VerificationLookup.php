<?php

namespace DataAccounting\Verification;

use MediaWiki\Storage\RevisionStore;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

class VerificationLookup {
	private const TABLE = 'revision_verification';

	/** @var ILoadBalancer */
	private $lb;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var VerificationEntityFactory */
	private $verificationEntityFactory;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param RevisionStore $revisionStore
	 * @param VerificationEntityFactory $entityFactory
	 */
	public function __construct(
		ILoadBalancer $loadBalancer, RevisionStore $revisionStore, VerificationEntityFactory $entityFactory
	) {
		$this->lb = $loadBalancer;
		$this->revisionStore = $revisionStore;
		$this->verificationEntityFactory = $entityFactory;
	}

	/**
	 * Gets the verification entity from verification hash
	 *
	 * @param string $hash Verification hash
	 * @return VerificationEntity|null
	 */
	public function verificationEntityFromHash( string $hash ): ?VerificationEntity {
		return $this->getVerificationEntityFromQuery( [ VerificationEntity::HASH_TYPE_VERIFICATION => $hash ] );
	}

	/**
	 * @param int $revId
	 * @return VerificationEntity|null
	 */
	public function getVerificationEntityFromRevId( int $revId ): ?VerificationEntity {
		return $this->getVerificationEntityFromQuery( [ 'rev_id' => $revId ] );
	}

	/**
	 * Gets the latest verification entry for the given title
	 *
	 * @param Title $title
	 * @return VerificationEntity|null
	 */
	public function verificationEntityFromTitle( Title $title ): ?VerificationEntity {
		if ( !$title->exists() ) {
			return null;
		}
		return $this->getVerificationEntityFromQuery( [ 'page_title' => $title->getPrefixedDBkey() ] );
	}

	public function getVerificationEntityFromQuery( array $query ) {
		$res = $this->lb->getConnection( DB_REPLICA )->selectRow(
			static::TABLE,
			[ '*' ],
			$query,
			__METHOD__
		);

		if ( !$res ) {
			return null;
		}

		return $this->verificationEntityFactory->newFromDbRow( $res );
	}
}
