<?php

namespace DataAccounting\API;

use MediaWiki\Rest\SimpleHandler;

use DataAccounting\ServerInfo;

class GetServerInfoHandler extends SimpleHandler {
	/** @inheritDoc */
	public function run(): array {
		/** @var string */
		$apiVersion = ServerInfo::DA_API_VERSION;
		return [ 'api_version' => $apiVersion ];
	}

	/** @inheritDoc */
	public function needsReadAccess() {
		return false;
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}
}
