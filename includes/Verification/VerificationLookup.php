<?php

namespace DataAccounting\Verification;

use DataAccounting\ForkChainResolver;
use DataAccounting\Verification\Entity\VerificationEntity;
use Exception;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use NamespaceInfo;
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
	/** @var NamespaceInfo  */
	private $namespaceInfo;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param RevisionStore $revisionStore
	 * @param VerificationEntityFactory $entityFactory
	 */
	public function __construct(
		ILoadBalancer $loadBalancer, RevisionStore $revisionStore,
		VerificationEntityFactory $entityFactory, NamespaceInfo $namespaceInfo
	) {
		$this->lb = $loadBalancer;
		$this->revisionStore = $revisionStore;
		$this->verificationEntityFactory = $entityFactory;
		$this->namespaceInfo = $namespaceInfo;
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
		if ( !( $title instanceof Title ) || !$title->exists() ) {
			return null;
		}
		// TODO: Replace with getPrefixedDBkey, once database enties use it
		return $this->verificationEntityFromQuery( [ 'page_title' => $this->getCanonicalTitle( $title ) ] );
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
		$res = $this->allVerificationEntitiesFromQuery( $query, 1 );
		return count( $res ) ? $res[0] : null;
	}

	/**
	 * @param array $query
	 * @param int|null $limit
	 *
	 * @return VerificationEntity[]
	 */
	public function allVerificationEntitiesFromQuery( array $query, int $limit = -1 ): array {
		$options = [
			'ORDER BY' => [ 'rev_id DESC' ]
		];
		if ( $limit > 0 ) {
			$options['LIMIT'] = $limit;
		}
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			static::TABLE,
			[
				'revision_verification_id',
				'domain_id',
				'genesis_hash',
				'page_title',
				'page_id',
				'rev_id',
				// 'verification_context',
				'content_hash',
				'time_stamp',
				'metadata_hash',
				'verification_hash',
				'previous_verification_hash',
				'signature_hash',
				'signature',
				'public_key',
				'wallet_address',
				'witness_event_id',
				'source',
			],
			$query,
			__METHOD__,
			$options,
		);

		if ( !$res ) {
			return [];
		}

		$entities = [];
		foreach ( $res as $row ) {
			$entities[] = $this->verificationEntityFactory->newFromDbRow( $row );
		}

		return $entities;
	}

	/**
	 * @param string|Title $title
	 * @return array
	 */
	public function getAllRevisionIds( $title ): array {
		if ( $title instanceof Title ) {
			$title = $this->getCanonicalTitle( $title );
		}
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			'revision_verification',
			[ 'rev_id', 'previous_verification_hash', 'genesis_hash' ],
			[ 'page_title' => $title ],
			__METHOD__,
			[ 'ORDER BY' => 'rev_id' ]
		);

		$output = null;
		foreach ( $res as $row ) {
			if ( $output === null ) {
				$output = [];
				$firstRev = (int)$row->rev_id;
				// If page is forked, get all revisions from the fork point
				$parents = ( new ForkChainResolver( $this ) )->resolveFromRevisionId( $firstRev );
				if ( $parents ) {
					$output = array_map( static function ( VerificationEntity $entity ) {
						return $entity->getRevision()->getId();
					}, $parents );
				}
			}
			$output[] = (int)$row->rev_id;
		}

		return $output ?? [];
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
	 * @return VerificationEntity|null Updated entity of null on failure
	 */
	public function updateEntity( VerificationEntity $entity, array $data ): ?VerificationEntity {
		$res = $this->lb->getConnection( DB_PRIMARY )->update(
			static::TABLE,
			$data,
			[ 'rev_id' => $entity->getRevision()->getId() ],
			__METHOD__
		);

		if ( $res ) {
			return $this->verificationEntityFactory->newFromDbRow(
				(object)array_merge(
					$entity->jsonSerialize(),
					$data
				)
			);
		}
		return null;
	}

	/**
	 * @return array
	 */
	public function getAllEntities(): array {
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			static::TABLE,
			[
				'revision_verification_id',
				'domain_id',
				'genesis_hash',
				'page_title',
				'page_id',
				'rev_id',
				// 'verification_context',
				'content_hash',
				'time_stamp',
				'metadata_hash',
				'verification_hash',
				'previous_verification_hash',
				'signature_hash',
				'signature',
				'public_key',
				'wallet_address',
				'witness_event_id',
				'source',
			],
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
				'page_title' => $this->getCanonicalTitle( $title ),
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
				'page_title' => $this->getCanonicalTitle( $title ),
			],
			__METHOD__
		);
	}

	/**
	 * @param int $revId
	 *
	 * @return bool
	 */
	public function deleteForRevId( int $revId ): bool {
		return $this->lb->getConnection( DB_PRIMARY )->delete(
			static::TABLE,
			[
				'rev_id' => $revId,
			],
			__METHOD__
		);
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private function getCanonicalTitle( Title $title ): string {
		if ( $title->getNamespace() === NS_MAIN ) {
			return $title->getText();
		}
		$canonicalNamespace = $this->namespaceInfo->getCanonicalName( $title->getNamespace() );
		if ( !$canonicalNamespace ) {
			return $title->getPrefixedText();
		}
		return "$canonicalNamespace:{$title->getText()}";
	}
}
