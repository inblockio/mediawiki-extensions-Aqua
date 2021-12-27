<?php

declare( strict_types = 1 );

namespace DataAccounting;

use DOMDocument;
use SimpleXMLElement;
use Wikimedia\Rdbms\ILoadBalancer;

use DataAccounting\Verification\WitnessingEngine;

class RevisionXmlBuilder {
	private ILoadBalancer $loadBalancer;
	private WitnessingEngine $witnessingEngine;

	public function __construct(
		ILoadBalancer $loadBalancer,
		WitnessingEngine $witnessingEngine
	) {
		$this->loadBalancer = $loadBalancer;
		$this->witnessingEngine = $witnessingEngine;
	}

	public function getPageMetadataByRevId( int $revId ): string {
		// This is based on the case of 'verify_page' API call in StandardRestApi.php.
		$row = $this->loadBalancer->getConnectionRef( DB_REPLICA )->selectRow(
			'revision_verification',
			[
				'domain_id',
				'genesis_hash',
				'rev_id',
				'verification_hash',
				'previous_verification_hash',
				'time_stamp',
				'witness_event_id',
				'signature',
				'public_key',
				'wallet_address',
				'verification_context',
				'content_hash',
				'metadata_hash',
				'signature_hash'
			],
			[ 'rev_id' => $revId ],
			__METHOD__
		);

		if ( !$row ) {
			return '';
		}

		$output = [
			'domain_id' => $row->domain_id,
			'genesis_hash' => $row->genesis_hash,
			'rev_id' => $revId,
			'verification_hash' => $row->verification_hash,
			'previous_verification_hash' => $row->previous_verification_hash,
			'time_stamp' => $row->time_stamp,
			'witness_event_id' => $row->witness_event_id,
			'signature' => $row->signature,
			'public_key' => $row->public_key,
			'wallet_address' => $row->wallet_address,
			'verification_context' => $row->verification_context,
			'content_hash' => $row->content_hash,
			'metadata_hash' => $row->metadata_hash,
			'signature_hash' => $row->signature_hash,
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

	private function getPageWitnessData( $witness_event_id, $revision_verification_hash ) {
		$witnessEntity = $this->witnessingEngine->getLookup()->witnessEventFromQuery( [
			'witness_event_id' => $witness_event_id
		] );
		if ( $witnessEntity === null ) {
			return '';
		}
		$structured_merkle_proof = $this->witnessingEngine->getLookup()->requestMerkleProof(
			$witness_event_id, $revision_verification_hash
		);
		$witness_data["structured_merkle_proof"] = json_encode( $structured_merkle_proof );
		$xmlString = $this->convertArray2XMLString( $witness_data, "<witness/>" );
		return $xmlString;
	}

	private function convertArray2XMLString( $arr, $tag ) {
		$xml = new SimpleXMLElement( $tag );
		$filtered = array_filter( $arr );
		foreach ( $filtered as $key => $value ) {
			$xml->addChild( $key, (string)$value );
		}
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
