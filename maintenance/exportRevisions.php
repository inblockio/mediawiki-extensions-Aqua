<?php

use DataAccounting\Verification\VerificationEntity;
use MediaWiki\MediaWikiServices;

require_once dirname( dirname( dirname( ( __DIR__ ) ) ) ) ."/maintenance/Maintenance.php";

class ExportRevisions extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'DataAccounting' );
		$this->addOption( 'output-dir', 'Output dir for files', true );
		$this->addOption( 'rev', 'Revision id to export' );
		$this->addOption( 'title', 'Title to export revisions for' );
	}

	public function execute() {
		/** @var \DataAccounting\Transfer\TransferEntityFactory $transferEntityFactory */
		$transferEntityFactory = MediaWikiServices::getInstance()->getService(
			'DataAccountingTransferEntityFactory'
		);
		$output = $this->getOption( 'output-dir' );
		if ( !is_writable( $output ) ) {
			$this->error( "$output not writable");
		}
		$entities = $this->getEntities();

		/** @var VerificationEntity $entity */
		foreach( $entities as $entity ) {
			$filename = substr( $entity->getHash( VerificationEntity::VERIFICATION_HASH ), 0, 20 );
			$file = "$output/{$filename}.json";
			$transferEntity = $transferEntityFactory->newRevisionEntityFromVerificationEntity( $entity );
			file_put_contents( $file, json_encode( $transferEntity, JSON_PRETTY_PRINT) );
		}
	}

	private function getEntities() {
		/** @var \DataAccounting\Verification\VerificationEngine $verificationEngine */
		$verificationEngine = MediaWikiServices::getInstance()->getService(
			'DataAccountingVerificationEngine'
		);

		$rev = $this->getOption( 'rev' );
		if ( is_numeric( $rev ) && (int)$rev > 0 ) {
			return [
				$verificationEngine->getLookup()->verificationEntityFromRevId( $rev )
			];
		}
		$title = $this->getOption( 'title' );
		if ( is_string( $title ) ) {
			$revs = $verificationEngine->getLookup()->getAllRevisionIds( $title );
			$entities = [];
			foreach( $revs as $rev ) {
				$entities[] = $verificationEngine->getLookup()->verificationEntityFromRevId( $rev );
			}
			return $entities;
		}

		return $verificationEngine->getLookup()->getAllEntities();
	}
}

$maintClass = ExportRevisions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
