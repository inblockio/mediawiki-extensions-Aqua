<?php

use MediaWiki\MediaWikiServices;

require_once dirname( dirname( dirname( ( __DIR__ ) ) ) ) ."/maintenance/Maintenance.php";

/**
 * This script is mostly for testing, not made for production
 */
class JsonImport extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'DataAccounting' );
		$this->addOption( 'file', 'File to import', true );
	}

	public function execute() {
		$file = $this->getOption( 'file' );
		if ( !file_exists( $file ) ) {
			$this->error( 'File specified does not exist, or not readable' );
		}
		$contents = file_get_contents( $file );
		$decoded = json_decode( $contents, true );
		if ( !$decoded ) {
			$this->error( 'Failed to decode input file' );
		}
		if ( !isset( $decoded['pages'] ) || !isset( $decoded['siteInfo'] ) ) {
			$this->error( 'File structure invalid' );
		}
		/** @var \DataAccounting\Transfer\TransferEntityFactory $transferEntityFactory */
		$transferEntityFactory = MediaWikiServices::getInstance()->getService(
			'DataAccountingTransferEntityFactory'
		);
		/** @var \DataAccounting\Transfer\Importer $importer */
		$importer = MediaWikiServices::getInstance()->getService(
			'DataAccountingImporter'
		);

		$siteInfo = $decoded['siteInfo'];
		foreach ( $decoded['pages'] as $page ) {
			$this->output( "Processing {$page['title']}...\n" );

			$revisions = $page['revisions'];
			unset( $page['revisions'] );
			$page['site_info'] = $siteInfo;
			$context = $transferEntityFactory->newTransferContextFromData( $page );
			foreach ( $revisions as $revision ) {
				$this->output( "Processing revision {$revision['content']['rev_id']}..." );
				$entity = $transferEntityFactory->newRevisionEntityFromApiData( $revision );
				if ( !$entity instanceof \DataAccounting\Transfer\TransferRevisionEntity ) {
					$this->output( "failed\n" );
					continue;
				}
				$importer->importRevision( $entity, $context );
				$this->output( "done\n" );
			}
		}
	}
}

$maintClass = JsonImport::class;
require_once RUN_MAINTENANCE_IF_MAIN;
