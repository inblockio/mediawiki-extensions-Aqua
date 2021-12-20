<?php

namespace DataAccounting\API;

use DataAccounting\Verification\VerificationEntity;
use Wikimedia\ParamValidator\ParamValidator;

class GetHashChainInfoHandler extends AuthorizedEntityHandler {

	/** @inheritDoc */
	public function run( string $id_type, string $id ) {
		$entity = $this->getEntity( $id_type, $id );
		return [
			VerificationEntity::GENESIS_HASH => $entity->getHash( VerificationEntity::GENESIS_HASH ),
			VerificationEntity::DOMAIN_ID => $entity->getDomainId(),
			'latest_verification_hash' => $entity->getHash( VerificationEntity::VERIFICATION_HASH ),
		];
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
