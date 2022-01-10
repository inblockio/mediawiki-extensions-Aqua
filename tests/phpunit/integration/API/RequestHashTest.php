<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\RequestHashHandler;
use DataAccounting\Tests\Integration\API;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;

/**
 * @group Database
 */
class RequestHashTest extends API {
	use HandlerTestTrait;

	/**
	 * @covers \DataAccounting\API\RequestHashHandler
	 */
	public function testRequestHash(): void {
		// Testing the case when the rev_id is found.
		$services = [
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
		];
		$requestData = new RequestData( [ 'pathParams' => [ 'rev_id' => 1 ] ] );
		$this->expectContextPermissionDenied(
			new RequestHashHandler( ...$services ),
			$requestData
		);
		// Let's authorize the user. Because this API endpoint requires 'read'
		// permission for the title that is related to given rev_id.
		$services[0] = $this->createMock( PermissionManager::class );
		$services[0]->method( 'userCan' )->willReturn( true );
		$response = $this->executeHandler(
			new RequestHashHandler( ...$services ),
			$requestData
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$this->assertArrayHasKey( 'value', $data );
		$this->assertStringStartsWith(
			'I sign the following page verification_hash: [0x',
			$data['value'],
		);

		// Testing the case when the rev_id is not found.
		$this->expectExceptionObject(
			new HttpException( "Not found", 404 )
		);
		$response = $this->executeHandler(
			new RequestHashHandler( ...$services ),
			new RequestData( [ 'pathParams' => [ 'rev_id' => 0 ] ] )
		);
	}
}
