<?php

namespace DataAccounting\API;

use DataAccounting\Verification\VerificationEntity;
use Wikimedia\ParamValidator\ParamValidator;

class GetPageLastRevHandler extends AuthorizedEntityHandler {

	/** @inheritDoc */
	public function run( $page_title ) {
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
	protected function getEntity( string $pageTitle ): ?VerificationEntity {
		// TODO: DB data should hold Db key, not prefixed text (spaces replaced with _)
		// Once that is done, remove next line
		$pageTitle = str_replace( '_', ' ', $pageTitle );
		return $this->verificationEngine->getLookup()->verificationEntityFromQuery( [
			'page_title' => $pageTitle
		] );
	}
}
