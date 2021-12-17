<?php

namespace DataAccounting\Verification;

use DataAccounting\Config\DataAccountingConfig;
use DataAccounting\Content\SignatureContent;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\PageUpdaterFactory;
use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use MWException;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class VerificationEngine {
	/** @var VerificationLookup */
	private $verificationLookup;
	/** @var ILoadBalancer */
	private $lb;
	/** @var DataAccountingConfig */
	private $config;
	/** @var WikiPageFactory */
	private $wikiPageFactory;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var PageUpdaterFactory */
	private $pageUpdaterFactory;

	/**
	 * @param VerificationLookup $verificationLookup
	 * @param ILoadBalancer $lb
	 * @param DataAccountingConfig $config
	 * @param WikiPageFactory $wikiPageFactory
	 * @param RevisionStore $revisionStore
	 * @param PageUpdaterFactory $pageUpdaterFactory
	 */
	public function __construct(
		VerificationLookup $verificationLookup,
		ILoadBalancer $lb,
		DataAccountingConfig $config,
		WikiPageFactory $wikiPageFactory,
		RevisionStore $revisionStore,
		PageUpdaterFactory $pageUpdaterFactory
	) {
		$this->verificationLookup = $verificationLookup;
		$this->lb = $lb;
		$this->config = $config;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->revisionStore = $revisionStore;
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
	 * @param RevisionRecord $revision
	 * @param User $user
	 * @param string $walletAddress
	 * @param string $signature
	 * @param string $publicKey
	 * @return bool
	 * @throws HttpException
	 * @throws MWException
	 */
	public function signRevision(
		RevisionRecord $revision, User $user, $walletAddress, $signature, $publicKey
	) {
		$signatureHash = $this->getHashSum( $signature . $publicKey );

		// TODO: Should it even be possible to sign old revision (not current)?
		$entity = $this->getLookup()->verificationEntityFromRevId( $revision->getId() );
		if ( $entity === null ) {
			throw new MWException( "Revision not found", 404 );
		}

		$updateRes = $this->verificationLookup->updateEntity( $entity, [
			'signature' => $signature,
			'public_key' => $publicKey,
			'wallet_address' => $walletAddress,
			'signature_hash' => $signatureHash
		] );

		if ( !$updateRes ) {
			return false;
		}

		return $this->storeSignature( $entity, $user, $walletAddress );
	}

	/**
	 * @param VerificationEntity $entity
	 * @param User $user
	 * @param string $walletAddress
	 * @return bool
	 * @throws MWException
	 */
	private function storeSignature( VerificationEntity $entity, User $user, $walletAddress ) {
		$wikipage = $this->wikiPageFactory->newFromTitle( $entity->getTitle() );
		$updater = $this->pageUpdaterFactory->newPageUpdater( $wikipage, $user );
		$lastRevision = $this->revisionStore->getRevisionByTitle( $entity->getTitle() );
		$data = [];
		if ( $lastRevision->hasSlot( SignatureContent::SLOT_ROLE_SIGNATURE ) ) {
			$content = $lastRevision->getContent( SignatureContent::SLOT_ROLE_SIGNATURE );
			if ( $content->isValid() ) {
				$data = json_decode( $content->getText(), 1 );
			}
		}
		$data[] = [
			'user' => $walletAddress,
			'timestamp' => \MWTimestamp::now( TS_MW ),
		];
		$content = new SignatureContent( json_encode( $data ) );
		$updater->setContent( SignatureContent::SLOT_ROLE_SIGNATURE, $content );
		$newRevision = $updater->saveRevision(
			\CommentStoreComment::newUnsavedComment( "Page signed by wallet: $walletAddress" ),
			EDIT_SUPPRESS_RC
		);

		return $newRevision instanceof \MediaWiki\Revision\RevisionRecord;
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
