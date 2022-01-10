<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration;

use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 */
abstract class API extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	protected function assertJsonContentType( $response ) {
		// Assert that the response body type is JSON.
		$this->assertSame( 'application/json', $response->getHeaderLine( 'Content-Type' ) );
	}

	protected function getJsonBody( $response ): array {
		return json_decode( $response->getBody()->getContents(), true );
	}

	protected function expectPermissionDenied( $handler, RequestData $requestData ) {
		try {
			$response = $this->executeHandler(
				$handler,
				$requestData
			);
		} catch ( LocalizedHttpException $ex ) {
			$this->assertSame(
				'Localized exception with key rest-permission-denied-revision',
				$ex->getMessage()
			);
			$this->assertSame(
				'You are not allowed to use the REST API',
				$ex->getMessageValue()->getParams()[0]->getValue()
			);
		}
	}

	protected function expectContextPermissionDenied( $handler, RequestData $requestData ) {
		try {
			$response = $this->executeHandler(
				$handler,
				$requestData
			);
		} catch ( HttpException $ex ) {
			$this->assertSame(
				"Permission denied",
				$ex->getMessage()
			);
		}
	}
}
