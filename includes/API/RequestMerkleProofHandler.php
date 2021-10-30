<?php

namespace MediaWiki\Extension\Example\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

require_once(__DIR__ . "/../ApiUtil.php");

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

class RequestMerkleProofHandler extends SimpleHandler {
    /** @inheritDoc */
    public function run( $witness_event_id, $page_verification_hash ) {
        #request_merkle_proof:expects witness_id and page_verification hash and
        #returns left_leaf,righ_leaf and successor hash to verify the merkle
        #proof node by node, data is retrieved from the witness_merkle_tree db.
        #Note: in some cases there will be multiple replays to this query. In
        #this case it is required to use the depth as a selector to go through
        #the different layers. Depth can be specified via the $depth parameter; 

        $params = $this->getValidatedParams();
        $depth = $params['depth'];
        
        $output = \MediaWiki\Extension\Example\requestMerkleProof($witness_event_id, $page_verification_hash, $depth);
        return $output;
    }

    /** @inheritDoc */
    public function needsWriteAccess() {
        return false;
    }

    /** @inheritDoc */
    public function getParamSettings() {
        return [
            'witness_event_id' => [
                self::PARAM_SOURCE => 'path',
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'page_verification_hash' => [
                self::PARAM_SOURCE => 'path',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'depth' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'integer',
                ParamValidator::PARAM_REQUIRED => false,
            ],
        ];
    }
}
