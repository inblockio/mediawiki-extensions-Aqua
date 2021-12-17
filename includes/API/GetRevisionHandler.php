<?php

namespace DataAccounting\API;

use DataAccounting\Verification\GenericDatabaseEntity;
use DataAccounting\Verification\VerificationEntity;
use MediaWiki\Storage\RevisionRecord;
use Wikimedia\ParamValidator\ParamValidator;

class GetRevisionHandler extends AuthorizedEntityHandler {

	/** @inheritDoc */
	public function run() {
		$contentOutput = [
			'rev_id' => $this->verificationEntity->getRevision()->getId(),
			'content' => $this->prepareContent( $this->verificationEntity->getRevision() ),
			'content_hash' => $this->verificationEntity->getHash( VerificationEntity::CONTENT_HASH ),
		];

		$metadataOutput = [
			'domain_id' => $this->verificationEntity->getDomainId(),
			'time_stamp' => $this->verificationEntity->getTime()->format( 'YmdHis' ),
			'previous_verification_hash' => $this->verificationEntity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ),
			'metadata_hash' => $this->verificationEntity->getHash( VerificationEntity::METADATA_HASH ),
		];

		$signatureOutput = [
			'signature' => $this->verificationEntity->getSignature(),
			'public_key' => $this->verificationEntity->getPublicKey(),
			'wallet_address' => $this->verificationEntity->getWalletAddress(),
			'signature_hash' => $this->verificationEntity->getHash( VerificationEntity::SIGNATURE_HASH ),
		];

		$witnessOutput = null;
		if ( $this->verificationEntity->getWitnessEventId() ) {
			$witnessEntity = $this->verificationEngine->getWitnessEntity( $this->verificationEntity );
			if ( $witnessEntity instanceof GenericDatabaseEntity ) {
				$witnessOutput = $witnessEntity->jsonSerialize();
				$witnessOutput['structured_merkle_proof'] =
					$this->verificationEngine->requestMerkleProof( $this->verificationEntity );
			}
		}

		return [
			'verification_context' => $this->verificationEntity->getVerificationContext(),
			'content' => $contentOutput,
			'metadata' => $metadataOutput,
			'signature' => $signatureOutput,
			'witness' => $witnessOutput,
		];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'verification_hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @param string $verificationHash
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $verificationHash ): ?VerificationEntity {
		return $this->verificationEngine->getLookup()->verificationEntityFromHash( $verificationHash );
	}

	/**
	 * Collect and compile content of all slots
	 *
	 * @param RevisionRecord $revision
	 * @return array
	 */
	private function prepareContent( RevisionRecord $revision ) {
		$slots = $revision->getSlotRoles();
		$merged = [];
		foreach ( $slots as $role ) {
			$slot = $revision->getSlot( $role );
			if ( !$slot->getContent() ) {
				continue;
			}
			$merged[$role] = $slot->getContent()->serialize();
		}

		return $merged;
	}
}
