<?php

namespace DataAccounting\Transfer;

use DataAccounting\Verification\GenericDatabaseEntity;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationEntity;

class TransferEntityFactory {
	private $verificationEngine;

	/**
	 * @param VerificationEngine $engine
	 */
	public function __construct( VerificationEngine $engine ) {
		$this->verificationEngine = $engine;
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
			if ( $file instanceof \File) {
				$content = file_get_contents( $file->getLocalRefPath() );
				if ( is_string( $content ) ) {
					$contentOutput['file'] = base64_encode( $content );
				}
			}
		}

		$metadataOutput = [
			'domain_id' => $entity->getDomainId(),
			'time_stamp' => $entity->getTime()->format( 'YmdHis' ),
			'previous_verification_hash' => $entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ),
			'metadata_hash' => $entity->getHash( VerificationEntity::METADATA_HASH ),
		];

		$signatureOutput = [
			'signature' => $entity->getSignature(),
			'public_key' => $entity->getPublicKey(),
			'wallet_address' => $entity->getWalletAddress(),
			'signature_hash' => $entity->getHash( VerificationEntity::SIGNATURE_HASH ),
		];

		$witnessOutput = null;
		if ( $entity->getWitnessEventId() ) {
			$witnessEntity = $this->verificationEngine->getWitnessEntity( $entity );
			if ( $witnessEntity instanceof GenericDatabaseEntity ) {
				$witnessOutput = $witnessEntity->jsonSerialize();
				$witnessOutput['structured_merkle_proof'] =
					$this->verificationEngine->requestMerkleProof( $entity );
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
