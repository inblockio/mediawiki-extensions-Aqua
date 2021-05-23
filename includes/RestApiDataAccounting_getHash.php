<?php
namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

class RestApiDataAccounting_getHash extends SimpleHandler {

	/** @inheritDoc */
	public function run( $rev_id ) {

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );

		$res = $dbr->select(
		'page_verification', 
		[ 'rev_id','hash_verification' ],
	      	'rev_id = '.$rev_id,
		__METHOD__
		);

		$output = '';
		foreach( $res as $row ) {
        	#$output .= 'Revision[' . $row->rev_id . '] PageVerificationHash[' . $row->hash_verification .']';
		$output = '[' . $row->hash_verification . ']';
		}



		#This code corresponds to the query
		#SELECT page_verification_id, signature, public_key FROM page_verification WHERE page_verification_id = 55 ORDER BY page_verification_id ASC;

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
