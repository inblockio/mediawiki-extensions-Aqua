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

/**
 * Extension:DataAccounting Standard Rest API
 */
class APIRead extends SimpleHandler {

    private const VALID_ACTIONS = [ 
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
            #Expects Revision_ID [Required] Signature[Required], Public Key[Required] and Wallet Address[Required] as inputs; Returns a status for success or failure
               case 'request_hash':
            $rev_id = $var1;
            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbr = $lb->getConnectionRef( DB_REPLICA );

            $res = $dbr->select(
            'page_verification',
            [ 'rev_id','verification_hash' ],
                'rev_id = ' . $rev_id,
            __METHOD__
            );

            $output = '';
            foreach( $res as $row ) {
                $output .= 'I sign the following page verification_hash: [0x' . $row->verification_hash .']';
            }
            return $output;

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

