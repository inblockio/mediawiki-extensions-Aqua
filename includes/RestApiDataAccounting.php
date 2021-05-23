<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

class RestApiDataAccounting extends SimpleHandler {

	/** @inheritDoc */
	public function run( $rev_id, $signature, $public_key, $wallet_address ) {
			/** include functionality to write to database. 
			* See https://www.mediawiki.org/wiki/Manual:Database_access */
			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbw = $lb->getConnectionRef( DB_MASTER );
			
			$table = 'page_verification';
				
			$dbw->update( $table, ['signature' => $signature,'public_key' => $public_key, 'wallet_address' => $wallet_address ], "rev_id =$rev_id"); 
		return ( "Signature[{$signature}] and Public_Key[{$public_key}] stored successfully for Revision[$rev_id] in Database!"  );
			$signature=[];
			$public_key=[];
                        $rev_id=[];
                        $wallet_address=[];
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
                         'wallet_address' => [
                                 self::PARAM_SOURCE => 'path',
                                 ParamValidator::PARAM_TYPE => 'string',
                                 ParamValidator::PARAM_REQUIRED => true,
                         ],

		];
	}
}
