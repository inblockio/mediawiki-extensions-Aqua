<?php

namespace DataAccounting\API;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\SimpleHandler;

use DataAccounting\ServerInfo;

class GetServerInfoHandler extends SimpleHandler {
	/** @inheritDoc */
	public function run(): array {
		$apiVersion = ServerInfo::DA_API_VERSION;
		return [ 'api_version' => $apiVersion ];
	}
}
