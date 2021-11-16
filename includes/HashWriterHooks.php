<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace DataAccounting;

use DatabaseUpdater;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use MediaWiki\Page\Hook\RevisionFromEditCompleteHook;
use MediaWiki\Revision\Hook\RevisionRecordInsertedHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use WikiPage;

require_once 'Util.php';
require_once 'ApiUtil.php';

function calculateMetadataHash( $domainId, $timestamp, $previousVerificationHash = "" ) {
	return getHashSum( $domainId . $timestamp . $previousVerificationHash );
}

function calculateSignatureHash( $signature, $publicKey ) {
	return getHashSum( $signature . $publicKey );
}

function calculateWitnessHash( $domain_manifest_verification_hash, $merkle_root, $witness_network, $witness_tx_hash ) {
	return getHashSum( $domain_manifest_verification_hash . $merkle_root . $witness_network . $witness_tx_hash );
}

function calculateVerificationHash( $contentHash, $metadataHash, $signature_hash, $witness_hash ) {
	return getHashSum( $contentHash . $metadataHash . $signature_hash . $witness_hash );
}

function makeEmptyIfNonce( $x ) {
	return ( $x == "nonce" ) ? "" : $x;
}

function getPageVerificationData( $dbr, $previous_rev_id ) {
	$row = $dbr->selectRow(
		'page_verification',
		[ 'rev_id', 'verification_hash', 'signature', 'public_key', 'wallet_address', 'witness_event_id' ],
		"rev_id = $previous_rev_id",
		__METHOD__
	);
	if ( !$row ) {
		// When $row is empty, we have to construct $output consisting of empty
		// strings.
		return [
			'verification_hash' => "",
			'signature' => "",
			'public_key' => "",
			'wallet_address' => "",
			'witness_event_id' => null,
		];
	}
	$output = [
		'verification_hash' => $row->verification_hash,
		'signature' => $row->signature,
		'public_key' => $row->public_key,
		'wallet_address' => $row->wallet_address,
		'witness_event_id' => $row->witness_event_id
	];
	return $output;
}

class HashWriterHooks implements
	RevisionFromEditCompleteHook,
	RevisionRecordInsertedHook,
	ArticleDeleteCompleteHook,
	PageMoveCompleteHook,
	LoadExtensionSchemaUpdatesHook
{

	// This function updates the dataset wit the correct revision ID, especially important during import.
	// https://github.com/inblockio/DataAccounting/commit/324cd13fadb1daed281c2df454268a7b1ba30fcd
	public function onRevisionRecordInserted( $revisionRecord ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );
		$table_name = 'page_verification';
		$data = [
			'page_title' => $revisionRecord->getPageAsLinkTarget(),
			'page_id' => $revisionRecord->getPageId(),
			'rev_id' => $revisionRecord->getID(),
			'time_stamp' => $revisionRecord->getTimestamp(),
		];

		$dbw->insert( $table_name, $data, __METHOD__ );
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param RevisionRecord $rev
	 * @param int|bool $originalRevId
	 * @param UserIdentity $user
	 * @param string[] &$tags
	 */
	public function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
		/**
		 * We check if a comment 'revision imported' or 'revisions imported' is
		 * present which indicates that we are importing a page, if we import a
		 * page we do not run the hook, we would prefer a upgraded version of
		 * the revision object which allows us to capture the context to make
		 * this hack unnecessary
		 */
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$dbw->update(
			'page_verification',
			DataAccountingFactory::getInstance()->newPageVerificationBuilder()->buildVerificationData( $rev ),
			[ 'rev_id' => $rev->getID() ],
			__METHOD__
		);
	}

	public function onArticleDeleteComplete( $wikiPage, $user, $reason, $id,
		$content, $logEntry, $archivedRevisionCount
	) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );
		$dbw->delete( 'page_verification', [ 'page_id' => $id ], __METHOD__ );
	}

	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		// We use getPrefixedText() so that it uses spaces for the namespace,
		// not underscores.
		$old_title = $old->getPrefixedText();
		$new_title = $new->getPrefixedText();
		echo json_encode( [ $old_title, $new_title ] );
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );
		$dbw->update(
			'page_verification',
			[ 'page_title' => $new_title ],
			[
				'page_title' => $old_title,
				'page_id' => $pageid ],
			__METHOD__
		);
	}

	/**
	 * Register our database schema.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'data_accounting', dirname( __DIR__ ) . '/sql/data_accounting.sql' );
	}
}
