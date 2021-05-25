<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Example class to echo a path parameter
 */
class RestApiStandard extends SimpleHandler {

	private const VALID_ACTIONS = [ 'rev_id', 'give_page', 'page_all_rev', 'page_last_rev', 'page_last_rev_sig', 'page_all_rev_sig', 'page_all_rev_wittness', 'page_all_rev_sig_witness' ];

	/** @inheritDoc */
	public function run( $action, $var1, $var2 ) {
		switch ( $action ) {
                        #Expects rev_id: $var1 as input and returns verification_hash(required), signature(optional), public_key(optional), wallet_address(optiona    l), witness_id(optional)
                        case 'rev_id':
				return [ "Expects rev_id: $var1 as input and returns verification_hash(required), signature(optional), public_key(optional), wallet_address(optional), witness_id(optional) " ];

                        #Expects rev_id: $var1 as input and returns page_title and page_id
                        case 'give_page':
				return [ "Expects rev_id: $var1 as input and returns page_title and page_id" ];

                        #Expects page title: $var1 and returns LAST verified revision
                        case 'page_last_rev':
				return [ "Expects page title: $var1 and returns LAST verified revision"];

                        #Expects page title: $var1 and returns LAST verified and signed revision
                        case 'page_last_rev_sig':
				return [ "Expects page title: $var1 and returns LAST verified and signed revision"];

                        i#Expects page title: $var1 and returns ALL verified revisions
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
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}

