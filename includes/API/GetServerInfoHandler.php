<?php

namespace DataAccounting\API;

use DataAccounting\ServerInfo;
use MediaWiki\Rest\SimpleHandler;

class GetServerInfoHandler extends SimpleHandler {
	/** @inheritDoc */
	public function run(): array {
		/** @var string */
		$apiVersion = ServerInfo::DA_API_VERSION;
		return [ 'api_version' => $apiVersion ];
	}
}
