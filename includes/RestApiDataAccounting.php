<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Example class to echo a path parameter
 */
class RestApiDataAccounting extends SimpleHandler {

	private const VALID_ACTIONS = [ 'get_hash', 'data_input' ];

	/** @inheritDoc */
	public function run( $valueToEcho, $action ) {
		switch ( $action ) {
			case 'get_hash':
				return base64_decode( $valueToEcho );

			case 'data_input':
				$dbw = wfGetDB( DB_MASTER );
				$data = ['hash_content' => "it works!"];
				$dbw->insert('page_verification', $data, __METHOD__);
				return base64_decode( $valueToEcho );

			default:
				return [ ' echo' => $valueToEcho ];
		}
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'value_to_echo' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'text_action' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => self::VALID_ACTIONS,
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
