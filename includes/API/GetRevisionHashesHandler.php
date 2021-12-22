<?php

namespace DataAccounting\API;

use DataAccounting\Verification\Entity\VerificationEntity;
use Wikimedia\ParamValidator\ParamValidator;

class GetRevisionHashesHandler extends AuthorizedEntityHandler {

	/** @inheritDoc */
	public function run() {
		return $this->verificationEngine->getLookup()->newerHashesForEntity(
			$this->verificationEntity,
			VerificationEntity::VERIFICATION_HASH
		);
	}

	/**
	 * @param string $verificationHash
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $verificationHash ): ?VerificationEntity {
		return $this->verificationEngine->getLookup()->verificationEntityFromHash( $verificationHash );
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
}
