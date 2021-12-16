<?php

namespace DataAccounting\Verification;

use DataAccounting\Config\DataAccountingConfig;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

class VerificationEngine {
	/** @var VerificationLookup */
	private $verificationLookup;
	/** @var ILoadBalancer */
	private $lb;
	/** @var DataAccountingConfig */
	private $config;

	/**
	 * @param VerificationLookup $verificationLookup
	 * @param ILoadBalancer $lb
	 * @param DataAccountingConfig $config
	 */
	public function __construct(
		VerificationLookup $verificationLookup, ILoadBalancer $lb, DataAccountingConfig $config
	) {
		$this->verificationLookup = $verificationLookup;
		$this->lb = $lb;
		$this->config = $config;
	}

	/**
	 * @return VerificationLookup
	 */
	public function getLookup(): VerificationLookup {
		return $this->verificationLookup;
	}

	/**
	 * @param string|Title $title
	 * @return int
	 */
	public function getPageChainHeight( $title ): int {
		return count( $this->getLookup()->getAllRevisionIds( $title ) );
	}

	/**
	 * @param VerificationEntity $verificationEntity
	 * @return GenericDatabaseEntity|null if not witnessed
	 */
	public function getWitnessEntity( VerificationEntity $verificationEntity ): ?GenericDatabaseEntity {
		if ( !$verificationEntity->getWitnessEventId() ) {
			return null;
		}

		$row = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'witness_events',
			[
				'domain_id',
				'domain_manifest_title',
				'witness_hash',
				'witness_event_verification_hash',
				'witness_network',
				'smart_contract_address',
				'domain_manifest_genesis_hash',
				'merkle_root',
				'witness_event_transaction_hash',
				'sender_account_address',
				'source'
			],
			[ 'witness_event_id' => $verificationEntity->getWitnessEventId() ],
			__METHOD__
		);

		if ( !$row ) {
			return null;
		}

		return new GenericDatabaseEntity( $row );
	}

	/**
	 * @param VerificationEntity $verificationEntity
	 * @param int|null $depth
	 * @return array
	 */
	public function requestMerkleProof( VerificationEntity $verificationEntity, $depth = null ) {
		// IF query returns a left or right leaf empty, it means the successor string
		// will be identical the next layer up. In this case it is required to
		// read the depth and start the query with a depth parameter -1 to go to the next layer.
		// This is repeated until the left or right leaf is present and the successor hash different.

		$witnessEventId = $verificationEntity->getWitnessEventId();
		$verificationHash = $verificationEntity->getHash( VerificationEntity::VERIFICATION_HASH );
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
					$output = new GenericDatabaseEntity( $row );
				}
			}
			if ( $output === null ) {
				break;
			}
			$depth = $maxDepth - 1;
			$verificationHash = $output->get( 'successor' );
			$final_output[] = $output;
			if ( $depth === -1 ) {
				break;
			}
		}

		return $finalOutput;
	}

	/**
	 * @return int|null
	 */
	public function getMaxWitnessEventId(): ?int {
		$row = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'witness_events',
			[ 'MAX( witness_event_id ) as witness_event_id' ],
			[],
			__METHOD__,
		);
		if ( !$row ) {
			return null;
		}
		return (int)$row->witness_event_id;
	}

	/**
	 * @param string $input
	 * @return string
	 */
	public function getHashSum( $input ): string {
		if ( $input == '' ) {
			return '';
		}
		return hash( "sha3-512", $input, false );
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getDomainId(): string {
		$domainID = (string)$this->config->get( 'DomainID' );
		if ( $domainID === "UnspecifiedDomainId" ) {
			// A default domain ID is still used, so we generate a new one
			$domainID = $this->generateDomainId();
			$this->config->set( 'DomainID', $domainID );
		}
		return $domainID;
	}

	/**
	 * @return string
	 */
	private function generateDomainId(): string {
		$domainIdFull = $this->generateRandomHash();
		return substr( $domainIdFull, 0, 10 );
	}

	/**
	 * @return string
	 */
	private function generateRandomHash(): string {
		// Returns a hash sum (calculated using getHashSum) of n characters.
		$randomval = '';
		for ( $i = 0; $i < 128; $i++ ) {
			$randomval .= chr( rand( 65, 90 ) );
		}
		return $this->getHashSum( $randomval );
	}
}
