<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);


/**
 * Extension:DataAccounting Standard Rest API
 */
class RestApiStandard extends SimpleHandler {

	private const VALID_ACTIONS = [ 'verify_page', 'give_page', 'page_all_rev', 'page_last_rev', 'page_last_rev_sig', 'page_all_rev_sig', 'page_all_rev_wittness', 'page_all_rev_sig_witness', 'store_signed_tx', 'store_witnesstx' ];

	/** @inheritDoc */
	public function run( $action, $var1, $var2, $var3, $var4 ) {
		switch ( $action ) {
                        #Expects rev_id as input and returns verification_hash(required), signature(optional), public_key(optional), wallet_address(optiona    l), witness_id(optional)
                        case 'verify_page':

                                $rev_id = $var1;

                                $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
                                $dbr = $lb->getConnectionRef( DB_REPLICA );
                                $res = $dbr->select(
                                'page_verification', 
                                 [ 'rev_id','hash_verification','signature','public_key','wallet_address' ],
                                 'rev_id = '.$var1,
                                __METHOD__
                                );

                                $output = array();
                                foreach( $res as $row ) {
                                        $output['rev_id'] = $rev_id;
                                        $output['verification_hash'] = $row->hash_verification;
                                        $output['signature'] = $row->signature;
                                        $output['public_key'] = $row->public_key;
                                        $output['wallet_address'] = $row->wallet_address;  
                                        break;
                              }
                              return $output;

                        #Expects Revision_ID as input and returns page_title and page_id
                        case 'give_page':
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
                        #IMPORTANT: ADD ' ' TO THE PAGE INPUT NAME
                        #select * from page_verification where page_title = 'Witness' ORDER BY rev_id DESC LIMIT 1;
                        #POTENTIALLY USELESS AS ALL PAGES GET VERIFIED?  
                        case 'page_last_rev':
                                /** Database Query */
                                 $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
                                 $dbr = $lb->getConnectionRef( DB_REPLICA );
                                 $res = $dbr->select(
                                 'page_verification',
                                 [ 'rev_id','page_title','page_id' ],
                                 'page_title='.$var1,
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
                                /** Database Query */
                                 $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
                                 $dbr = $lb->getConnectionRef( DB_REPLICA );
                                 $res = $dbr->select(
                                 'page_verification',
                                 [ 'rev_id','page_title','signature' ],
                                 'page_title='.$var1,
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
                                //Database Query
                                 $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
                                 $dbr = $lb->getConnectionRef( DB_REPLICA );
                                 #INSERT LOOP AND PARSE INTO ARRAY TO GET ALL PAGES 
                                 $res = $dbr->select(
                                 'page_verification',
                                 [ 'rev_id','page_title','page_id' ],
                                 'page_title='.$var1,
                                 __METHOD__,
                                 [ 'ORDER BY' => 'rev_id' ] 
                                  );

                                 $output = '';
                                 foreach( $res as $row ) {
                                 $output = 'Page Title: ' . $row->page_title .' Page_ID: ' . $row->page_id . ' Revision_ID: ' . $row->rev_id;  
                                 }                                 
                                 return [$output];
                                return ['NOT IMPLEMENTED'];

                        #Expects Page Title and returns ALL verified revisions which have been signed
			case 'page_all_rev_sig':
                                return ['NOT IMPLEMENTED'];

                        #Expects Page Title and returns ALL verified revisions which have been witnessed
			case 'page_all_rev_wittness':
                                return ['NOT IMPLEMENTED'];

                        #Expects Page Title and returns ALL verified revisions which have been signed and wittnessed
			case 'page_all_rev_sig_witness':
                                return ['NOT IMPLEMENTED'];
                                
                        #Expects Revision_ID [Required] Signature[Required], Public Key[Required] and Wallet Address[Required] as inputs; Returns a status for success or failure
                        case 'store_signed_tx':
                         /** include functionality to write to database. 
                         * See https://www.mediawiki.org/wiki/Manual:Database_access */
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
                                        'wallet_address' => $wallet_address;
                                    ], 
                                    "rev_id =$rev_id");

                         return ( "Successfully stored Data for Revision[{$var1}] in Database! Data: Signature[{$var2}], Public_Key[{$var3}] and Wallet_Address[{$var4}] "  );
                                 $signature=[];
                                $public_key=[];
                                $wallet_address=[];
                                $rev_id=[];

                        #Expects all required input for the page_witness database: Transaction Hash, Sender Address, List of Pages with witnessed revision
			case 'store_witnesstx':
				return [ "Expects page title: $var1 and returns ALL verified revisions which have been signed and wittnessed"];

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
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
                        'var2' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
                        'var3' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
                        'var4' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}

