<?php

namespace DataAccounting\API;

use DataAccounting\RevisionManipulator;
use DataAccounting\Verification\VerificationEngine;
use Exception;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use RequestContext;
use User;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class DeleteRevisionsHandler extends SimpleHandler {

	/** @var PermissionManager */
	private $permissionManager;

	/** @var RevisionManipulator */
	protected $revisionManipulator;

	/** @var User */
	private $user;

	/**
	 * @param PermissionManager $permissionManager
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct(
		PermissionManager $permissionManager,
		RevisionManipulator $revisionManipulator
	) {
		$this->permissionManager = $permissionManager;
		$this->revisionManipulator = $revisionManipulator;
		$this->user = RequestContext::getMain()->getUser();
	}

	/** @inheritDoc */
	public function run() {
		if ( !$this->permissionManager->userHasRight( $this->user, 'delete' ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'rest-permission-denied-revision' )->plaintextParams(
					'You are not allowed to delete revisions'
				),
				403
			);
		}

		$revisionIds = $this->getValidatedBody()['ids'];
		try {
			$this->executeAction( $revisionIds );
		} catch ( \Exception $e ) {
			throw new HttpException( $e->getMessage(), 500 );
		}
		return [ 'success' => true ];
	}

	/**
	 * @param array $revisionIds
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function executeAction( array $revisionIds ) {
		$this->revisionManipulator->deleteRevisions( $revisionIds );
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
			'ids' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
			]
		] );
	}
}
