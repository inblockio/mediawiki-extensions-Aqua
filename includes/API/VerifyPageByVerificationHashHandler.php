<?php

namespace DataAccounting\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

class VerifyPageHandler extends SimpleHandler {
    /** @inheritDoc */
    public function run( $verification_hash ) {
        # Expects rev_id as input and returns verification_hash(required),
        # signature(optional), public_key(optional), wallet_address(optional),
        # witness_id(optional)

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );
        $row = $dbr->selectRow(
            'page_verification',
            [
                'rev_id',
                'domain_id',
                'previous_verification_Hash',
                'verification_hash',
                'next_verification_hash'
                'time_stamp',
                'signature',
                'public_key',
                'wallet_address',
                'witness_event_id'
            ],
            ['verification_hash' => $verification_hash],
            __METHOD__
        );

        if (!$row) {
            throw new HttpException("rev_id not found in the database", 404);
        }

        $output = [
            'rev_id' => $rev_id,
            'domain_id' => $row->domain_id,
            'previous_verification_hash' => $row->previous_verification_hash,
            'verification_hash' => $row->verification_hash,
            'next_verification_hash' => $row->next_verification_hash,
            'time_stamp' => $row->time_stamp,
            'signature' => $row->signature,
            'public_key' => $row->public_key,
            'wallet_address' => $row->wallet_address,
            'witness_event_id' => $row->witness_event_id,
        ];
        return $output;
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
