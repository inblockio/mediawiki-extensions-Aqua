<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use DataAccounting\API\VerifyPageHandler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;

use MediaWiki\Rest\HttpException;

/**
 * @group Database
 */
class DAApiTest extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	/**
	 * @covers \DataAccounting\API\VerifyPageHandler
	 */
	public function testVerifyPage(): void {
		$response = $this->executeHandler(
			new VerifyPageHandler(),
			new RequestData( [ 'pathParams' => [ 'rev_id' => '1' ] ] )
		);

		$this->assertSame( 'application/json', $response->getHeaderLine( 'Content-Type' ) );
		$data = json_decode( $response->getBody()->getContents(), true );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$keys = [
			"rev_id",
			"domain_id",
			"verification_hash",
			"time_stamp",
			"signature",
			"public_key",
			"wallet_address",
			"witness_event_id",
		];
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}

		// Testing the case when the rev_id is not found
		try {
			$response = $this->executeHandler(
				new VerifyPageHandler(),
				new RequestData( [ 'pathParams' => [ 'rev_id' => '0' ] ] )
			);
		} catch ( HttpException $ex ) {
			$this->assertSame( 'rev_id not found in the database', $ex->getMessage() );
		}
	}

}
