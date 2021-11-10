<?php

namespace DataAccounting\API;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

# include / exclude for debugging
error_reporting( E_ALL );
ini_set( "display_errors", 1 );

class GetPageByRevIdHandler extends SimpleHandler {

	/** @inheritDoc */
	public function run( $rev_id ) {
		#Expects Revision_ID as input and returns page_title and page_id
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$resRow = $dbr->selectRow(
			'page_verification',
			[ 'rev_id', 'page_title', 'page_id' ],
			[ 'rev_id' => $rev_id ],
			__METHOD__
		);

		if ( !$resRow ) {
			throw new HttpException( "rev_id not found in the database", 404 );
		}

		return [
			'page_title' => $resRow->page_title,
			'page_id' => $resRow->page_id,
		];
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
