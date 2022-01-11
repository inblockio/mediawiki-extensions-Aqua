<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\GetPageAllRevsHandler;
use DataAccounting\Tests\Integration\API;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;

/**
 * @group Database
 */
class GetPageAllRevsTest extends API {
	use HandlerTestTrait;

	/**
	 * @covers \DataAccounting\API\GetPageAllRevsHandler
	 */
	public function testGetPageAllRevs(): void {
		// Testing the case when the page exists.
		$services = [
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
		];
		$requestData = new RequestData( [
			'pathParams' => [ 'page_title' => 'UTPage' ]
		] );
		$this->expectContextPermissionDenied(
			new GetPageAllRevsHandler( ...$services ),
			$requestData
		);
		// Let's authorize the user. Because this API endpoint requires 'read'
		// permission for the title that is related to given rev_id.
		$services[0] = $this->createMock( PermissionManager::class );
		$services[0]->method( 'userCan' )->willReturn( true );
		$response = $this->executeHandler(
			new GetPageAllRevsHandler( ...$services ),
			$requestData
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		// TODO why is this not an int?
		$this->assertSame( [ 1 ], $data );

		// Testing the case when the page doesn't exist.
		$title = 'IDONTEXIST IDONTEXIST';
		$this->expectExceptionObject(
			new HttpException( "Not found", 404 )
		);
		$response = $this->executeHandler(
			new GetPageAllRevsHandler( ...$services ),
			new RequestData( [ 'pathParams' => [ 'page_title' => $title ] ] )
		);
	}
}
