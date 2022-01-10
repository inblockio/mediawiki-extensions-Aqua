<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\VerifyPageHandler;
use DataAccounting\Tests\Integration\API;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;

/**
 * @group Database
 */
class VerifyPage extends API {
	use HandlerTestTrait;

	/**
	 * @covers \DataAccounting\API\VerifyPageHandler
	 */
	public function testVerifyPage(): void {
		// Testing the case when the rev_id is found.
		$services = [
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
		];
		$requestData = new RequestData( [ 'pathParams' => [ 'rev_id' => 1 ] ] );
		// Should be denied permission unless the user is authorized.
		$this->expectContextPermissionDenied(
			new VerifyPageHandler( ...$services ),
			$requestData
		);
		// Let's authorize the user. Because this API endpoint requires 'read'
		// permission for the title that is related to given rev_id.
		$services[0] = $this->createMock( PermissionManager::class );
		$services[0]->method( 'userCan' )->willReturn( true );
		$response = $this->executeHandler(
			new VerifyPageHandler( ...$services ),
			$requestData
		);

		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
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

		// Testing the case when the rev_id is not found.
		$this->expectExceptionObject(
			new HttpException( "Not found", 404 )
		);

		$response = $this->executeHandler(
			new VerifyPageHandler( ...$services ),
			new RequestData( [ 'pathParams' => [ 'rev_id' => 0 ] ] )
		);
	}
}
