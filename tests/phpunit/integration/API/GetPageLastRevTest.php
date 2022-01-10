<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\GetPageLastRevHandler;
use DataAccounting\Tests\Integration\API;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;

/**
 * @group Database
 */
class GetPageLastRevTest extends API {
	use HandlerTestTrait;

	/**
	 * @covers \DataAccounting\API\GetPageLastRevHandler
	 */
	public function testGetPageLastRev(): void {
		// Testing the case when the page is found.
		$services = [
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
		];
		$requestData = new RequestData( [ 'queryParams' => [ 'page_title' => 'UTPage' ] ] );
		$this->expectContextPermissionDenied(
			new GetPageLastRevHandler( ...$services ),
			$requestData
		);
		// Let's authorize the user. Because this API endpoint requires 'read'
		// permission for the title that is related to given rev_id.
		$services[0] = $this->createMock( PermissionManager::class );
		$services[0]->method( 'userCan' )->willReturn( true );
		$response = $this->executeHandler(
			new GetPageLastRevHandler( ...$services ),
			$requestData
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$keys = [
			'page_title',
			'page_id',
			'rev_id',
			'verification_hash',
		];
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		// TODO the rev_id shouldn't be a string.
		$this->assertSame( 1, $data['rev_id'] );
		$this->assertSame( 'UTPage', $data['page_title'] );

		// Testing the case when the page is not found.
		$this->expectExceptionObject(
			new HttpException( "Not found", 404 )
		);

		$response = $this->executeHandler(
			new GetPageLastRevHandler( ...$services ),
			new RequestData( [ 'queryParams' => [ 'page_title' => 'IDONTEXIST IDONTEXIST' ] ] )
		);
	}
}
