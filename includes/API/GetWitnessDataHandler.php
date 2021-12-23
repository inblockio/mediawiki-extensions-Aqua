<?php

namespace DataAccounting\API;

use DataAccounting\Verification\WitnessingEngine;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class GetWitnessDataHandler extends SimpleHandler {
	/** @var WitnessingEngine */
	private $witnessingEngine;

	/**
	 * @param WitnessingEngine $witnessingEngine
	 */
	public function __construct( WitnessingEngine $witnessingEngine ) {
		$this->witnessingEngine = $witnessingEngine;
	}

	/** @inheritDoc */
	public function run( $witness_event_id ) {
		#Expects 'get_witness_data\'- USES witness_event_id - used to retrieve all required data to execute a witness event (including domain_manifest_genesis_hash, merkle_root, network ID or name, witness smart contract address, transaction_id) for the publishing via Metamask'];
		$witnessEntity = $this->witnessingEngine->getLookup()->witnessEventFromQuery( [
			'witness_event_id' => $witness_event_id,
		] );
		if ( $witnessEntity === null ) {
			throw new HttpException( "Not found", 404 );
		}
		return $witnessEntity->jsonSerialize();
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
