<?php

use DataAccounting\Config\Handler;
use DataAccounting\HashLookup;
use DataAccounting\TransclusionManager;
use MediaWiki\MediaWikiServices;

return [
	'DataAccountingConfigHandler' => static function ( MediaWikiServices $services ): Handler {
		return new Handler(
			$services->getDBLoadBalancer()
		);
	},
	'DataAccountingHashLookup' => static function( MediaWikiServices $services ): HashLookup {
		return new HashLookup(
			$services->getDBLoadBalancer(),
			$services->getRevisionStore()
		);
	},
	'DataAccountingTransclusionManager' => static function( MediaWikiServices $services ): TransclusionManager {
		return new TransclusionManager(
			$services->getTitleFactory(),
			$services->get( 'DataAccountingHashLookup' ),
			$services->getPageUpdaterFactory(),
			$services->getWikiPageFactory()
		);
	}
];
