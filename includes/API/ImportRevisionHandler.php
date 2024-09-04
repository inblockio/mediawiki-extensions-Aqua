<?php

namespace DataAccounting\API;

use DataAccounting\Transfer\Importer;
use DataAccounting\Transfer\TransferContext;
use DataAccounting\Transfer\TransferEntityFactory;
use DataAccounting\Transfer\TransferRevisionEntity;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use Wikimedia\ParamValidator\ParamValidator;

class ImportRevisionHandler extends SimpleHandler {
	/** @var PermissionManager */
	private $permissionManager;
	/** @var TransferEntityFactory */
	private $transferEntityFactory;
	/** @var Importer */
	private $importer;

	/** @var VerificationEngine */
	private $verificationEngine;

	/**
	 * @param PermissionManager $permissionManager
	 * @param TransferEntityFactory $transferEntityFactory
	 * @param Importer $importer
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct(
		PermissionManager $permissionManager,
		TransferEntityFactory $transferEntityFactory,
		Importer $importer,
		VerificationEngine $verificationEngine
	) {
		$this->transferEntityFactory = $transferEntityFactory;
		$this->importer = $importer;
		$this->permissionManager = $permissionManager;
		$this->verificationEngine = $verificationEngine;
	}

	/** @inheritDoc */
	public function run() {
		$user = \RequestContext::getMain()->getUser();
		if ( !$this->permissionManager->userHasRight( $user, 'import' ) ) {
			// TODO: Enable once user context becomes available
			// throw new HttpException( 'User must have \"import\" permission', 401 );
		}
		$params = $this->getValidatedParams();

		$context = $this->transferEntityFactory->newTransferContextForImport(
			$this->getBodyData( 'context' ), $params['direct']
		);
		if ( !( $context instanceof TransferContext ) ) {
			throw new HttpException( 'Context not valid' );
		}

		$hash = $this->getBodyData( 'revision' )['metadata']['verification_hash'] ?? null;
		if ( !$hash ) {
			throw new HttpException( 'Revision hash not provided' );
		}
		$entity = $this->verificationEngine->getLookup()->verificationEntityFromHash( $hash );
		if ( $entity ) {
			return $this->getResponseFactory()->createJson( [ 'status' => 'ok' ] );
		}
		$revisionEntity = $this->transferEntityFactory->newRevisionEntityFromApiData(
			$this->getBodyData( 'revision' )
		);
		if ( !( $revisionEntity instanceof TransferRevisionEntity ) ) {
			throw new HttpException( 'Revision data not valid' );
		}

		$status = $this->importer->importRevision( $revisionEntity, $context, $user );
		if ( !$status->isOK() ) {
			throw new HttpException( $status->getMessage() );
		}
		return [ 'status' => 'ok' ];
	}

	/**
	 * @param string|null $key Piece of data to retrieve
	 * @param array $default
	 * @return array|mixed
	 */
	protected function getBodyData( $key = null, $default = [] ) {
		$body = $this->getValidatedBody();
		if ( $key ) {
			if ( isset( $body[$key] ) ) {
				return $body[$key];
			} else {
				return $default;
			}
		}
		return $body;
	}

	/**
	 * @param string $contentType
	 * @return JsonBodyValidator|null
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType === 'application/json' ) {
			return new JsonBodyValidator( [
				'context' => [
					ParamValidator::PARAM_REQUIRED => false,
				],
				'revision' => [
					ParamValidator::PARAM_REQUIRED => false,
				]
			] );
		}
		return null;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'direct' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
