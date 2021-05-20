<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace MediaWiki\Extension\Example;

use MediaWiki\Revision\SlotRecord;
use DatabaseUpdater;

function getHashSum($inputStr) {
	return hash("sha3-512", $inputStr);
}

function calculateMetadataHash($timestamp, $previousVerificationHash = "", $signature = "", $publicKey = "") {
	return getHashSum($timestamp . $previousVerificationHash . $signature . $publicKey);
}

function calculateVerificationHash($contentHash, $metadataHash) {
	return getHashSum($contentHash . $metadataHash);
}

function makeEmptyIfNonce($x) {
	return ($x == "nonce") ? "": $x;
}

function getPageVerificationData($dbr, $previous_rev_id) {
	$res = $dbr->select(
		'page_verification',
		[ 'rev_id', 'hash_verification', 'signature', 'public_key' ],
		"rev_id = $previous_rev_id",
		__METHOD__
	);
	$output = array();
	foreach( $res as $row) {
		array_push($output, makeEmptyIfNonce($row->hash_verification));
		array_push($output, makeEmptyIfNonce($row->signature));
		array_push($output, makeEmptyIfNonce($row->public_key));
		break;
	}
	return $output;
}

class HashWriterHooks implements
	\MediaWiki\Page\Hook\RevisionFromEditCompleteHook,
	\MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
{

	public function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
		$dbw = wfGetDB( DB_MASTER );
		$pageContent = $rev->getContent(SlotRecord::MAIN)->serialize();
		$contentHash = getHashSum($pageContent);
		$parentId = $rev->getParentId();
		$metadata = getPageVerificationData($dbw, $parentId);
		$metadataHash = calculateMetadataHash($rev->getTimeStamp(), $metadata[0], $metadata[1], $metadata[2]);
		$data = [
			'page_id' => 2,
			'rev_id' => $rev->getID(),
			'hash_content' => $contentHash,
			'hash_metadata' => $metadataHash,
			'hash_verification' => calculateVerificationHash($contentHash, $metadataHash),
			'signature' => '',
			'public_key' => '',
			'debug' => $rev->getTimeStamp() . ' previous hash verification ' . $metadata[0] . ' signature ' . $metadata[1] . ' public key ' . $metadata[2] . ' content ' . substr($pageContent, 0, 10),
		];
		$dbw->insert('page_verification', $data, __METHOD__);
		/** re-initilizing variables to ensure they do not hold values for the next revision. */
		$rev_id =[];
		$pageContent =[];
		$contentHash =[];
		$parentId =[];
		$metadata =[];
		$metadataHash =[];
		$data =[];	
	}

	/**
	 * Register our database schema.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'data_accounting', dirname( __DIR__ ) . '/sql/data_accounting.sql' );
	}
}
