<?php

namespace DataAccounting\API;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

use DataAccounting\get_page_all_revs;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once(__DIR__ . "/../ApiUtil.php");

class GetPageAllRevsByGenesisHandler extends SimpleHandler {
    /** @inheritDoc */
    public function run( $genesis_hash ) {
        #Expects Page Title and returns ALL verified revisions
        return \DataAccounting\get_page_all_revs($genesis_hash);
    }

    /** @inheritDoc */
    public function needsWriteAccess() {
        return false;
    }

    /** @inheritDoc */
    public function getParamSettings() {
        return [
            'genesis_hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
            ],
        ];
    }
}
