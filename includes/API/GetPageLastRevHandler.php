<?php

namespace DataAccounting\API;

use DataAccounting\Verification\Entity\VerificationEntity;
use Wikimedia\ParamValidator\ParamValidator;

class GetPageLastRevHandler extends AuthorizedEntityHandler {

	/** @inheritDoc */
	public function run() {
		return [
			'page_title' => $this->verificationEntity->getTitle()->getPrefixedDBkey(),
			'page_id' => $this->verificationEntity->getTitle()->getArticleID(),
			'rev_id' => $this->verificationEntity->getRevision()->getId(),
			VerificationEntity::VERIFICATION_HASH =>
				$this->verificationEntity->getHash( VerificationEntity::VERIFICATION_HASH ),
		];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'page_title' => [
				self::PARAM_SOURCE => 'query',
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
	protected function getEntity(): ?VerificationEntity {
		$title = \Title::newFromText( $this->getValidatedParams()['page_title'] );
		return $this->verificationEngine->getLookup()->verificationEntityFromTitle( $title );
	}
}
