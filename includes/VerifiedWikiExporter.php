<?php

namespace MediaWiki\Extension\Example;

use DOMDocument;
use SimpleXMLElement;
use XmlDumpWriter;
use MediaWiki\MediaWikiServices;

use WikiExporter;
use Title;

function convertArray2XMLString($arr, $tag) {
	$xml = new SimpleXMLElement($tag);
	$flipped = array_flip(array_filter($arr));
	array_walk($flipped, array($xml, 'addChild'));
	// We have to do these steps to ensure there are proper newlines in the XML
	// string.
	$dom = new DOMDocument();
	$dom->loadXML($xml->asXML());
	$dom->formatOutput = true;
	$xmlString = $dom->saveXML();
	// Remove the first line which has 'xml version="1.0"'
	$xmlString = preg_replace('/^.+\n/', '', $xmlString);
	return $xmlString;
}

function getPageMetadataByRevId($rev_id) {
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
            'rev_id = '.$rev_id,
            __METHOD__
    );

    if (!$row) {
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
	$xmlString = convertArray2XMLString($output, "<verification/>");

	//Inject <witness> data in case witness id is present
	if ( !is_null($output['witness_event_id']) ) {
		$wdXmlString = getPageWitnessData(
			$output['witness_event_id'],
			$output['verification_hash'],
		);
		$xmlString = str_replace('</verification>', "\n", $xmlString) . $wdXmlString . "\n</verification>";
	}

	return $xmlString;
}

function getPageWitnessData($witness_event_id, $page_verification_hash) {
	$witness_data = getWitnessData($witness_event_id);
	if (empty($witness_data)) {
		return '';
	}
	$structured_merkle_proof = json_encode(requestMerkleProof($witness_event_id, $page_verification_hash));
	$witness_data["structured_merkle_proof"] = $structured_merkle_proof;
	$xmlString = convertArray2XMLString($witness_data, "<witness/>");
	return $xmlString;
}

class VerifiedWikiExporter extends WikiExporter {
	public function __construct(
		$db,
		$history = self::CURRENT,
		$text = self::TEXT,
		$limitNamespaces = null
	) {
		parent::__construct($db, $history, $text, $limitNamespaces);
		$this->writer = new XmlDumpWriter( $text, self::schemaVersion() );
	}
	protected function outputPageStreamBatch( $results, $lastRow ) {
		$rowCarry = null;
		while ( true ) {
			$slotRows = $this->getSlotRowBatch( $results, $rowCarry );

			if ( !$slotRows ) {
				break;
			}

			// All revision info is present in all slot rows.
			// Use the first slot row as the revision row.
			$revRow = $slotRows[0];

			if ( $this->limitNamespaces &&
				!in_array( $revRow->page_namespace, $this->limitNamespaces ) ) {
				$lastRow = $revRow;
				continue;
			}

			if ( $lastRow === null ||
				$lastRow->page_namespace !== $revRow->page_namespace ||
				$lastRow->page_title !== $revRow->page_title ) {
				if ( $lastRow !== null ) {
					$output = '';
					if ( $this->dumpUploads ) {
						$output .= $this->writer->writeUploads( $lastRow, $this->dumpUploadFileContents );
					}
					$output .= $this->writer->closePage();
					$this->sink->writeClosePage( $output );
				}
				$output = $this->writer->openPage( $revRow );
				// Data accounting modification
				$title = $revRow->page_title;
				// Convert title text to without underscores.
				// See https://www.mediawiki.org/wiki/Manual:PAGENAMEE_encoding
				// TODO we might want to store titles that have been converted
				// via wfEscapeWikiText()?
				// See parser/CoreParserFunctions.php.
				// Also Because the way we store the title is that we use
				// $wikipage->getTitle().
				$titleObj = Title::newFromText( $title );
				$chain_height = getPageChainHeight( $titleObj->getText() );
				$output .= "<data_accounting_chain_height>$chain_height</data_accounting_chain_height>\n";
				// End of Data accounting modification
				$this->sink->writeOpenPage( $revRow, $output );
			}
			$output = $this->writer->writeRevision( $revRow, $slotRows );
			$verification_info = getPageMetadataByRevId($revRow->rev_id);
			$output = str_replace("</revision>", $verification_info . "\n</revision>", $output);
			$this->sink->writeRevision( $revRow, $output );
			$lastRow = $revRow;
		}

		if ( $rowCarry ) {
			throw new LogicException( 'Error while processing a stream of slot rows' );
		}

		return $lastRow;
	}
}

