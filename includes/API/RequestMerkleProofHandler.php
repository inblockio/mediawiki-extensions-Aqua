<?php

namespace DataAccounting\API;

use DataAccounting\Verification\WitnessingEngine;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class RequestMerkleProofHandler extends SimpleHandler {
	private WitnessingEngine $witnessingEngine;

	/**
	 * @param WitnessingEngine $witnessingEngine
	 */
	public function __construct( WitnessingEngine $witnessingEngine ) {
		$this->witnessingEngine = $witnessingEngine;
	}

	/** @inheritDoc */
	public function run( $witness_event_id, $revision_verification_hash ) {
		#request_merkle_proof:expects witness_id and revision_verification_hash and
		#returns left_leaf,righ_leaf and successor hash to verify the merkle
		#proof node by node, data is retrieved from the witness_merkle_tree db.
		#Note: in some cases there will be multiple replays to this query. In
		#this case it is required to use the depth as a selector to go through
		#the different layers. Depth can be specified via the $depth parameter;

		$params = $this->getValidatedParams();
		$depth = $params['depth'] ?? null;
		return $this->witnessingEngine->getLookup()->requestMerkleProof(
			$witness_event_id, $revision_verification_hash, $depth
		);
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
			'revision_verification_hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'depth' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
