<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use MediaWikiIntegrationTestCase;
use WikiExporter;

/**
 * @group Database
 * @covers \DataAccounting\Hooks
 */
class ExportTest extends MediaWikiIntegrationTestCase {

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
