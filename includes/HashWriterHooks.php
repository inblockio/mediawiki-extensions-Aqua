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
	return getHashSum($timestamp + $previousVerificationHash + $signature + $publicKey);
}

function calculateVerificationHash($contentHash, $metadataHash) {
	return getHashSum($contentHash . $metadataHash);
}

function getPreviousVerificationHash($dbr, $rev_id) {
	$res = $dbr->select(
		'page_verification', 
		[ 'rev_id', 'hash_verification' ],
		"rev_id = $rev_id",
		__METHOD__
	);
	$output = '';
	foreach( $res as $row) {
		$output = $row->hash_verification;
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
		$previousVerificationHash = getPreviousVerificationHash($dbw, $parentId);
		$metadataHash = calculateMetadataHash($rev->getTimeStamp());
		$data = [
			'page_id' => 2,
			'rev_id' => $rev->getID(),
			'hash_content' => $contentHash,
			'hash_metadata' => $metadataHash,
			'hash_verification' => calculateVerificationHash($contentHash, $metadataHash),
			'signature' => $contentHash . $metadataHash,
			'public_key' => $previousVerificationHash,
		];
		$dbw->insert('page_verification', $data, __METHOD__);
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
