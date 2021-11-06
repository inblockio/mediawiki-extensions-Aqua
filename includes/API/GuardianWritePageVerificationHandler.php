<?php

namespace DataAccounting\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Permissions\PermissionManager;
use RequestContext;
use Wikimedia\Message\MessageValue;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

class GuardianWritePageVerificationHandler extends SimpleHandler {
	/** @var PermissionManager */
	private $permissionManager;

	/** @var User */
	private $user;

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		PermissionManager $permissionManager
	) {
		$this->permissionManager = $permissionManager;

		// @todo Inject this, when there is a good way to do that
		$this->user = RequestContext::getMain()->getUser();
	}

    /** @inheritDoc */
    public function run( $page_verification_hash ) {
        // Only user and sysop have the 'move' right. We choose this so that
        // the DataAccounting extension works as expected even when not run via
        // micro-PKC Docker. As in, it shouldn't depend on the configuration of
        // an external, separate repo.
		if ( !$this->permissionManager->userHasRight( $this->user, 'move' ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'rest-permission-denied-revision' )->plaintextParams(
                    'You are not allowed to use the REST API'
				),
				403
			);
		}

        $body = $this->getValidatedBody();
        return $body;
    }

    /** @inheritDoc */
    public function needsWriteAccess() {
        return true;
    }

	/**
	 * @inheritDoc
     */
    public function getBodyValidator( $contentType ) {
        if ( $contentType !== 'application/json' ) {
            throw new HttpException( "Unsupported Content-Type",
                415,
                [ 'content_type' => $contentType ]
            );
        }

        return new JsonBodyValidator( [
            'verification_hash' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'content' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'boolean',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'metadata' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'boolean',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'signature' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'boolean',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'witness' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'boolean',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'guardian_timestamp' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'elapsed_time' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'guardian_id' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'request_hash' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'reply_hash' => [
                self::PARAM_SOURCE => 'body',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
        ] );
    }
}
