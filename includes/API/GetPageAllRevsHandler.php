<?php

namespace DataAccounting\API;

use DataAccounting\Verification\Entity\VerificationEntity;
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

		if ( $this->getValidatedParams()['full_entities'] ) {
			$entities = [];
			foreach ( $ids as $id ) {
				$entities[] = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $id );
			}
			return array_filter( $entities );
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
			'full_entities' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
			],
		];
	}

	/**
	 * @param string $idType
	 * @param string $id
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $pageTitle ): ?VerificationEntity {
		$title = \Title::newFromText( $pageTitle );
		return $this->verificationEngine->getLookup()->verificationEntityFromTitle( $title );
	}
}
