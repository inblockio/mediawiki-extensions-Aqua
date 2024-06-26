<?php

namespace DataAccounting\API;

use DataAccounting\Verification\Entity\VerificationEntity;
use MediaWiki\Rest\HttpException;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

class GetBranchHandler extends AuthorizedEntityHandler {

	/** @inheritDoc */
	public function run() {
		$currentEntity = $this->verificationEntity;
		$title = $currentEntity->getTitle();
		$hashes = [ $currentEntity->getHashes()[VerificationEntity::VERIFICATION_HASH] ];
		while ( true ) {
			$prev_hash = $currentEntity->getHashes()[VerificationEntity::PREVIOUS_VERIFICATION_HASH];
			if ( !$prev_hash || $prev_hash == '' ) {
				break;
			}
			$hashes[] = $prev_hash;
			$currentEntity = $this->verificationEngine->getLookup()->verificationEntityFromHash( $prev_hash );
		}

		return [
			"title" => $title->getDBkey(),
			"namespace" => $title->getNamespace(),
			"hashes" => $hashes,
		];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'revision_hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @param string $revision_hash
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $revision_hash ): ?VerificationEntity {
		return $this->verificationEngine->getLookup()->verificationEntityFromHash( $revision_hash );
	}
}
