<?php

namespace DataAccounting\API;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class VerifyPageHandler extends SimpleHandler {

	/** @inheritDoc */
	public function run( $rev_id ) {
		# Expects rev_id as input and returns verification_hash(required),
		# signature(optional), public_key(optional), wallet_address(optional),
		# witness_id(optional)

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$row = $dbr->selectRow(
			'revision_verification',
			[
				'rev_id',
				'domain_id',
				'verification_hash',
				'time_stamp',
				'signature',
				'public_key',
				'wallet_address',
				'witness_event_id'
			],
			[ 'rev_id' => $rev_id ],
			__METHOD__
		);

		if ( !$row ) {
			throw new HttpException( "rev_id not found in the database", 404 );
		}

		$output = [
			'rev_id' => $rev_id,
			'domain_id' => $row->domain_id,
			'verification_hash' => $row->verification_hash,
			'time_stamp' => $row->time_stamp,
			'signature' => $row->signature,
			'public_key' => $row->public_key,
			'wallet_address' => $row->wallet_address,
			'witness_event_id' => $row->witness_event_id,
		];
		return $output;
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'rev_id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
