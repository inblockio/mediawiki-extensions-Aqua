<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\WriteStoreSignedTxHandler;
use DataAccounting\Tests\Integration\API;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Permissions\PermissionManager;

/**
 * @group Database
 */
class WriteStoreSignedTest extends API {
	use HandlerTestTrait;

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
