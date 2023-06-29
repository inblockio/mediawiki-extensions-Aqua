<?php

namespace DataAccounting\API;

use DataAccounting\Transfer\Importer;
use DataAccounting\Transfer\TransferContext;
use DataAccounting\Transfer\TransferEntityFactory;
use DataAccounting\Transfer\TransferRevisionEntity;
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

	/**
	 * @param PermissionManager $permissionManager
	 * @param TransferEntityFactory $transferEntityFactory
	 * @param Importer $importer
	 */
	public function __construct(
		PermissionManager $permissionManager,
		TransferEntityFactory $transferEntityFactory,
		Importer $importer
	) {
		$this->transferEntityFactory = $transferEntityFactory;
		$this->importer = $importer;
		$this->permissionManager = $permissionManager;
	}

	/** @inheritDoc */
	public function run() {
		$user = \RequestContext::getMain()->getUser();
		if ( !$this->permissionManager->userHasRight( $user, 'import' ) ) {
			// TODO: Enable once user context becomes available
			// throw new HttpException( 'User must have \"import\" permission', 401 );
		}
		$context = $this->transferEntityFactory->newTransferContextForImport(
			$this->getBodyData( 'context' )
		);
		if ( !( $context instanceof TransferContext ) ) {
			throw new HttpException( 'Context not valid' );
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
}
