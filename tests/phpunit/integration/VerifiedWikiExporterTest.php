<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use DataAccounting\RevisionXmlBuilder;
use DOMDocument;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiIntegrationTestCase;
use SimpleXMLElement;
use stdClass;
use WikiExporter;
use function DataAccounting\getPageChainHeight;
use function DataAccounting\getWitnessData;
use function DataAccounting\requestMerkleProof;

/**
 * @group Database
 */
class VerifiedWikiExporterTest extends MediaWikiIntegrationTestCase {

	public function testVerificationDataIsPresentInOutput(): void {
		$this->getServiceContainer()->getHookContainer()->register(
			'XmlDumpWriterOpenPage',
			function( \XmlDumpWriter $dumpWriter, string &$output, stdClass $page, \Title $title ) {
				$chain_height = getPageChainHeight( $title->getText() );
				$output .= "<data_accounting_chain_height>$chain_height</data_accounting_chain_height>\n";
			}
		);

		$xmlBuilder = new RevisionXmlBuilder(
			$this->getServiceContainer()->getDBLoadBalancer()
		);

		$this->getServiceContainer()->getHookContainer()->register(
			'XmlDumpWriterWriteRevision',
			function( \XmlDumpWriter $dumpWriter, string &$output, stdClass $page, string $text, RevisionRecord $revision ) use ( $xmlBuilder ) {
				$output .= $xmlBuilder->getPageMetadataByRevId( $revision->getId() );
			}
		);

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
