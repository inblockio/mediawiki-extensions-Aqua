<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

/**
 * Extension:DataAccounting Standard Rest API
 */
class RestApiStandard extends SimpleHandler {

	private const VALID_ACTIONS = [ 'rev_id', 'give_page', 'page_all_rev', 'page_last_rev', 'page_last_rev_sig', 'page_all_rev_sig', 'page_all_rev_wittness', 'page_all_rev_sig_witness', 'store_sigtx', 'store_witnesstx' ];

	/** @inheritDoc */
	public function run( $action, $var1, $var2, $var3, $var4 ) {
		switch ( $action ) {
                        #Expects rev_id: $var1 as input and returns verification_hash(required), signature(optional), public_key(optional), wallet_address(optiona    l), witness_id(optional)
                        case 'rev_id':
                                 $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
                                 $dbr = $lb->getConnectionRef( DB_REPLICA );
 
                                 $res = $dbr->select(
                                'page_verification', 
                                 [ 'rev_id','hash_verification' ],
                                 'rev_id = '.$var1,
                                __METHOD__
                                );
 
                                $output = '';
                                foreach( $res as $row ) {
                                $output .= '0x' . $row->hash_verification .'';
                                }
                                return $output;

                        #Expects rev_id: $var1 as input and returns page_title and page_id
                        case 'give_page':
				return [ "Expects rev_id: $var1 as input and returns page_title and page_id" ];

                        #Expects page title: $var1 and returns LAST verified revision
                        case 'page_last_rev':
				return [ "Expects page title: $var1 and returns LAST verified revision"];

                        #Expects page title: $var1 and returns LAST verified and signed revision
                        case 'page_last_rev_sig':
				return [ "Expects page title: $var1 and returns LAST verified and signed revision"];

                        #Expects page title: $var1 and returns ALL verified revisions
                        case 'page_all_rev':
				return [ "Expects page title: $var1 and returns ALL verified revisions"];

                        #Expects page title: $var1 and returns ALL verified revisions which have been signed
			case 'page_all_rev_sig':
				return [ "Expects page title: $var1 and returns ALL verified revisions which have been signed"];

                        #Expects page title: $var1 and returns ALL verified revisions which have been witnessed
			case 'page_all_rev_wittness':
				return [ "Expects page title: $var1 and returns ALL verified revisions which have been witnessed"];

                        #Expects page title: '$var1' and returns ALL verified revisions which have been signed and wittnessed
			case 'page_all_rev_sig_witness':
				return [ "Expects page title: $var1 and returns ALL verified revisions which have been signed and wittnessed"];

                                
                        #Expects Revision_ID [Required] Signature[Required], Public Key[Required] and Wallet Address[Required] as inputs; Returns a status for success or failure
                        case 'store_sigtx':
                         /** include functionality to write to database. 
                         * See https://www.mediawiki.org/wiki/Manual:Database_access */
                                $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
                                $dbw = $lb->getConnectionRef( DB_MASTER );
                                
                                $table = 'page_verification';
                                 
                                 /** write data to database */
                                 #$dbw->insert($table ,[$field => $data,$field_two => $data_two], __METHOD__);
        
                                 $dbw->update( $table, ['signature' => $var2, 'public_key' => $var3, 'wallet_address' => $var4 ], "rev_id =$var1");

                         return ( "Successfully stored Data for Revision[{$var1}] in Database! Data: Signature[{$var2}], Public_Key[{$var3}] and Wallet_Address[{$var4}] "  );
                                 $signature=[];
                                $public_key=[];
                                $rev_id=[];


                        #Expects all requied input for the page_witness database: Transaction Hash, Sender Address, List of Pages with witnessed revision
			case 'store_witnesstx':
				return [ "Expects page title: $var1 and returns ALL verified revisions which have been signed and wittnessed"];

			default:
				return [ ' echo' => $valueToEcho ];
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

