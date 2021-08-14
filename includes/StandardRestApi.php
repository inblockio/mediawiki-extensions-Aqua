<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

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

/**
 * Extension:DataAccounting Standard Rest API
 */
class StandardRestApi extends SimpleHandler {

    private const VALID_ACTIONS = [ 
        'verify_page', 
        'get_page_by_rev_id', 
        'page_all_rev', 
        'get_page_last_rev', 
        'get_witness_data', 
        'request_merkle_proof', 
        'store_signed_tx', 
        'store_witness_tx', 
        'request_hash' 
    ];

    /** @inheritDoc */
    public function run( $action ) {
        $params = $this->getValidatedParams();
        $var1 = $params['var1'];
        $var2 = $params['var2'] ?? null;
        $var3 = $params['var3'] ?? null;
        $var4 = $params['var4'] ?? null;
        switch ( $action ) {
            #Expects rev_id as input and returns verification_hash(required), signature(optional), public_key(optional), wallet_address(optional), witness_id(optional)
                case 'verify_page':

            $rev_id = $var1;

            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );
            $row = $dbr->selectRow(
                'page_verification', 
                [
                    'rev_id',
                    'domain_id',
                    'hash_verification',
                    'time_stamp',
                    'signature',
                    'public_key',
                    'wallet_address',
                    'witness_event_id'
                ],
                ['rev_id' => $var1],
                __METHOD__
            );

            if (!$row) {
                return [];
            }

            $output = [
                'rev_id' => $rev_id,
                'domain_id' => $row->domain_id,
                'verification_hash' => $row->hash_verification,
                'time_stamp' => $row->time_stamp,
                'signature' => $row->signature,
                'public_key' => $row->public_key,
                'wallet_address' => $row->wallet_address,
                'witness_event_id' => $row->witness_event_id,
            ];
            return $output;

            #Expects Revision_ID as input and returns page_title and page_id
        case 'get_page_by_rev_id':
            /** Database Query */ 
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );
            $res = $dbr->select(
                'page_verification',
                [ 'rev_id','page_title','page_id' ],
                'rev_id = '.$var1,
                __METHOD__
            );

            $output = '';
            foreach( $res as $row ) {
                $output = 'Page Title: ' . $row->page_title .' Page_ID: ' . $row->page_id;  
            }
            return $output;

            #Expects Page Title and returns LAST verified revision
            #select * from page_verification where page_title = 'Witness' ORDER BY rev_id DESC LIMIT 1;
            #POTENTIALLY USELESS AS ALL PAGES GET VERIFIED?  
        case 'get_page_last_rev':
            $page_title = $var1;
            /** Database Query */
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );
            // TODO use max(rev_id) instead
            $res = $dbr->select(
                'page_verification',
                [ 'rev_id', 'page_title', 'page_id' ],
                [ 'page_title' => $page_title ],
                __METHOD__,
                [ 'ORDER BY' => 'rev_id' ] 
            );

            $output = json_decode("{}");
            foreach( $res as $row ) {
                $output = [
                    'page_title' => $row->page_title,
                    'page_id' => $row->page_id,
                    'rev_id' => $row->rev_id,
                ];
            }
            return $output;

            #Expects Page Title and returns ALL verified revisions
            #NOT IMPLEMENTED
        case 'page_all_rev':
            $page_title = $var1;
            return get_page_all_rev($page_title);

            #request_merkle_proof:expects witness_id and page_verification hash and returns left_leaf,righ_leaf and successor hash to verify the merkle proof node by node, data is retrieved from the witness_merkle_tree db. Note: in some cases there will be multiple replays to this query. In this case it is required to use the depth as a selector to go through the different layers. Depth can be specified via the $depth parameter; 
        case 'request_merkle_proof':
            if ($var1 == null) {
                return "var1 (/witness_event_id) is not specified but expected";                
            }
            if ($var2 === null) {
                return "var2 (page_verification_hash) is not specified but expected";
            }
            //Redeclaration
            $witness_event_id = $var1;
            $page_verification_hash = $var2;
            $depth = $var3;
            $output = requestMerkleProof($witness_event_id, $page_verification_hash, $depth);
            return [json_encode($output)];

            #Expects 'get_witness_data\'- USES witness_event_id - used to retrieve all required data to execute a witness event (including domain_manifest_verification_hash, merkle_root, network ID or name, witness smart contract address, transaction_id) for the publishing via Metamask'];
        case 'get_witness_data':
            if ($var1 === null) {
                return "var1 (witness_event_id) is not specified but expected";
            }
            $witness_event_id = $var1;
            $output = getWitnessData($witness_event_id);
                                 
            return $output;

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

            /** write data to database */
            #$dbw->insert($table ,[$field => $data,$field_two => $data_two], __METHOD__);

            $dbw->update( $table, 
                [
                    'signature' => $signature, 
                    'public_key' => $public_key, 
                    'wallet_address' => $wallet_address,
                    'signature_hash' => $signature_hash,
                ], 
                "rev_id =$rev_id");

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
                [ 'page_manifest_verification_hash', 'merkle_root', 'witness_network' ],
                [ 'witness_event_id' => $witness_event_id ]
            );
            $witness_hash = getHashSum(
                $row->page_manifest_verification_hash .
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

            return ( "Successfully stored data for witness_event_id[{$witness_event_id}] in Database[$table]! Data: account_address[{$account_addres}], witness_event_transaction_hash[{$transaction_hash}]"  );

        case 'request_hash':
            $rev_id = $var1;
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );

            $res = $dbr->select(
            'page_verification',
            [ 'rev_id','hash_verification' ],
                'rev_id = ' . $rev_id,
            __METHOD__
            );

            $output = '';
            foreach( $res as $row ) {
                $output .= 'I sign the following page verification_hash: [0x' . $row->hash_verification .']';
            }
            return $output;

        default:
            return [ 'HIT ACTION DEFAULT - EXIT' ];
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

