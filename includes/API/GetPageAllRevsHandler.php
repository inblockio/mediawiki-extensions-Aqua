<?php

namespace DataAccounting\API;

use DataAccounting\Verification\VerificationEntity;
use MediaWiki\Rest\HttpException;
use Wikimedia\ParamValidator\ParamValidator;

class GetPageAllRevsHandler extends AuthorizedEntityHandler {

	/** @inheritDoc */
	public function run() {
		$ids = $this->verificationEngine->getLookup()->getAllRevisionIds(
			$this->verificationEntity->getTitle()
		);

		if ( count( $ids ) === 0 ) {
			throw new HttpException( 'Not found', 404 );
		}

		return $ids;
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
