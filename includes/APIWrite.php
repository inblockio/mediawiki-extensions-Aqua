<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

use Title;
use WikitextContent;
use WikiPage;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once("ApiUtil.php");
require_once("Util.php");

function selectToArray($db, $table, $col, $conds) {
    $out = array();
    $res = $db->select(
        $table,
        [$col],
        $conds,
    );
    foreach ($res as $row) {
        array_push($out, $row->{$col});
    }
    return $out;
}

// TODO move to Util.php
function updateDomainManifest($witness_event_id, $db) {
    $row = $db->selectRow(
        'witness_events',
        [
            "domain_id",
            "domain_manifest_title",
            "domain_manifest_verification_hash",
            "merkle_root",
            "witness_event_verification_hash",
            "witness_network",
            "smart_contract_address",
            "witness_event_transaction_hash",
            "sender_account_address",
        ],
        [ 'witness_event_id' => $witness_event_id ]
    );
    if (!$row) {
        return;
    }
    $dm = "Domain Manifest $witness_event_id";
    if ( ('Data Accounting:' . $dm) !== $row->domain_manifest_title) {
        return;
    }
    //6942 is custom namespace. See namespace definition in extension.json.
    $title = Title::newFromText( $dm, 6942 );
    $page = new WikiPage( $title );
    $text = "\n<h1> Witness Event Publishing Data </h1>\n";
    $text .= "<p> This means, that the Witness Event Verification Hash has been written to a Witness Network and has been Timestamped.\n";

    $text .= "* Witness Event: " . $witness_event_id . "\n";
    $text .= "* Domain ID: " . $row->domain_id . "\n";
    $text .= "* Domain Manifest Title: " . $row->domain_manifest_title . "\n";
    // We don't include witness hash.
    $text .= "* Page Domain Manifest verification Hash: " . $row->domain_manifest_verification_hash . "\n";
    $text .= "* Merkle Root: " . $row->merkle_root . "\n";
    $text .= "* Witness Event Verification Hash: " . $row->witness_event_verification_hash . "\n";
    $text .= "* Witness Network: " . $row->witness_network . "\n";
    $text .= "* Smart Contract Address: " . $row->smart_contract_address . "\n";
    $text .= "* Transaction Hash: " . $row->witness_event_transaction_hash . "\n";
    $text .= "* Sender Account Address: " . $row->sender_account_address . "\n";
    // We don't include source.

    $pageText = $page->getContent()->getText();
    // We create a new content using the old content, and append $text to it.
    $newContent = new WikitextContent($pageText . $text);
    $page->doEditContent( $newContent,
        "Domain Manifest witnessed" );
}

/**
 * Extension:DataAccounting Standard Rest API
 */
class StandardRestApi extends SimpleHandler {

    private const VALID_ACTIONS = [ 
        'store_signed_tx', 
        'store_witness_tx', 
    ];

    /** @inheritDoc */
    public function run( $action ) {
        $params = $this->getValidatedParams();
        $var1 = $params['var1'];
        $var2 = $params['var2'] ?? null;
        $var3 = $params['var3'] ?? null;
        $var4 = $params['var4'] ?? null;
        switch ( $action ) {
   
            #Expects Revision_ID [Required] Signature[Required], Public Key[Required] and Wallet Address[Required] as inputs; Returns a status for success or failure
        case 'store_signed_tx':
            /** include functionality to write to database. 
             * See https://www.mediawiki.org/wiki/Manual:Database_access */
            if ($var2 === null) {
                return "var2 is not specified";
            }
            if ($var3 === null) {
                return "var3 is not specified";
            }
            if ($var4 === null) {
                return "var4 is not specified";
            }
            $rev_id = $var1;
            $signature = $var2;
            $public_key = $var3;
            $wallet_address = $var4;

            //Generate signature_hash
            $signature_hash = getHashSum($signature . $public_key);

            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbw = $lb->getConnectionRef( DB_MASTER );
            $table = 'page_verification';

            $dbw->update( $table, 
                [
                    'signature' => $signature, 
                    'public_key' => $public_key, 
                    'wallet_address' => $wallet_address,
                    'signature_hash' => $signature_hash,
                ], 
                ["rev_id" => $rev_id]);

            //TODO return proper API status code
            return ( "Successfully stored Data for Revision[{$var1}] in Database! Data: Signature[{$var2}], Public_Key[{$var3}] and Wallet_Address[{$var4}] "  );

            #Expects all required input for the page_witness database: Transaction Hash, Sender Address, List of Pages with witnessed revision
        case 'store_witness_tx':
            /** include functionality to write to database. 
             * See https://www.mediawiki.org/wiki/Manual:Database_access */
            if ($var1 == null) {
                return "var1 (/witness_event_id) is not specified but expected";                
            }
            if ($var2 === null) {
                return "var2 (account_address) is not specified but expected";
            }
            if ($var3 === null) {
                return "var3 (transaction_hash) is not specified but expected";
            }

            //Redeclaration
            $witness_event_id = $var1;
            $account_address = $var2;
            $transaction_hash = $var3;

            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbw = $lb->getConnectionRef( DB_MASTER );

            $table = 'witness_events';

            $verification_hashes = selectToArray(
                $dbw,
                'witness_page',
                'page_verification_hash',
                [ 'witness_event_id' => $witness_event_id ]
            );

            // If witness ID exists, don't write witness_id, if it does not
            // exist update with witness id as oldest witness event has biggest
            // value (proof of existence)
            foreach ($verification_hashes as $vh) {
                $row = $dbw->selectRow(
                    'page_verification',
                    [ 'witness_event_id' ],
                    [ 'hash_verification' => $vh ]
                );
                if (is_null($row->witness_event_id)) {
                    $dbw->update(
                        'page_verification',
                        [ 'witness_event_id' => $witness_event_id ],
                        [ 'hash_verification' => $vh ]
                    );
                }
            }

            // Generate the witness_hash
            $row = $dbw->selectRow(
                'witness_events',
                [ 'domain_manifest_verification_hash', 'merkle_root', 'witness_network' ],
                [ 'witness_event_id' => $witness_event_id ]
            );
            $witness_hash = getHashSum(
                $row->domain_manifest_verification_hash .
                $row->merkle_root .
                $row->witness_network .
                $transaction_hash
            );
            /** write data to database */
            // Write the witness_hash into the witness_events table
            $dbw->update( $table,
                [
                    'sender_account_address' => $account_address,
                    'witness_event_transaction_hash' => $transaction_hash,
                    'source' => 'default',
                    'witness_hash' => $witness_hash,
                ],
                "witness_event_id = $witness_event_id");

            // Update the domain manifest
            updateDomainManifest($witness_event_id, $dbw);

            return ( "Successfully stored data for witness_event_id[{$witness_event_id}] in Database[$table]! Data: account_address[{$account_address}], witness_event_transaction_hash[{$transaction_hash}]"  );

        default:
            //TODO Return correct error code https://www.mediawiki.org/wiki/API:REST_API/Reference#PHP_3
            return 'ERROR: Invalid action';
        }
    }

    /** @inheritDoc */
    public function needsWriteAccess() {
        return false;
    }

    /** @inheritDoc */
    public function getParamSettings() {
        return [
            'action' => [
                self::PARAM_SOURCE => 'path',
                ParamValidator::PARAM_TYPE => self::VALID_ACTIONS,
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'var1' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'var2' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'var3' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'var4' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
        ];
    }
}

