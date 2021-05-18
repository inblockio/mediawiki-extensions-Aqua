<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

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

			/**
			 *the 'data_input' method will take the base64 string and decode it. After decoding it there should be a string which contains the input data from the Metamask singing process [$Singature,$PublicKey] whic are then writtein into the database page_verification under the respective datafields.
			 */
			case 'data_input':
				#$dbw = wfGetDB( DB_MASTER );
				$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
				$dbw = $lb->getConnectionRef( DB_MASTER );
				$data = ['hash_content' => "New Entry!"];
				
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
