<?php

use DataAccounting\Content\TransclusionHashes;
use DataAccounting\Transfer\ExportSpecification;
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
		// If set, all transcluded pages will be included in the export
		// If rev is set, only specific revisions of transcluded pages will be included,
		// otherwise full history of all transcluded pages
		$this->addOption( 'transcluded', 'Whether to include transcluded pages' );
	}

	public function execute() {
		$title = $this->getTitle();
		if ( !$title ) {
			$this->error( 'Invalid title specified' );
		}

		$exportSpec = new ExportSpecification();

		$rev = $this->getOption( 'rev' );
		if ( is_numeric( $rev ) && (int)$rev > 0 ) {
			$exportSpec->addTitle( $title, [ $rev ] );
		} else {
			$exportSpec->addTitle( $title );
		}
		if ( $this->hasOption( 'transcluded' ) ) {
			$this->addTranscluded( $title, $exportSpec, $rev );
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

	private function addTranscluded( Title $title, ExportSpecification &$exportSpec, $rev = null ) {
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		if ( !$rev ) {
			$revision = $revisionStore->getRevisionByTitle( $title );
		} else {
			$revision = $revisionStore->getRevisionById( $rev );
		}

		if ( !$revision || !$revision->hasSlot( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES ) ) {
			$this->output( "Could not retrieve transcluded content\n" );
			return;
		}

		$transclusionContent = $revision->getContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES );
		$titleFactory = MediaWikiServices::getInstance()->getTitleFactory();

		$transclusions = $transclusionContent->getData();
		if ( !$transclusions->isOK() ) {
			$this->output( "Could not retrieve transcluded content\n" );
			return;
		}
		foreach ( $transclusions->getValue() as $transclusion ) {
			$title = $titleFactory->makeTitle( $transclusion->ns, $transclusion->dbkey );
			if ( $rev ) {
				$exportSpec->addTitle( $title, [ $transclusion->revid ] );
			} else {
				$exportSpec->addTitle( $title );
			}
		}
	}
}

$maintClass = JsonExport::class;
require_once RUN_MAINTENANCE_IF_MAIN;
