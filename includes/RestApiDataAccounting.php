<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

class RestApiDataAccounting extends SimpleHandler {

	/** @inheritDoc */
	public function run( $signature, $public_key ) {
			/** include functionality to write to database. 
			* See https://www.mediawiki.org/wiki/Manual:Database_access */
			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbw = $lb->getConnectionRef( DB_MASTER );
			
			$table = 'page_verification';
				
			/** prepare data input */
			$field = 'signature';
			$data = base64_decode( $signature );
			$field_two = 'public_key';
			$data_two = base64_decode( $public_key );

			/** write data to database */
			$dbw->insert($table ,[$field => $data,$field_two => $data_two], __METHOD__);
					
		return ( "Store of Signature {$signature} and Public Key {$public_key} Successful!"  );
		}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'signature' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'public_key' => [
                                self::PARAM_SOURCE => 'path',
                                ParamValidator::PARAM_TYPE => 'string',
                                ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
