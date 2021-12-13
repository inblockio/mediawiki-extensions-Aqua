<?php

declare( strict_types = 1 );

namespace DataAccounting\Hasher;

use Wikimedia\Rdbms\ILoadBalancer;

class DbRevisionVerificationRepo implements RevisionVerificationRepo {
	private ILoadBalancer $loadBalancer;

	public function __construct(
		ILoadBalancer $loadBalancer
	) {
		$this->loadBalancer = $loadBalancer;
	}

	public function getRevisionVerificationData( int $revId ): array {
		$dbr = $this->loadBalancer->getConnection( DB_PRIMARY );

		$row = $dbr->selectRow(
			'revision_verification',
			[ 'rev_id', 'verification_hash', 'signature', 'public_key', 'wallet_address', 'witness_event_id' ],
			[ 'rev_id' => $revId ],
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

		// TODO: return new RevisionVerification object
		return [
			'verification_hash' => $row->verification_hash,
			'signature' => $row->signature,
			'public_key' => $row->public_key,
			'wallet_address' => $row->wallet_address,
			'witness_event_id' => $row->witness_event_id
		];
	}

}
