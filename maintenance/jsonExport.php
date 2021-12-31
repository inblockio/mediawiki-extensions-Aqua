<?php

use MediaWiki\MediaWikiServices;

require_once dirname( dirname( dirname( ( __DIR__ ) ) ) ) ."/maintenance/Maintenance.php";

/**
 * This script is mostly for testing, not made for production
 */
class JsonExport extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'DataAccounting' );
		$this->addOption( 'rev', 'Revision id to export' );
		$this->addOption( 'title', 'Title to export revisions for', true );
	}

	public function execute() {
		$title = $this->getTitle();
		if ( !$title ) {
			$this->error( 'Invalid title specified' );
		}

		$exportSpec = new \DataAccounting\Transfer\ExportSpecification();

		$rev = $this->getOption( 'rev' );
		if ( is_numeric( $rev ) && (int)$rev > 0 ) {
			$exportSpec->addTitle( $title, [ $rev ] );
		} else {
			$exportSpec->addTitle( $title );
		}

		/** @var \DataAccounting\Transfer\Exporter $exporter */
		$exporter = MediaWikiServices::getInstance()->getService( 'DataAccountingExporter' );
		$this->output(
			json_encode( $exporter->getExportContents( $exportSpec ), JSON_PRETTY_PRINT )
		);
	}

	/**
	 * @return Title|null
	 */
	private function getTitle(): ?Title {
		$title = $this->getOption( 'title' );
		$titleFactory = MediaWikiServices::getInstance()->getTitleFactory();
		$titleObject = $titleFactory->newFromText( $title );
		if ( !( $titleObject instanceof Title ) || !$titleObject->exists() ) {
			return null;
		}
		return $titleObject;
	}
}

$maintClass = JsonExport::class;
require_once RUN_MAINTENANCE_IF_MAIN;
