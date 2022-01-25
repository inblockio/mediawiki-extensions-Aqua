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
	public function run( string $identifier_type ) {
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
			'identifier_type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => [ 'genesis_hash', 'title' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'identifier' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @param string $identifierType
	 * @param string $identifier
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $identifierType ): ?VerificationEntity {
		$identifier = $this->getValidatedParams()['identifier'];
		if ( $identifierType === 'title' ) {
			$title = \Title::newFromText( $identifier );
			return $this->verificationEngine->getLookup()->verificationEntityFromTitle( $title );
		} else {
			return $this->verificationEngine->getLookup()->verificationEntityFromQuery( [
				VerificationEntity::GENESIS_HASH => $identifier,
			] );
		}
	}
}
