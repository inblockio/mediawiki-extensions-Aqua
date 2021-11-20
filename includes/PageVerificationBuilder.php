<?php

declare( strict_types = 1 );

namespace DataAccounting;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

class PageVerificationBuilder {

	public function __construct(
		private PageVerificationRepo $verificationRepo,
		private HashingService $hashingService
	) {
	}

	public function buildVerificationData( RevisionRecord $rev ): array {
		// CONTENT DATA HASH CALCULATOR
		$pageContent = $rev->getContent( SlotRecord::MAIN )->serialize();
		$contentHash = getHashSum( $pageContent );

		// GET DATA FOR META DATA and SIGNATURE DATA
		$parentId = $rev->getParentId();
		$verificationData = $this->verificationRepo->getRevisionVerificationData( $parentId );

		// META DATA HASH CALCULATOR
		$previousVerificationHash = $verificationData['verification_hash'];
		$timestamp = $rev->getTimeStamp();
		$metadataHash = $this->hashingService->calculateMetadataHash( $timestamp, $previousVerificationHash );

		// SIGNATURE DATA HASH CALCULATOR
		$signature = $verificationData['signature'];
		$publicKey = $verificationData['public_key'];
		$signatureHash = $this->hashingService->calculateSignatureHash( $signature, $publicKey );

		// WITNESS DATA HASH CALCULATOR
		$witnessData = getWitnessData( $verificationData['witness_event_id'] ); // TODO: inject new service
		if ( !empty( $witnessData ) ) {
			$domain_manifest_verification_hash = $witnessData['witness_event_verification_hash'];
			$merkle_root = $witnessData['merkle_root'];
			$witness_network = $witnessData['witness_network'];
			$witness_tx_hash = $witnessData['witness_event_transaction_hash'];
			$witnessHash = $this->hashingService->calculateWitnessHash(
				$domain_manifest_verification_hash,
				$merkle_root,
				$witness_network,
				$witness_tx_hash
			);
		} else {
			$witnessHash = '';
		}

		// TODO: return new PageVerification object. Or maybe write to the repo here, turning this into a "PageVerifier"?
		return [
			'domain_id' => getDomainId(), // TODO: inject global
			'page_title' => $rev->getPage()->getDBkey(),
			'page_id' => $rev->getPage()->getId(),
			'rev_id' => $rev->getID(),
			'hash_content' => $contentHash,
			'time_stamp' => $timestamp,
			'hash_metadata' => $metadataHash,
			'verification_hash' => $this->hashingService->calculateVerificationHash(
				$contentHash,
				$metadataHash,
				$signatureHash,
				$witnessHash
			),
			'signature' => '',
			'public_key' => '',
			'wallet_address' => '',
			'source' => 'default',
		];
	}

}
