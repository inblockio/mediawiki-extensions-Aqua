<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once("ApiUtil.php");

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

    private const VALID_ACTIONS = ['help', 'verify_page', 'get_page_by_rev_id', 'page_all_rev', 'page_last_rev', 'page_last_rev_sig', 'page_all_rev_sig', 'get_witness_data', 'request_merkle_proof', 'store_signed_tx', 'store_witness_tx', 'request_hash' ];

    /** @inheritDoc */
    public function run( $action ) {
        $params = $this->getValidatedParams();
        $var1 = $params['var1'];
        $var2 = $params['var2'] ?? null;
        $var3 = $params['var3'] ?? null;
        $var4 = $params['var4'] ?? null;
        switch ( $action ) {
            #Expects rev_id as input and returns verification_hash(required), signature(optional), public_key(optional), wallet_address(optional), witness_id(optional)
        case 'help':
            $index = (int) $var1;
            $output = array();
            $output[0]=["help expects an number as in input to displays information for all actions this API services.To use an action, replace 'help' in your url with your desired action. ensure you provide all four '\' as they are the separators for up to four input variables. actions: help[0], verify_page[1], get_page_by_rev_id[2], page_last_rev[3], page_last_rev_sig[4], page_all_rev[5], page_all_rev_sig[6], request_merkle_proof[7], get_witness_data[8], store_signed_tx[9], store_witness_tx[10]"];
            $output[1]=['action \'verify_page\': expects revision_id as input and returns verification_hash(required), signature(optional), public_key(optional), wallet_address(optional), witness_id(optional)'];
            $output[2]=['action \'get_page_by_rev_id\': expects revision_id as input and returns page_title and page_id'];
            $output[3]=['action \'page_last_rev\': expects page_title and returns last verified revision.'];
            $output[4]=['action \'page_lage_rev_sig\': expects page_title as input and returns last signed and verified revision_id.'];
            $output[5]=['action \'page_all_rev\': expects page_title as input and returns last signed and verified revision_id.'];
            $output[6]=['action \'page_all_rev_sig\':NOT IMPLEMENTED'];
            $output[7]=['action \'request_merkle_proof\':expects witness_id and page_verification hash and returns left_leaf,righ_leaf and successor hash to verify the merkle proof node by node, data is retrieved from the witness_merkle_tree db. Note: in some cases there will be multiple replays to this query. In this case it is required to use the depth as a selector to go through the different layers. Depth can be specified via the $depth parameter'];
            $output[8]=['action \'get_witness_data\' - expects page_witness_id - used to retrieve all required data to execute a witness event (including witness_event_verification_hash, network ID or name, witness smart contract address) for the publishing via Metamask'];
            $output[9]=['action \'store_signed_tx\':expects revision_id=value1 [required] signature=value2[required], public_key=value3[required] and wallet_address=value4[required] as inputs; Returns a status for success or failure
                '];
            $output[10]=['action \'store_witness_tx\' expects witness id: $var1; account_address:$var2; transaction_id:$var3 and returns success or error code, used to receive data from metamask'];


            return $output[$index];

        case 'verify_page':

            $rev_id = $var1;

            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );
            $res = $dbr->select(
                'page_verification', 
                [ 'rev_id','hash_verification','time_stamp','signature','public_key','wallet_address' ],
                'rev_id = '.$var1,
                __METHOD__
            );

            $output = array();
            foreach( $res as $row ) {
                $output['rev_id'] = $rev_id;
                $output['verification_hash'] = $row->hash_verification;
                $output['time_stamp'] = $row->time_stamp;
                $output['signature'] = $row->signature;
                $output['public_key'] = $row->public_key;
                $output['wallet_address'] = $row->wallet_address;  
                break;
            }
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
        case 'page_last_rev':
            $page_title = $var1;
            /** Database Query */
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );
            $res = $dbr->select(
                'page_verification',
                [ 'rev_id','page_title','page_id' ],
                'page_title= \''.$page_title.'\'',
                __METHOD__,
                [ 'ORDER BY' => 'rev_id' ] 
            );

            $output = '';
            foreach( $res as $row ) {
                $output = 'Page Title: ' . $row->page_title .' Page_ID: ' . $row->page_id . ' Revision_ID: ' . $row->rev_id;  
            }                                 
            return [$output];

            #Expects Page Title Name as INPUT and RETURNS LAST signed (and verified) revision.
            #Does not work as expected - Shows signature but also shows the query if no signature is there.
        case 'page_last_rev_sig':
            #select * from page_verification where page_title='Main Page' AND signature <> '' ORDER BY rev_id DESC;
            $page_title = $var1;
            /** Database Query */
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );
            $res = $dbr->select(
                'page_verification',
                [ 'rev_id','page_title','signature' ],
                'page_title=\''.$page_title.'\'',
                __METHOD__,
                [ 'ORDER BY' => 'signature' ] 
            );

            $output = '';
            foreach( $res as $row ) {
                $output = 'Page Title: ' . $row->page_title .' Signature: ' . $row->signature . ' Revision_ID: ' . $row->rev_id;  
            }                                 
            return [$output];

            #Expects Page Title and returns ALL verified revisions
            #NOT IMPLEMENTED
        case 'page_all_rev':
            $page_title = $var1;
            return get_page_all_rev($page_title);

            #Expects Page Title and returns ALL verified revisions which have been signed
        case 'page_all_rev_sig':
            return ['NOT IMPLEMENTED'];

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

            //IF query returns a left or right leaf empty, it means the successor string will be identifical the next layer up. In this case it is required to read the depth and start the query with a depth parameter -1 to go to the next layer. This is repeated until the left or right leaf is present and the successor hash different.

            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );

            if ($depth === null) {
                $res = $dbr->select(
                    'witness_merkle_tree',
                    [ 'witness_event_id', 'depth', 'left_leaf', 'right_leaf', 'successor' ],
                    'left_leaf=\'' . $page_verification_hash . '\' AND witness_event_id=' . $witness_event_id. ' 
                    OR right_leaf=\'' . $page_verification_hash . '\' AND witness_event_id=' . $witness_event_id);

                foreach( $res as $row ) {
                    $output .= 
                        ' Witness Event ID: ' . $row->witness_event_id . 
                        ' Depth ' . $row->depth .
                        ' Left Leaf: ' . $row->left_leaf . 
                        ' Right Leaf: ' . $row->right_leaf . 
                        ' Successor: ' . $row->successor;  
                }                                 
                return [$output];
            } else {
                $res = $dbr->select(
                    'witness_merkle_tree',
                    [ 'witness_event_id', 'depth', 'left_leaf', 'right_leaf', 'successor' ],
                    'left_leaf=\''.$page_verification_hash.'\' AND witness_event_id='.$witness_event_id.' AND depth='.$depth.
                    ' OR right_leaf=\''.$page_verification_hash.'\'  AND witness_event_id='.$witness_event_id.' AND depth='.$depth);

                foreach( $res as $row ) {
                    $output .= 
                        ' Witness Event ID: ' . $row->witness_event_id . 
                        ' Depth ' . $row->depth .
                        ' Left Leaf: ' . $row->left_leaf . 
                        ' Right Leaf: ' . $row->right_leaf . 
                        ' Successor: ' . $row->successor;  
                }                                 
                return [$output];
            }
            return true;

            #Expects 'get_witness_data\':NOT IMPLEMENTED - USES page_witness - used to retrieve all required data to execute a witness event (including witness hash, network ID or name, witness smart contract address) for the publishing via Metamask'];
        case 'get_witness_data':
            if ($var1 === null) {
                return "var1 (witness_event_id) is not specified but expected";
            }

            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );

            $res = $dbr->select(
            'witness_events',
            [ 'witness_event_verification_hash','witness_network','smart_contract_address' ],
                'witness_event_id = ' . $var1,
            __METHOD__
            );

            foreach( $res as $row ) {
                $output = 'Witness Event Verification Hash ' . $row->witness_event_verification_hash .' Witness Network: ' . $row->witness_network . ' Smart Contract Address: ' . $row->smart_contract_address;  
            }                                 
            return [$output];

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
                ], 
                "rev_id =$rev_id");

            return ( "Successfully stored Data for Revision[{$var1}] in Database! Data: Signature[{$var2}], Public_Key[{$var3}] and Wallet_Address[{$var4}] "  );
            $signature=[];
            $public_key=[];
            $wallet_address=[];
            $rev_id=[];

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

            /** write data to database */
            #$dbw->insert($table ,[$field => $data,$field_two => $data_two], __METHOD__);

            $dbw->update( $table,
                [
                    'sender_account_address' => $account_address,
                    'witness_event_transaction_hash' => $transaction_hash,
                ],
                "witness_event_id = $witness_event_id");

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

