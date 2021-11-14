<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

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

		$this->getServiceContainer()->getHookContainer()->register(
			'XmlDumpWriterWriteRevision',
			function( \XmlDumpWriter $dumpWriter, string &$output, stdClass $page, string $text, RevisionRecord $revision ) {
				$output .= $this->getPageMetadataByRevId( $revision->getId() );
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

	private function getPageMetadataByRevId( $rev_id ) {
		// This is based on the case of 'verify_page' API call in StandardRestApi.php.
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$row = $dbr->selectRow(
			'page_verification',
			[
				'domain_id',
				'rev_id',
				'verification_hash',
				'time_stamp',
				'witness_event_id',
				'signature',
				'public_key',
				'wallet_address' ],
			'rev_id = ' . $rev_id,
			__METHOD__
		);

		if ( !$row ) {
			return '';
		}

		$output = [
			'domain_id' => $row->domain_id,
			'rev_id' => $rev_id,
			'verification_hash' => $row->verification_hash,
			'time_stamp' => $row->time_stamp,
			'witness_event_id' => $row->witness_event_id,
			'signature' => $row->signature,
			'public_key' => $row->public_key,
			'wallet_address' => $row->wallet_address,
		];

		// Convert the $output array to XML string
		$xmlString = $this->convertArray2XMLString( $output, "<verification/>" );

		//Inject <witness> data in case witness id is present
		if ( $output['witness_event_id'] !== null ) {
			$wdXmlString = $this->getPageWitnessData(
				$output['witness_event_id'],
				$output['verification_hash'],
			);
			$xmlString = str_replace( '</verification>', "\n", $xmlString ) . $wdXmlString . "\n</verification>";
		}

		return $xmlString;
	}

	private function getPageWitnessData( $witness_event_id, $page_verification_hash ) {
		$witness_data = getWitnessData( $witness_event_id );
		if ( empty( $witness_data ) ) {
			return '';
		}
		$structured_merkle_proof = json_encode( requestMerkleProof( $witness_event_id, $page_verification_hash ) );
		$witness_data["structured_merkle_proof"] = $structured_merkle_proof;
		$xmlString = $this->convertArray2XMLString( $witness_data, "<witness/>" );
		return $xmlString;
	}

	private function convertArray2XMLString( $arr, $tag ) {
		$xml = new SimpleXMLElement( $tag );
		$flipped = array_flip( array_filter( $arr ) );
		array_walk( $flipped, [ $xml, 'addChild' ] );
		// We have to do these steps to ensure there are proper newlines in the XML
		// string.
		$dom = new DOMDocument();
		$dom->loadXML( $xml->asXML() );
		$dom->formatOutput = true;
		$xmlString = $dom->saveXML();
		// Remove the first line which has 'xml version="1.0"'
		$xmlString = preg_replace( '/^.+\n/', '', $xmlString );
		return $xmlString;
	}

}
