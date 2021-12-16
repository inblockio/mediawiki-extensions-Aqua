<?php

namespace DataAccounting\API;

use DataAccounting\Verification\VerificationEntity;
use Wikimedia\ParamValidator\ParamValidator;

class RequestHashHandler extends AuthorizedEntityHandler {
	/** @inheritDoc */
	public function run() {
		$hash = $this->verificationEntity->getHash( VerificationEntity::VERIFICATION_HASH );
		return 'I sign the following page verification_hash: [0x' . $hash . ']';
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'rev_id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @param string $idType
	 * @param string $id
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $revId ): ?VerificationEntity {
		return $this->verificationEngine->getLookup()->verificationEntityFromRevId( (int)$revId );
	}
}
