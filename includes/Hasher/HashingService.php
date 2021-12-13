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

	public function calculateMetadataHash( $timestamp, $previousVerificationHash = "" ): string {
		return getHashSum( $this->domainId . $timestamp . $previousVerificationHash );
	}

	public function calculateSignatureHash( $signature, $publicKey ): string {
		return getHashSum( $signature . $publicKey );
	}

	public function calculateWitnessHash( $domain_manifest_genesis_hash, $merkle_root, $witness_network, $witness_tx_hash ): string {
		return getHashSum( $domain_manifest_genesis_hash . $merkle_root . $witness_network . $witness_tx_hash );
	}

	public function calculateVerificationHash( $contentHash, $metadataHash, $signature_hash, $witness_hash ): string {
		return getHashSum( $contentHash . $metadataHash . $signature_hash . $witness_hash );
	}

	// TODO: maybe better to keep $domainId as parameter since the using service needs to have it injected anyway
	// TODO: perhaps we can have a single function like below:

//	public function hash( string ...$strings ): string {
//		return getHashSum( implode( '', $strings ) );
//	}

}
