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

function generateDomainId() {
    //*todo* import public key via wizard instead of autogenerating random
    //value
    $randomval = '';
    for( $i=0; $i<10; $i++ ) {
        $randomval .= chr(rand(65, 90));
    }
    $domain_id = getHashSum($randomval);
    //print $domain_id;
    return substr($domain_id, 0, 10);
}

function getDomainId() {
    $domain_id_filename = 'domain_id.txt';
    if (!file_exists($domain_id_filename)) {
        $domain_id = generateDomainId();
        $myfile = fopen($domain_id_filename, "w");
        fwrite($myfile, $domain_id);
        fclose($myfile);
    } else {
        //*todo* validate domain_id
        $domain_id = file_get_contents($domain_id_filename);
    }
    return $domain_id;
}

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
        array_push($output, makeEmptyIfNonce($row->wallet_address));
        break;
    }
    return $output;
}

class HashWriterHooks implements
    \MediaWiki\Page\Hook\RevisionFromEditCompleteHook,
    \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
{

    public function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
        /** 
         * We check if a comment 'revision imported' or 'revisions imported' is
         * present which indicates that we are importing a page, if we import a
         * page we do not run the hook, we would prefer a upgraded version of
         * the revision object which allows us to capture the context to make
         * this hack unnecessary
         */
        $comment = $rev->getComment()->text;
        if (strpos($comment, 'revision imported') || strpos($comment, 'revisions imported')) {
            return;
        }
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
            'debug' => $rev->getTimeStamp().'[PV]'.$metadata[0].'[SIG]'.$metadata[1].'[PK]'.$metadata[2].'[Comment]'.$rev->getComment()->text.'[Domain_ID]'.getDomainId()
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
