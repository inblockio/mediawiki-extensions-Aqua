<?php

namespace DataAccounting\API;

use DataAccounting\Transfer\TransferEntityFactory;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationEntity;
use MediaWiki\Permissions\PermissionManager;
use Wikimedia\ParamValidator\ParamValidator;

class GetRevisionHandler extends AuthorizedEntityHandler {
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
	public function run() {
		$transferEntity = $this->transferEntityFactory->newRevisionEntityFromVerificationEntity(
			$this->verificationEntity
		);

		return $transferEntity->jsonSerialize();
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'verification_hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @param string $verificationHash
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $verificationHash ): ?VerificationEntity {
		return $this->verificationEngine->getLookup()->verificationEntityFromHash( $verificationHash );
	}
}
