<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Permissions\PermissionManager;

use DataAccounting\API\GetPageAllRevsHandler;
use DataAccounting\API\GetPageLastRevHandler;
use DataAccounting\API\RequestHashHandler;
use DataAccounting\API\VerifyPageHandler;
use DataAccounting\API\WriteStoreSignedTxHandler;

use DataAccounting\ServerInfo;
use DataAccounting\API\GetServerInfoHandler;

/**
 * @group Database
 */
class DAApiTest extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function assertJsonContentType( $response ) {
		// Assert that the response body type is JSON.
		$this->assertSame( 'application/json', $response->getHeaderLine( 'Content-Type' ) );
	}

	public function getJsonBody( $response ): array {
		return json_decode( $response->getBody()->getContents(), true );
	}

	/**
	 * @covers \DataAccounting\API\GetServerInfoHandler
	 */
	public function testGetServerInfo(): void {
		$response = $this->executeHandler(
			new GetServerInfoHandler(),
			new RequestData()
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$this->assertArrayHasKey( 'api_version', $data );
		$this->assertSame( ServerInfo::DA_API_VERSION, $data['api_version'] );
	}

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

	public function expectPermissionDenied( $handler, RequestData $requestData ) {
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

	public function expectContextPermissionDenied( $handler, RequestData $requestData ) {
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

	/**
	 * @covers \DataAccounting\API\WriteStoreSignedTxHandler
	 */
	public function testWriteStoreSigned(): void {
		$requestData = new RequestData( [
			'method' => 'POST',
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( [
			'rev_id' => 1,
			'signature' => 'signature',
			'public_key' => 'public key',
			'wallet_address' => 'wallet address',
		] ) ] );

		$services = [
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
			$this->getServiceContainer()->getRevisionStore(),
		];
		// Should be denied permission unless the user is authorized.
		$this->expectPermissionDenied(
			new WriteStoreSignedTxHandler( ...$services ),
			$requestData
		);
		// Let's authorize the user. Because this API endpoint requires 'move'
		// permission.
		// TODO we should authorize the user instead of disabling the
		// permission check.
		// If this is changed, don't forget to also change for WitnessTest.php.
		$services[0] = $this->createMock( PermissionManager::class );
		$services[0]->method( 'userHasRight' )->willReturn( true );

		// Should work now.
		$response = $this->executeHandler(
			new WriteStoreSignedTxHandler( ...$services ),
			$requestData
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertSame( [ 'value' => true ], $data );
	}
}
