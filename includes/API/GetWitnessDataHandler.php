<?php

namespace DataAccounting\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use function DataAccounting\getWitnessData;

require_once __DIR__ . "/../ApiUtil.php";

class GetWitnessDataHandler extends SimpleHandler {

	/** @inheritDoc */
	public function run( $witness_event_id ) {
		#Expects 'get_witness_data\'- USES witness_event_id - used to retrieve all required data to execute a witness event (including domain_manifest_verification_hash, merkle_root, network ID or name, witness smart contract address, transaction_id) for the publishing via Metamask'];
		$output = getWitnessData( $witness_event_id );
		if ( empty( $output ) ) {
			throw new HttpException( "Not found", 404 );
		}
		return $output;
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function needsReadAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'witness_event_id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
