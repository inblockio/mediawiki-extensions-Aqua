<?php

declare( strict_types = 1 );

namespace DataAccounting;

class HashingService {

	public function __construct(
		private string $domainId
	) {
	}

	public function calculateMetadataHash( $timestamp, $previousVerificationHash = "" ): string {
		return getHashSum( $this->domainId . $timestamp . $previousVerificationHash );
	}

	public function calculateSignatureHash( $signature, $publicKey ): string {
		return getHashSum( $signature . $publicKey );
	}

	public function calculateWitnessHash( $domain_manifest_verification_hash, $merkle_root, $witness_network, $witness_tx_hash ): string {
		return getHashSum( $domain_manifest_verification_hash . $merkle_root . $witness_network . $witness_tx_hash );
	}

	public function calculateVerificationHash( $contentHash, $metadataHash, $signature_hash, $witness_hash ): string {
		return getHashSum( $contentHash . $metadataHash . $signature_hash . $witness_hash );
	}

}
