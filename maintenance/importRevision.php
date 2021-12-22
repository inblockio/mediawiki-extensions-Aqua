<?php

use MediaWiki\MediaWikiServices;

require_once dirname( dirname( dirname( ( __DIR__ ) ) ) ) ."/maintenance/Maintenance.php";

/**
 * This script is mostly for testing, not made for production
 */
class ImportRevision extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'DataAccounting' );
		$this->addOption( 'transfer-entity', 'JSON file holding transfer entity', true );
		$this->addOption( 'transfer-context', 'JSON file holding transfer context', true );
	}

	public function execute() {
		$revisionData = $this->getArrayFromOption( 'transfer-entity' );
		$contextData = $this->getArrayFromOption( 'transfer-context' );

		/** @var \DataAccounting\Transfer\TransferEntityFactory $transferEntityFactory */
		$transferEntityFactory = MediaWikiServices::getInstance()->getService(
			'DataAccountingTransferEntityFactory'
		);
		$transferEntity = $transferEntityFactory->newRevisionEntityFromApiData( $revisionData );
		$transferContext = $transferEntityFactory->newTransferContext( $contextData );
		/** @var \DataAccounting\Transfer\Importer $importer */
		$importer = MediaWikiServices::getInstance()->getService(
			'DataAccountingImporter'
		);
		$importer->importRevision( $transferEntity, $transferContext );
	}

	private function getArrayFromOption( $name ) {
		$entityFile = $this->getOption( $name );
		if ( !file_exists( $entityFile ) ) {
			$this->error( "File $entityFile does not exist" );
		}
		return json_decode( file_get_contents( $entityFile ), 1 );
	}
}

$maintClass = ImportRevision::class;
require_once RUN_MAINTENANCE_IF_MAIN;
