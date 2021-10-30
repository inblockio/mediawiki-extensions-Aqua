<?php

namespace MediaWiki\Extension\Example\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

class RequestHashHandler extends SimpleHandler {
    /** @inheritDoc */
    public function run( $rev_id ) {
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );

        $row = $dbr->selectRow(
            'page_verification',
            [ 'rev_id','verification_hash' ],
            [ 'rev_id' => $rev_id ],
            __METHOD__
        );

        if (!$row) {
            throw new HttpException("rev_id not found in the database", 404);
        }
        return 'I sign the following page verification_hash: [0x' . $row->verification_hash .']';
    }

    /** @inheritDoc */
    public function needsWriteAccess() {
        return false;
    }

    /** @inheritDoc */
    public function getParamSettings() {
        return [
            'rev_id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
            ],
        ];
    }
}
