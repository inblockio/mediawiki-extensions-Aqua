<?php

namespace DataAccounting\API;

use DataAccounting\RevisionManipulator;
use Exception;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use RequestContext;
use User;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class DeleteRevisionByHashHandler extends SimpleHandler {

	/** @var PermissionManager */
	private $permissionManager;

	/** @var RevisionManipulator */
	protected $revisionManipulator;

	/** @var User */
	private $user;

	/**
	 * @param PermissionManager $permissionManager
	 * @param RevisionManipulator $revisionManipulator
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

		$hash = $this->getValidatedParams()['hash'];
		try {
			$this->revisionManipulator->deleteFromHash( $hash, $this->user );
		} catch ( Exception $e ) {
			throw new HttpException( $e->getMessage(), $e->getCode() );
		}
		return [ 'success' => true ];
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
