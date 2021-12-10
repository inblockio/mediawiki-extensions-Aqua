<?php

declare( strict_types = 1 );

namespace DataAccounting\Hasher;

require_once __DIR__ . "/../Util.php";

class HashingService {
	public string $domainId;

	public function __construct(
		string $domainId
	) {
		$this->domainId = $domainId;
	}

	public function calculateMetadataHash(
			string $timestamp,
			string $previousVerificationHash = ""
		): string {
		return getHashSum( $this->domainId . $timestamp . $previousVerificationHash );
	}

	public function calculateSignatureHash( string $signature, string $publicKey ): string {
		return getHashSum( $signature . $publicKey );
	}

	public function calculateWitnessHash(
			string $domain_manifest_genesis_hash,
			string $merkle_root,
			string $witness_network,
			string $witness_tx_hash
		): string {
		return getHashSum(
			$domain_manifest_genesis_hash . $merkle_root . $witness_network . $witness_tx_hash
		);
	}

	public function calculateVerificationHash(
			string $contentHash,
			string $metadataHash,
			string $signature_hash,
			string $witness_hash
		): string {
		return getHashSum( $contentHash . $metadataHash . $signature_hash . $witness_hash );
	}

}
