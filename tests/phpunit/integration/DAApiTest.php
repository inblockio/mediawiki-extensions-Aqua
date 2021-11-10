<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use DataAccounting\API\VerifyPageHandler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 */
class DAApiTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	public function testVerifyPage(): void {
		$response = $this->executeHandler(
			new VerifyPageHandler(),
			new RequestData( [ 'pathParams' => [ 'rev_id' => '2' ] ] )
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
	}
}
