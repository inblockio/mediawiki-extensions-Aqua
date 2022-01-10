<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\GetRevisionHashesHandler;
use DataAccounting\Tests\Integration\API;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;

/**
 * @group Database
 */
class GetRevisionHashesHandlerTest extends API {
	use HandlerTestTrait;

	/**
	 * @covers \DataAccounting\API\GetRevisionHashesHandler
	 */
	public function testGetRevisionHashesHandler(): void {
		// Testing the case when the page is found.
		$services = [
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
		];
		$requestData = new RequestData( [ 'pathParams' => [
			'verification_hash' => '6dc8489cf860aab2bf34a24c366a3c3767e47943bfc2bca6661bb39d2546d2f5a246e5518b7161b61fa5639c767bc551b3845781f1c01ec4988192959a700b94'
		] ] );
		$this->expectContextPermissionDenied(
			new GetRevisionHashesHandler( ...$services ),
			$requestData
		);
		// Let's authorize the user. Because this API endpoint requires 'read'
		// permission for the title that is related to given rev_id.
		$services[0] = $this->createMock( PermissionManager::class );
		$services[0]->method( 'userCan' )->willReturn( true );
		$response = $this->executeHandler(
			new GetRevisionHashesHandler( ...$services ),
			$requestData
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$keys = [
			'verification_context',
			'content',
			'metadata',
			'signature',
			'witness',
		];
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		// TODO the rev_id shouldn't be a string.
		#$this->assertSame( 1, $data['rev_id'] );
		#$this->assertSame( 'UTPage', $data['page_title'] );

		// Testing the case when the page is not found.
		$this->expectExceptionObject(
			new HttpException( "Not found", 404 )
		);

		$response = $this->executeHandler(
			new GetRevisionHashesHandler( ...$services ),
			new RequestData( [ 'pathParams' => [ 'verification_hash' => '123' ] ] )
		);
	}
}
