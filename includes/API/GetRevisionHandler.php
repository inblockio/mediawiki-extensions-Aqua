<?php

namespace DataAccounting\API;

use DataAccounting\Verification\GenericDatabaseEntity;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationEntity;
use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Storage\RevisionRecord;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

class GetRevisionHandler extends ContextAuthorized {
	/** @var VerificationEngine */
	private VerificationEngine $verificationEngine;
	/** @var VerificationEntity|null */
	private ?VerificationEntity $verificationEntity = null;

	/**
	 * @param PermissionManager $permissionManager
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct(
		PermissionManager $permissionManager, VerificationEngine $verificationEngine
	) {
		parent::__construct( $permissionManager );
		$this->verificationEngine = $verificationEngine;

	}

	/** @inheritDoc */
	public function run( $verification_hash ) {
		$this->assertVerificationEntity( $verification_hash );

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

	/** @inheritDoc */
	protected function provideTitle( string $verification_hash ): ?Title {
		$this->assertVerificationEntity( $verification_hash );
		return $this->verificationEntity->getTitle();
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

	/**
	 * Ensure VerificationEntity is set
	 * @param string $verificationHash
	 * @throws HttpException
	 */
	private function assertVerificationEntity( string $verificationHash ) {
		$this->verificationEntity = $this->verificationEngine->getLookup()->verificationEntityFromHash(
			$verificationHash
		);
		if ( !( $this->verificationEntity instanceof VerificationEntity ) ) {
			throw new HttpException( "Not found", 404 );
		}
	}
}
