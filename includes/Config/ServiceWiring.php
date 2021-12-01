<?php

use DataAccounting\Config\Handler;
use MediaWiki\MediaWikiServices;

return [
	'DataAccountingConfigHandler' => static function ( MediaWikiServices $services ): Handler {
		return new Handler(
			$services->getMainConfig(),
			$services->getDBLoadBalancer()
		);
	}
];
