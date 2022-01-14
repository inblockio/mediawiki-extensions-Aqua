<?php

namespace DataAccounting\Verification;

use DataAccounting\Verification\Entity\MerkleTreeEntity;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\Entity\WitnessEventEntity;
use DataAccounting\Verification\Entity\WitnessPageEntity;
use Wikimedia\Rdbms\ILoadBalancer;

class WitnessLookup {
	/** @var ILoadBalancer */
	private $lb;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->lb = $loadBalancer;
	}

	/**
	 * @param array $query
	 * @return WitnessEventEntity|null
	 */
	public function witnessEventFromQuery( array $query ): ?WitnessEventEntity {
		$row = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'witness_events',
			[ '*' ],
			$query,
			__METHOD__,
			[
				'ORDER BY' => [ 'witness_event_id DESC' ]
			]
		);

		if ( !$row ) {
			return null;
		}

		return new WitnessEventEntity( $row );
	}

	/**
	 * @param array $witnessInfo
	 * @return int|null
	 */
	public function insertWitnessEvent( array $witnessInfo ): ?int {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$res = $db->insert(
			'witness_events',
			$witnessInfo,
			__METHOD__
		);

		 if ( !$res ) {
			return null;
		 }

		 return $db->insertId();
	}

	/**
	 * @return int
	 */
	public function getLastWitnessEventId(): int {
		$res = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'witness_events',
			[ 'MAX( witness_event_id) as id' ],
			[],
			__METHOD__
		);

		if ( !$res ) {
			// Sanity
			return 0;
		}
		return (int)$res->id;
	}

	/**
	 * @param array $data
	 * @return int|null
	 */
	public function insertMerkleTreeNode( array $data ): ?int {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$res = $db->insert(
			'witness_merkle_tree',
			$data,
			__METHOD__
		);

		if ( !$res ) {
			return null;
		}

		return $db->insertId();
	}

	/**
	 * @param int $witnessEventId
	 * @param string $verificationHash
	 * @param null $depth
	 * @return array
	 */
	public function requestMerkleProof( $witnessEventId, $verificationHash, $depth = null ) {
		// IF query returns a left or right leaf empty, it means the successor string
		// will be identical the next layer up. In this case it is required to
		// read the depth and start the query with a depth parameter -1 to go to the next layer.
		// This is repeated until the left or right leaf is present and the successor hash different.
		$finalOutput = [];

		while ( true ) {
			// TODO: This can be written more nicely by using the $dbr methods for lists
			if ( $depth === null ) {
				$conds =
					'left_leaf=\'' . $verificationHash .
					'\' AND witness_event_id=' . $witnessEventId .
					' OR right_leaf=\'' . $verificationHash .
					'\' AND witness_event_id=' . $witnessEventId;
			} else {
				$conds =
					'left_leaf=\'' . $verificationHash .
					'\' AND witness_event_id=' . $witnessEventId .
					' AND depth=' . $depth .
					' OR right_leaf=\'' . $verificationHash .
					'\'  AND witness_event_id=' . $witnessEventId .
					' AND depth=' . $depth;
			}
			$res = $this->lb->getConnection( DB_REPLICA )->select(
				'witness_merkle_tree',
				[
					'witness_event_id',
					'depth',
					'left_leaf',
					'right_leaf',
					'successor'
				],
				$conds,
			);

			$output = null;
			$maxDepth = null;
			foreach ( $res as $row ) {
				if ( $maxDepth === null || ( (int)$row->depth > $maxDepth ) ) {
					$maxDepth = $row->depth;
					$output = new MerkleTreeEntity( $row );
				}
			}
			if ( $output === null ) {
				break;
			}
			$depth = $maxDepth - 1;
			$verificationHash = $output->get( 'successor' );
			$finalOutput[] = $output;
			if ( $depth === -1 ) {
				break;
			}
		}

		return $finalOutput;
	}

	/**
	 * @param array $query
	 * @return MerkleTreeEntity|null
	 */
	public function merkleTreeFromQuery( array $query ): ?MerkleTreeEntity {
		$row = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'witness_merkle_tree',
			[ '*' ],
			$query,
			__METHOD__,
			[
				'ORDER BY' => [ 'witness_event_id DESC' ]
			]
		);

		if ( !$row ) {
			return null;
		}

		return new MerkleTreeEntity( $row );
	}

	/**
	 * @param VerificationEntity $entity
	 * @return WitnessEventEntity|null
	 */
	public function witnessEventFromVerificationEntity( VerificationEntity $entity ): ?WitnessEventEntity {
		if ( !$entity->getWitnessEventId() ) {
			return null;
		}

		$row = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'witness_events',
			[ '*' ],
			[ 'witness_event_id' => $entity->getWitnessEventId() ],
			__METHOD__
		);

		if ( !$row ) {
			return null;
		}

		return new WitnessEventEntity( $row );
	}

	public function pageEntitiesFromWitnessId( string $witnessEventId ) {
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			'witness_page',
			[ '*' ],
			[ 'witness_event_id' => $witnessEventId ],
			__METHOD__
		);

		$entities = [];
		foreach ( $res as $row ) {
			$entities[] = new WitnessPageEntity( $row );
		}

		return $entities;
	}

	/**
	 * @param WitnessEventEntity $entity
	 * @param array $data
	 * @return WitnessEventEntity|null Updated entity or null on failure
	 */
	public function updateWitnessEventEntity( WitnessEventEntity $entity, array $data ): ?WitnessEventEntity {
		$res = $this->lb->getConnection( DB_PRIMARY )->update(
			'witness_events',
			$data,
			[ 'witness_event_id' => $entity->get( 'witness_event_id' ) ],
			__METHOD__
		);

		if ( $res ) {
			return new WitnessEventEntity(
				(object)array_merge( $entity->jsonSerialize(), $data )
			);
		}

		return null;
	}
}
