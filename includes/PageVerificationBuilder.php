<?php

declare( strict_types = 1 );

namespace DataAccounting;

use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\RevisionRecord;
use Wikimedia\Rdbms\ILoadBalancer;

class PageVerificationBuilder {

	public function __construct(
		private ILoadBalancer $loadBalancer
	) {
	}

	public function buildVerificationData( RevisionRecord $rev ): array {
		// CONTENT DATA HASH CALCULATOR
		$pageContent = $rev->getContent( SlotRecord::MAIN )->serialize();
		$contentHash = getHashSum( $pageContent );

		// GET DATA FOR META DATA and SIGNATURE DATA
		$parentId = $rev->getParentId();
		$verificationData = $this->getPageVerificationData( $parentId );

		// META DATA HASH CALCULATOR
		$previousVerificationHash = $verificationData['verification_hash'];
		$domainId = getDomainId();
		$timestamp = $rev->getTimeStamp();
		$metadataHash = calculateMetadataHash( $domainId, $timestamp, $previousVerificationHash );

		// SIGNATURE DATA HASH CALCULATOR
		$signature = $verificationData['signature'];
		$publicKey = $verificationData['public_key'];
		$signatureHash = calculateSignatureHash( $signature, $publicKey );

		// WITNESS DATA HASH CALCULATOR
		$witnessData = getWitnessData( $verificationData['witness_event_id'] );
		if ( !empty( $witnessData ) ) {
			$domain_manifest_verification_hash = $witnessData['witness_event_verification_hash'];
			$merkle_root = $witnessData['merkle_root'];
			$witness_network = $witnessData['witness_network'];
			$witness_tx_hash = $witnessData['witness_event_transaction_hash'];
			$witnessHash = calculateWitnessHash(
				$domain_manifest_verification_hash,
				$merkle_root,
				$witness_network,
				$witness_tx_hash
			);
		} else {
			$witnessHash = '';
		}

		return [
			'domain_id' => getDomainId(),
			'page_title' => $rev->getPage()->getDBkey(),
			'page_id' => $rev->getPage()->getId(),
			'rev_id' => $rev->getID(),
			'hash_content' => $contentHash,
			'time_stamp' => $timestamp,
			'hash_metadata' => $metadataHash,
			'verification_hash' => calculateVerificationHash(
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

	private function getPageVerificationData( $previous_rev_id ): array {
		$dbr = $this->loadBalancer->getConnection( DB_PRIMARY );

		$row = $dbr->selectRow(
			'page_verification',
			[ 'rev_id', 'verification_hash', 'signature', 'public_key', 'wallet_address', 'witness_event_id' ],
			"rev_id = $previous_rev_id",
			__METHOD__
		);

		if ( !$row ) {
			// When $row is empty, we have to construct $output consisting of empty
			// strings.
			return [
				'verification_hash' => "",
				'signature' => "",
				'public_key' => "",
				'wallet_address' => "",
				'witness_event_id' => null,
			];
		}

		return [
			'verification_hash' => $row->verification_hash,
			'signature' => $row->signature,
			'public_key' => $row->public_key,
			'wallet_address' => $row->wallet_address,
			'witness_event_id' => $row->witness_event_id
		];
	}

}
