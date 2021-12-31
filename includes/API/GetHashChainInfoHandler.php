<?php

namespace DataAccounting\API;

use DataAccounting\Transfer\TransferEntityFactory;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\Entity\VerificationEntity;
use MediaWiki\Permissions\PermissionManager;
use Wikimedia\ParamValidator\ParamValidator;

class GetHashChainInfoHandler extends AuthorizedEntityHandler {
	/** @var TransferEntityFactory */
	private $transferEntityFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param VerificationEngine $verificationEngine
	 * @param TransferEntityFactory $transferEntityFactory
	 */
	public function __construct(
		PermissionManager $permissionManager, VerificationEngine $verificationEngine,
		TransferEntityFactory $transferEntityFactory
	) {
		parent::__construct( $permissionManager, $verificationEngine );
		$this->transferEntityFactory = $transferEntityFactory;
	}

	/** @inheritDoc */
	public function run( string $id_type, string $id ) {
		$context = $this->transferEntityFactory->newTransferContextFromTitle(
			$this->verificationEntity->getTitle()
		);
		if ( !$context ) {
			return [];
		}
		return $context->jsonSerialize();
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'id_type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => [ 'genesis_hash', 'title' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @param string $idType
	 * @param string $id
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $idType, string $id ): ?VerificationEntity {
		$conds = [];
		if ( $idType === 'title' ) {
			// TODO: DB data should hold Db key, not prefixed text (spaces replaced with _)
			// Once that is done, remove next line
			$id = str_replace( '_', ' ', $id );
			$conds['page_title'] = $id;
		} else {
			$conds[VerificationEntity::GENESIS_HASH] = $id;
		}
		return $this->verificationEngine->getLookup()->verificationEntityFromQuery( $conds );
	}
}
