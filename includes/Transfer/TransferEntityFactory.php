<?php

namespace DataAccounting\Transfer;

use DataAccounting\Verification\Entity\GenericDatabaseEntity;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\WitnessingEngine;
use TitleFactory;

class TransferEntityFactory {
	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var WitnessingEngine */
	private $witnessingEngine;
	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param VerificationEngine $engine
	 * @param WitnessingEngine $witnessingEngine
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		VerificationEngine $engine,
		WitnessingEngine $witnessingEngine,
		TitleFactory $titleFactory
	) {
		$this->verificationEngine = $engine;
		$this->titleFactory = $titleFactory;
		$this->witnessingEngine = $witnessingEngine;
	}

	/**
	 * @param array $data
	 * @return TransferContext|null
	 */
	public function newTransferContext( array $data ): ?TransferContext {
		if (
			isset( $data['site_info'] ) && is_array( $data['site_info'] ) &&
			isset( $data['title'] ) && isset( $data['namespace'] )
		) {
			$title = $this->titleFactory->makeTitle( $data['namespace'], $data['title'] );
			if ( !( $title instanceof \Title ) ) {
				return null;
			}
			return new TransferContext(
				$data[VerificationEntity::GENESIS_HASH],
				$data[VerificationEntity::DOMAIN_ID],
				$data['latest_verification_hash'] ?? '',
				$data['site_info'],
				$title,
				$data['chain_height'] ?? 0
			);
		}

		return null;
	}

	/**
	 * @param array $data
	 * @return TransferRevisionEntity|null
	 */
	public function newRevisionEntityFromApiData( array $data ): ?TransferRevisionEntity {
		if (
			isset( $data['verification_context'] ) && is_array( $data['verification_context'] ) &&
			isset( $data['content'] ) && is_array( $data['content'] ) &&
			isset( $data['metadata'] ) && is_array( $data['metadata'] ) &&
			isset( $data['signature'] ) && is_array( $data['signature'] )
		) {
			return new TransferRevisionEntity(
				$data['verification_context'],
				$data['content'],
				$data['metadata'],
				$data['signature'],
				$data['witness'] ?? null
			);
		}

		return null;
	}

	/**
	 * @param VerificationEntity $entity
	 * @return TransferRevisionEntity
	 */
	public function newRevisionEntityFromVerificationEntity(
		VerificationEntity $entity
	): TransferRevisionEntity {
		$contentOutput = [
			'rev_id' => $entity->getRevision()->getId(),
			'content' => $this->prepareContent( $entity ),
			'content_hash' => $entity->getHash( VerificationEntity::CONTENT_HASH ),
		];

		if ( $entity->getRevision()->getPage()->getNamespace() === NS_FILE ) {
			$file = $this->verificationEngine->getFileForVerificationEntity( $entity );
			if ( $file instanceof \File ) {
				$content = file_get_contents( $file->getLocalRefPath() );
				if ( is_string( $content ) ) {
					$contentOutput['file'] = [
						'data' => base64_encode( $content ),
						'filename' => $file->getName(),
						'size' => $file->getSize(),
						'comment' => $entity->getRevision()->getComment()->text,
					];
				}
			}
		}

		$metadataOutput = [
			'domain_id' => $entity->getDomainId(),
			'time_stamp' => $entity->getTime()->format( 'YmdHis' ),
			'previous_verification_hash' => $entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ),
			'metadata_hash' => $entity->getHash( VerificationEntity::METADATA_HASH ),
			'verification_hash' => $entity->getHash( VerificationEntity::VERIFICATION_HASH )
		];

		$signatureOutput = [
			'signature' => $entity->getSignature(),
			'public_key' => $entity->getPublicKey(),
			'wallet_address' => $entity->getWalletAddress(),
			'signature_hash' => $entity->getHash( VerificationEntity::SIGNATURE_HASH ),
		];

		$witnessOutput = null;
		if ( $entity->getWitnessEventId() ) {
			$witnessEntity = $this->witnessingEngine->getLookup()->witnessEventFromVerificationEntity( $entity );
			if ( $witnessEntity instanceof GenericDatabaseEntity ) {
				$witnessOutput = $witnessEntity->jsonSerialize();
				$witnessOutput['structured_merkle_proof'] =
					$this->witnessingEngine->getLookup()->requestMerkleProof(
						$entity->getWitnessEventId(),
						$entity->getHash( VerificationEntity::VERIFICATION_HASH )
					);
			}
		}

		return new TransferRevisionEntity(
			$entity->getVerificationContext(),
			$contentOutput,
			$metadataOutput,
			$signatureOutput,
			$witnessOutput
		);
	}

	/**
	 * Collect and compile content of all slots
	 *
	 * @param VerificationEntity $entity
	 * @return array
	 */
	private function prepareContent( VerificationEntity $entity ) {
		$slots = $entity->getRevision()->getSlotRoles();
		$merged = [];
		foreach ( $slots as $role ) {
			$slot = $entity->getRevision()->getSlot( $role );
			if ( !$slot->getContent() ) {
				continue;
			}
			$merged[$role] = $slot->getContent()->serialize();
		}

		return $merged;
	}
}
