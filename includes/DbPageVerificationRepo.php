<?php

declare( strict_types = 1 );

namespace DataAccounting;

use Wikimedia\Rdbms\ILoadBalancer;

class DbPageVerificationRepo implements PageVerificationRepo {

	public function __construct(
		private ILoadBalancer $loadBalancer
	) {
	}

	public function getPageVerificationData( int $revId ): array {
		$dbr = $this->loadBalancer->getConnection( DB_PRIMARY );

		$row = $dbr->selectRow(
			'page_verification',
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

		return [
			'verification_hash' => $row->verification_hash,
			'signature' => $row->signature,
			'public_key' => $row->public_key,
			'wallet_address' => $row->wallet_address,
			'witness_event_id' => $row->witness_event_id
		];
	}

}