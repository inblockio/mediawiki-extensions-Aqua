<?php

declare( strict_types = 1 );

namespace DataAccounting\Hasher;

require_once __DIR__ . "/../Util.php";

class HashingService {
	/**
	 * @var string
	 */
	public string $domainId;

	/**
	 * @param string $domainId
	 */
	public function __construct(
		string $domainId
	) {
		$this->domainId = $domainId;
	}

	/**
	 * @param string $timestamp
	 * @param string $previousVerificationHash
	 * @return string
	 */
	public function calculateMetadataHash(
			string $timestamp,
			string $previousVerificationHash = ""
		): string {
		return getHashSum( $this->domainId . $timestamp . $previousVerificationHash );
	}

	/**
	 * @param string $signature
	 * @param string $publicKey
	 * @return string
	 */
	public function calculateSignatureHash( string $signature, string $publicKey ): string {
		return getHashSum( $signature . $publicKey );
	}

	/**
	 * @param string $domain_manifest_verification_hash
	 * @param string $merkle_root
	 * @param string $witness_network
	 * @param string $witness_tx_hash
	 * @return string
	 */
	public function calculateWitnessHash(
			string $domain_manifest_verification_hash,
			string $merkle_root,
			string $witness_network,
			string $witness_tx_hash
		): string {
		return getHashSum(
			$domain_manifest_verification_hash . $merkle_root . $witness_network . $witness_tx_hash
		);
	}

	/**
	 * @param string $contentHash
	 * @param string $metadataHash
	 * @param string $signature_hash
	 * @param string $witness_hash
	 * @return string
	 */
	public function calculateVerificationHash(
			string $contentHash,
			string $metadataHash,
			string $signature_hash,
			string $witness_hash
		): string {
		return getHashSum( $contentHash . $metadataHash . $signature_hash . $witness_hash );
	}

}
