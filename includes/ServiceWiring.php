<?php

use DataAccounting\Config\Handler;
use DataAccounting\TransclusionManager;
use DataAccounting\Transfer\Exporter;
use DataAccounting\Transfer\Importer;
use DataAccounting\Transfer\TransferEntityFactory;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationEntityFactory;
use DataAccounting\Verification\VerificationLookup;
use DataAccounting\Verification\WitnessingEngine;
use DataAccounting\Verification\WitnessLookup;
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
	'DataAccountingWitnessingEngine' => static function( MediaWikiServices $services ): WitnessingEngine {
		$lookup = new WitnessLookup( $services->getDBLoadBalancer() );

		return new WitnessingEngine(
			$lookup, $services->getTitleFactory(),
			$services->getWikiPageFactory(),
			$services->getMovePageFactory(),
			$services->getPageUpdaterFactory(),
			$services->getRevisionStore()
		);
	},
	'DataAccountingVerificationEngine' => static function( MediaWikiServices $services ): VerificationEngine {
		$entityFactory = new VerificationEntityFactory( $services->getTitleFactory(), $services->getRevisionStore() );
		$lookup = new VerificationLookup( $services->getDBLoadBalancer(), $services->getRevisionStore(), $entityFactory );
		$config = $services->getConfigFactory()->makeConfig( 'da' );

		return new VerificationEngine(
			$lookup, $services->getDBLoadBalancer(), $config,
			$services->getWikiPageFactory(), $services->getRevisionStore(), $services->getPageUpdaterFactory(),
			$services->get( 'DataAccountingWitnessingEngine' )
		);
	},
	'DataAccountingTransferEntityFactory' => static function( MediaWikiServices $services ): TransferEntityFactory {
		return new TransferEntityFactory(
			$services->getService( 'DataAccountingVerificationEngine' ),
			$services->getService( 'DataAccountingWitnessingEngine' ),
			$services->getTitleFactory(),
			$services->getContentLanguage(),
			$services->getNamespaceInfo()
		);
	},
	'DataAccountingImporter' => static function( MediaWikiServices $services ): Importer {
		return new Importer(
			$services->getService( 'DataAccountingVerificationEngine' ),
			$services->getService( 'DataAccountingWitnessingEngine' ),
			$services->getOldRevisionImporter(),
			$services->getUploadRevisionImporter(),
			$services->getContentHandlerFactory(),
			$services->getRevisionStore()
		);
	},
	'DataAccountingExporter' => static function( MediaWikiServices $services ): Exporter {
		return new Exporter(
			$services->getService( 'DataAccountingTransferEntityFactory' ),
			$services->getService( 'DataAccountingVerificationEngine' )
		);
	},
];
