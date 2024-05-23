<?php

namespace DataAccounting\Verification;

use DataAccounting\Config\DataAccountingConfig;
use DataAccounting\Content\SignatureContent;
use DataAccounting\Verification\Entity\VerificationEntity;
use Exception;
use File;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Storage\PageUpdaterFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
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
	/** @var WitnessingEngine */
	private $witnessingEngine;

	/** @var VerificationEntity|null */
	private $forcedParent;

	/**
	 * @param VerificationLookup $verificationLookup
	 * @param ILoadBalancer $lb
	 * @param DataAccountingConfig $config
	 * @param WikiPageFactory $wikiPageFactory
	 * @param RevisionStore $revisionStore
	 * @param PageUpdaterFactory $pageUpdaterFactory
	 * @param WitnessingEngine $witnessingEngine
	 */
	public function __construct(
		VerificationLookup $verificationLookup,
		ILoadBalancer $lb,
		DataAccountingConfig $config,
		WikiPageFactory $wikiPageFactory,
		RevisionStore $revisionStore,
		PageUpdaterFactory $pageUpdaterFactory,
		WitnessingEngine $witnessingEngine
	) {
		$this->verificationLookup = $verificationLookup;
		$this->lb = $lb;
		$this->config = $config;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->revisionStore = $revisionStore;
		$this->witnessingEngine = $witnessingEngine;

		$this->forcedParent = null;
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
	 * @return string
	 * @throws Exception
	 */
	public function getDomainId(): string {
		$domainID = (string)$this->config->get( 'DomainID' );
		if ( $domainID === "UnspecifiedDomainId" ) {
			// A default domain ID is still used, so we generate a new one
			$domainID = $this->getHasher()->generateDomainId();
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
		$signatureHash = $this->getHasher()->getHashSum( $signature . $publicKey );

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

		if ( !$this->config->get( 'InjectSignature' ) ) {
			// skip writing the signatures into the actual page
			return true;
		}
		return $this->storeSignature( $entity, $user, $walletAddress );
	}

	/**
	 * @param VerificationEntity $entity
	 * @param File|null $baseFile
	 * @return File|null
	 */
	public function getFileForVerificationEntity( VerificationEntity $entity, ?File $baseFile = null ): ?File {
		if ( $entity->getTitle()->getNamespace() !== NS_FILE ) {
			return null;
		}
		if ( !$baseFile ) {
			$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
			$file = $repoGroup->findFile( $entity->getTitle() );
		} else {
			$file = $baseFile;
		}

		if ( !$file ) {
			return null;
		}

		if ( $entity->getRevision()->isCurrent() ) {
			return $file;
		}
		$oldFiles = $file->getHistory();
		foreach ( $oldFiles as $oldFile ) {
			if ( $oldFile->getTimestamp() === $entity->getRevision()->getTimestamp() ) {
				return $oldFile;
			}
		}
		return null;
	}

	/**
	 * @return Hasher
	 */
	public function getHasher(): Hasher {
		return new Hasher();
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
	 * @param VerificationEntity $entity
	 * @param RevisionRecord $rev
	 * @param VerificationEntity|null $parentEntity
	 * @return VerificationEntity|null
	 * @throws Exception
	 */
	public function buildAndUpdateVerificationData(
		VerificationEntity $entity, \MediaWiki\Revision\RevisionRecord $rev, ?VerificationEntity $parentEntity = null
	): ?VerificationEntity {
		$contentHash = $this->getHasher()->calculateContentHash( $rev );

		$parentEntity = $parentEntity ?? $this->getParentEntity( $entity, $rev );

		// META DATA HASH CALCULATOR
		$previousVerificationHash = $parentEntity ?
			$parentEntity->getHash( VerificationEntity::VERIFICATION_HASH ) : '';
		$timestamp = $rev->getTimestamp();
		$metadataHash = $this->getHasher()->getHashSum(
			$this->getDomainId() . $timestamp . $previousVerificationHash
		);

		// SIGNATURE DATA HASH CALCULATOR
		$signature = $parentEntity ? $parentEntity->getSignature() : '';
		$publicKey = $parentEntity ? $parentEntity->getPublicKey() : '';
		$signatureHash = $this->getHasher()->getHashSum( $signature . $publicKey );

		$witnessHash = '';
		if ( $parentEntity && $parentEntity->getWitnessEventId() ) {
			$witnessEntity = $this->witnessingEngine->getLookup()->witnessEventFromQuery( [
				'witness_event_id' => $parentEntity->getWitnessEventId()
			] );
			if ( $witnessEntity ) {
				$witnessHash = $this->getHasher()->getHashSum(
					$witnessEntity->get( 'domain_snapshot_genesis_hash' ) .
					$witnessEntity->get( 'merkle_root' ) .
					$witnessEntity->get( 'witness_network' ) .
					$witnessEntity->get( 'witness_event_transaction_hash' )
				);
			}
		}

		$verificationHash = $this->getHasher()->getHashSum(
			"{$contentHash}{$metadataHash}{$signatureHash}{$witnessHash}"
		);

		if ( $parentEntity ) {
			// Take the previous genesis_hash if present and continue to use it.
			$genesisHash = $parentEntity->getHash( VerificationEntity::GENESIS_HASH );
			if ( $genesisHash === "" ) {
				// This can happen, if files get imported without verification data
				// and therefore are not created, imported or moved and the
				// genesis_hash remains empty.
				// In this case, use the newly generated verification_hash.
				$genesisHash = $verificationHash;
			}
		} else {
			// If there is no parent, we know we are on the first verified
			// revision.
			$genesisHash = $verificationHash;
		}

		$verificationContext = [
			"has_previous_signature" => !empty( $signatureHash ),
			"has_previous_witness" => !empty( $witnessHash )
		];

		return $this->getLookup()->updateEntity( $entity, [
			'domain_id' => $this->getDomainId(),
			'genesis_hash' => $genesisHash,
			'rev_id' => $rev->getId(),
			'verification_context' => json_encode( $verificationContext ),
			'content_hash' => $contentHash,
			'time_stamp' => $timestamp,
			'metadata_hash' => $metadataHash,
			'verification_hash' => $verificationHash,
			'previous_verification_hash' => $previousVerificationHash,
			'signature' => '',
			'public_key' => '',
			'wallet_address' => '',
			'source' => 'default',
		] );
	}

	/**
	 * @param VerificationEntity $entity
	 * @param RevisionRecord $rev
	 * @return VerificationEntity|null
	 */
	private function getParentEntity( VerificationEntity $entity, RevisionRecord $rev ): ?VerificationEntity {
		$parentRevision = $rev->getParentId();
		if ( $parentRevision ) {
			return $this->getLookup()->verificationEntityFromRevId( $parentRevision );
		}
		return null;
	}
}
