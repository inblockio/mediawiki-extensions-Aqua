<?php

namespace DataAccounting\API;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;


class GetPageLastRevHandler extends SimpleHandler {

	/** @inheritDoc */
	public function run( $page_title ) {
		#Expects Page Title and returns LAST verified revision
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		// TODO use max(rev_id) instead
		$row = $dbr->selectRow(
			'page_verification',
			[ 'rev_id', 'page_title', 'page_id', 'verification_hash' ],
			[ 'page_title' => $page_title ],
			__METHOD__,
			[ 'ORDER BY' => 'rev_id DESC' ]
		);
		if ( !$row ) {
			throw new HttpException( "page_title not found in the database", 404 );
		}
		$output = [
			'page_title' => $row->page_title,
			'page_id' => $row->page_id,
			'rev_id' => $row->rev_id,
			'verification_hash' => $row->verification_hash,
		];
		return $output;
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'page_title' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
