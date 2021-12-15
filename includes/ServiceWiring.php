<?php

use DataAccounting\Config\DataAccountingConfig;
use DataAccounting\Config\Handler;
use DataAccounting\TransclusionManager;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationEntityFactory;
use DataAccounting\Verification\VerificationLookup;
use MediaWiki\MediaWikiServices;

return [
	'DataAccountingConfigHandler' => static function ( MediaWikiServices $services ): Handler {
		return new Handler(
			$services->getDBLoadBalancer()
		);
	},
	'DataAccountingTransclusionManager' => static function( MediaWikiServices $services ): TransclusionManager {
		return new TransclusionManager(
			$services->getTitleFactory(),
			$services->get( 'DataAccountingVerificationEngine' ),
			$services->getRevisionStore(),
			$services->getPageUpdaterFactory(),
			$services->getWikiPageFactory()
		);
	},
	'DataAccountingVerificationEngine' => static function( MediaWikiServices $services ): VerificationEngine {
		$entityFactory = new VerificationEntityFactory( $services->getTitleFactory(), $services->getRevisionStore() );
		$lookup = new VerificationLookup( $services->getDBLoadBalancer(), $services->getRevisionStore(), $entityFactory );
		/** @var DataAccountingConfig $config */
		$config = $services->getConfigFactory()->makeConfig( 'da' );

		return new VerificationEngine( $lookup, $services->getDBLoadBalancer(), $config );
	}
];
