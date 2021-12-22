<?php

namespace DataAccounting\Verification;

use DataAccounting\Verification\Entity\VerificationEntity;
use Exception;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
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
		return $this->verificationEntityFromQuery( [ VerificationEntity::VERIFICATION_HASH => $hash ] );
	}

	/**
	 * @param int $revId
	 * @return VerificationEntity|null
	 */
	public function verificationEntityFromRevId( int $revId ): ?VerificationEntity {
		return $this->verificationEntityFromQuery( [ 'rev_id' => $revId ] );
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
		// TODO: Replace with getPrefixedDBkey, once database enties use it
		return $this->verificationEntityFromQuery( [ 'page_title' => $title->getPrefixedText() ] );
	}

	/**
	 * Get VerificationEntity based on a custom query
	 * Caller must ensure query resolves to a single entity,
	 * otherwise the latest of the set will be retrieved
	 *
	 * @param array $query
	 * @return VerificationEntity|null
	 */
	public function verificationEntityFromQuery( array $query ): ?VerificationEntity {
		$res = $this->lb->getConnection( DB_REPLICA )->selectRow(
			static::TABLE,
			[ '*' ],
			$query,
			__METHOD__,
			[ 'ORDER BY' => [ 'rev_id DESC' ] ],
		);

		if ( !$res ) {
			return null;
		}

		return $this->verificationEntityFactory->newFromDbRow( $res );
	}

	/**
	 * @param string|Title $title
	 * @return array
	 */
	public function getAllRevisionIds( $title ): array {
		if ( $title instanceof Title ) {
			// TODO: Replace with getPrefixedDBkey, once database enties use it
			$title = $title->getPrefixedText();
		}
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			'revision_verification',
			[ 'rev_id' ],
			[ 'page_title' => $title ],
			__METHOD__,
			[ 'ORDER BY' => 'rev_id' ]
		);

		$output = [];
		foreach ( $res as $row ) {
			$output[] = (int)$row->rev_id;
		}
		return $output;
	}

	/**
	 * Get all hashes that are same or newer than given entity
	 *
	 * @param VerificationEntity $verificationEntity
	 * @param string $type
	 * @return array
	 * @throws Exception
	 */
	public function newerHashesForEntity( VerificationEntity $verificationEntity, string $type ) {
		$this->assertVerificationHashValid( $type );
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			static::TABLE,
			[ $type ],
			[
				'rev_id >= ' . $verificationEntity->getRevision()->getId(),
				VerificationEntity::GENESIS_HASH =>
					$verificationEntity->getHash( VerificationEntity::GENESIS_HASH ),
			],
			__METHOD__,
			[ 'ORDER BY' => 'rev_id' ]
		);

		$hashes = [];
		foreach ( $res as $row ) {
			$hashes[] = $row->$type;
		}

		return $hashes;
	}

	/**
	 * @param string $type
	 * @throws Exception
	 */
	private function assertVerificationHashValid( string $type ) {
		if ( !$this->verificationEntityFactory->isValidHashType( $type ) ) {
			throw new Exception( "Hash type \"$type\" is not valid" );
		}
	}

	/**
	 * @param VerificationEntity $entity
	 * @param array $data
	 * @return bool
	 */
	public function updateEntity( VerificationEntity $entity, array $data ): bool {
		return $this->lb->getConnection( DB_PRIMARY )->update(
			static::TABLE,
			$data,
			[ 'rev_id' => $entity->getRevision()->getId() ],
			__METHOD__
		);
	}

	/**
	 * @return array
	 */
	public function getAllEntities(): array {
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			static::TABLE,
			[ '*' ],
			[],
			__METHOD__
		);

		$entities = [];
		foreach ( $res as $row ) {
			$entity = $this->verificationEntityFactory->newFromDbRow( $row );
			if ( $entity instanceof VerificationEntity ) {
				$entities[] = $entity;
			}
		}

		return $entities;
	}

	/**
	 * Insert "seed" data for the verification entry.
	 * This is done to "reserve" place for the verification data
	 *
	 * @param RevisionRecord $revisionRecord
	 * @return int|null Id of inserted record or null on failure
	 */
	public function insertShellData( RevisionRecord $revisionRecord ): ?int {
		$db = $this->lb->getConnection( DB_PRIMARY );
		// TODO: inject, although it should not even be necessary, table should use NS and dbkey instead
		$titleFactory = \MediaWiki\MediaWikiServices::getInstance()->getTitleFactory();
		$title = $titleFactory->makeTitle(
			$revisionRecord->getPage()->getNamespace(), $revisionRecord->getPage()->getDBkey()
		);
		if ( !$title ) {
			return null;
		}
		$res = $db->insert(
			static::TABLE,
			[
				'page_title' => $title->getPrefixedText(),
				'page_id' => $revisionRecord->getPageId(),
				'rev_id' => $revisionRecord->getID(),
				'time_stamp' => $revisionRecord->getTimestamp(),
			],
			__METHOD__
		);

		if ( !$res ) {
			return null;
		}

		return $db->insertId();
	}

	/**
	 * Clear all entries for the given page
	 * (on page deletion)
	 *
	 * @param Title $title
	 * @return bool
	 */
	public function clearEntriesForPage( Title $title ): bool {
		return $this->lb->getConnection( DB_PRIMARY )->delete(
			static::TABLE,
			[
				// TODO: This should be getPrefixedDbKey(), but that wouldnt be B/C
				'page_title' => $title->getPrefixedText()
			],
			__METHOD__
		);
	}
}
