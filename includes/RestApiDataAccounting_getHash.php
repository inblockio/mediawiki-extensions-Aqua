<?php
namespace MediaWiki\Extension\Example;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

class RestApiDataAccounting_getHash extends SimpleHandler {

	/** @inheritDoc */
	public function run( $rev_id ) {

		/**
		 * $dbr = $lb->getConnectionRef( DB_REPLICA );
		 * $res = $dbr->select(
		 * 'category',                              // $table The table to query FROM (or array of tables
		 * [ 'cat_title', 'cat_pages' ],            // $vars (columns of the table to SELECTED
		 * 'cat_pages > 0',                         // $conds (The WHERE conditions)
		 * __METHOD__,                              // $fname The current __METHOD__ (for performance tracking)
		 * [ 'ORDER BY' => 'cat_title ASC' ]        // $options = []
		 * );
		 * This example corresponds to the query
		 * SELECT cat_title, cat_pages FROM category WHERE cat_pages > 0 ORDER BY cat_title ASC
		 * **/

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
        	$output .= 'Revision_ID ' . $row->rev_id . 'Page verification hash : ' . $row->hash_verification;
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
