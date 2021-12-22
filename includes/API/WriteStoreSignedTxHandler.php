<?php

namespace DataAccounting\API;

use DataAccounting\Verification\VerificationEngine;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Revision\RevisionStore;
use RequestContext;
use User;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class WriteStoreSignedTxHandler extends SimpleHandler {

	/** @var PermissionManager */
	private $permissionManager;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var User */
	private $user;

	/**
	 * @param PermissionManager $permissionManager
	 * @param VerificationEngine $verificationEngine
	 * @param RevisionStore $revisionStore
	 */
	public function __construct(
		PermissionManager $permissionManager,
		VerificationEngine $verificationEngine,
		RevisionStore $revisionStore
	) {
		$this->permissionManager = $permissionManager;
		$this->revisionStore = $revisionStore;
		$this->verificationEngine = $verificationEngine;
		// @todo Inject this, when there is a good way to do that
		$this->user = RequestContext::getMain()->getUser();
	}

	/** @inheritDoc */
	public function run() {
		// Expects Revision_ID [Required] Signature[Required], Public
		// Key[Required] and Wallet Address[Required] as inputs; Returns a
		// status for success or failure

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
		$rev_id = $body['rev_id'];
		$signature = $body['signature'];
		$public_key = $body['public_key'];
		/** @var string */
		$wallet_address = $body['wallet_address'];

		$revision = $this->revisionStore->getRevisionById( $rev_id );
		if ( $revision === null ) {
			throw new HttpException( "Revision not found", 404 );
		}

		try {
			$res = $this->verificationEngine->signRevision(
				$revision, $this->user, $wallet_address, $signature, $public_key
			);
		} catch ( \Exception $ex ) {
			throw new HttpException( $ex->getMessage(), $ex->getCode() );
		}
		if ( !$res ) {
			throw new HttpException( "Could not sign the revision", 500 );
		}

		return true;
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
			'rev_id' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'signature' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'public_key' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'wallet_address' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		] );
	}
}
