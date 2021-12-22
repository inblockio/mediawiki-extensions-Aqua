<?php

namespace DataAccounting\API;

use DataAccounting\Verification\Entity\VerificationEntity;
use Wikimedia\ParamValidator\ParamValidator;

class VerifyPageHandler extends AuthorizedEntityHandler {

	/** @inheritDoc */
	public function run() {
		# Expects rev_id as input and returns verification_hash(required),
		# signature(optional), public_key(optional), wallet_address(optional),
		# witness_id(optional)

		return $this->verificationEntity->jsonSerialize();
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
