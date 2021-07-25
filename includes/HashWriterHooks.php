<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace MediaWiki\Extension\Example;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

use MediaWiki\Revision\SlotRecord;
use DatabaseUpdater;
use MediaWiki\MediaWikiServices;

require_once('Util.php');

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
        [ 'rev_id', 'hash_verification', 'signature', 'public_key', 'wallet_address' ],
        "rev_id = $previous_rev_id",
        __METHOD__
    );
    $output = array();
    foreach( $res as $row) {
        array_push($output, makeEmptyIfNonce($row->hash_verification));
        array_push($output, makeEmptyIfNonce($row->signature));
        array_push($output, makeEmptyIfNonce($row->public_key));
        array_push($output, makeEmptyIfNonce($row->wallet_address));
        break;
    }
    if (empty($output)) {
        // When $res is empty, we have to construct $output consisting of empty
        // strings.
        return ["", "", "", ""];
    }
    return $output;
}

class HashWriterHooks implements
    \MediaWiki\Page\Hook\RevisionFromEditCompleteHook,
    \MediaWiki\Revision\Hook\RevisionRecordInsertedHook,
    \MediaWiki\Page\Hook\ArticleDeleteCompleteHook,
    \MediaWiki\Hook\PageMoveCompleteHook,
    \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
{

    public function onRevisionRecordInserted( $revisionRecord ) {
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbw = $lb->getConnectionRef( DB_MASTER );
        $table_name = 'page_verification';
        $data = [
            'page_title' => $revisionRecord->getPageAsLinkTarget(),
            'page_id' => $revisionRecord->getPageId(),
            'rev_id' => $revisionRecord->getID(),
            'time_stamp' => $revisionRecord->getTimestamp(),
            'debug' => "RevisionRecordInserted",
        ];

        $dbw->insert($table_name, $data, __METHOD__);
    }

    public function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
        /** 
         * We check if a comment 'revision imported' or 'revisions imported' is
         * present which indicates that we are importing a page, if we import a
         * page we do not run the hook, we would prefer a upgraded version of
         * the revision object which allows us to capture the context to make
         * this hack unnecessary
         */

        $comment = $rev->getComment()->text;
        //if (strpos($comment, 'revision imported') || strpos($comment, 'revisions imported')) {
        //    // If we are here it means we are importing from an XML file.
        //    return;
        //}
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbw = $lb->getConnectionRef( DB_MASTER );
        $pageContent = $rev->getContent(SlotRecord::MAIN)->serialize();
        $contentHash = getHashSum($pageContent);
        $parentId = $rev->getParentId();
        $metadata = getPageVerificationData($dbw, $parentId);
        $timestamp = $rev->getTimeStamp();
        $metadataHash = calculateMetadataHash($timestamp, $metadata[0], $metadata[1], $metadata[2]);
        $data = [
            'domain_id' => getDomainId(),
            'page_title' => $wikiPage->getTitle(),
            'page_id' => $wikiPage->getId(),
            'rev_id' => $rev->getID(),
            'hash_content' => $contentHash,
            'time_stamp' => $timestamp,
            'hash_metadata' => $metadataHash,
            'hash_verification' => calculateVerificationHash($contentHash, $metadataHash),
            'signature' => '',
            'public_key' => '',
            'wallet_address' => '',
            'source' => 'default',
            'debug' => $rev->getTimeStamp().'[PV]'.$metadata[0].'[SIG]'.$metadata[1].'[PK]'.$metadata[2].'[Comment]'.$rev->getComment()->text.'[Domain_ID]'.getDomainId()
        ];
        $dbw->update('page_verification', $data, ['rev_id' => $rev->getID()], __METHOD__);
        /** re-initilizing variables to ensure they do not hold values for the next revision. */
        $rev_id =[];
        $pageContent =[];
        $contentHash =[];
        $parentId =[];
        $metadata =[];
        $metadataHash =[];
        $data =[];
    }

    public function onArticleDeleteComplete( $wikiPage, $user, $reason, $id,
		$content, $logEntry, $archivedRevisionCount
    ) {
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbw = $lb->getConnectionRef( DB_MASTER );
        $dbw->delete( 'page_verification', [ 'page_id' => $id ], __METHOD__ );
    }

	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
        $old_title = $old->getText();
        $new_title = $new->getText();
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbw = $lb->getConnectionRef( DB_MASTER );
        $dbw->update(
            'page_verification',
            ['page_title' => $new_title],
            ['page_title' => $old_title,
             'page_id' => $pageid],
            __METHOD__
        );
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
