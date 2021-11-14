<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use DataAccounting\RevisionXmlBuilder;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiIntegrationTestCase;
use stdClass;
use WikiExporter;
use function DataAccounting\getPageChainHeight;

/**
 * @group Database
 */
class VerifiedWikiExporterTest extends MediaWikiIntegrationTestCase {

	public function testVerificationDataIsPresentInOutput(): void {
		$exporter = new WikiExporter(
			$this->db
		);

		$output = new \DumpStringOutput();
		$exporter->sink = $output;

		$title = $this->insertPage( 'ExportTestPage' )['title'];

		$exporter->pageByTitle( $title );

		$this->assertStringContainsString(
			'<data_accounting_chain_height>1</data_accounting_chain_height>',
			$output->__toString()
		);

		$this->assertStringContainsString(
			'<verification>',
			$output->__toString()
		);

		$this->assertStringContainsString(
			'<verification_hash>',
			$output->__toString()
		);
	}

}
