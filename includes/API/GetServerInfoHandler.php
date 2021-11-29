<?php

namespace DataAccounting\API;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\SimpleHandler;

class GetServerInfoHandler extends SimpleHandler {
	/** @inheritDoc */
	public function run(): array {
		$apiVersion = MediaWikiServices::getInstance()->getMainConfig()->get( 'DAAPIVersion' );
		return [ 'api_version' => $apiVersion ];
	}
}
