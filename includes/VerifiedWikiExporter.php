<?php

namespace DataAccounting;

use LogicException;
use MediaWiki\MediaWikiServices;
use Title;
use WikiExporter;
use XmlDumpWriter;

class VerifiedWikiExporter extends WikiExporter {

	private XmlDumpWriter $writer;

	public function __construct(
		$db,
		$history = self::CURRENT,
		$text = self::TEXT,
		$limitNamespaces = null
	) {
		parent::__construct( $db, $history, $text, $limitNamespaces );
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
			$output = str_replace(
				"</revision>",
				$this->getVerificationXml( $revRow->rev_id ) . "\n</revision>",
				$output
			);
			$this->sink->writeRevision( $revRow, $output );
			$lastRow = $revRow;
		}

		if ( $rowCarry ) {
			throw new LogicException( 'Error while processing a stream of slot rows' );
		}

		return $lastRow;
	}

	private function getVerificationXml( $revId ): string {
		$xmlBuilder = new RevisionXmlBuilder(
			MediaWikiServices::getInstance()->getDBLoadBalancer()
		);

		return $xmlBuilder->getPageMetadataByRevId( $revId );
	}

}
