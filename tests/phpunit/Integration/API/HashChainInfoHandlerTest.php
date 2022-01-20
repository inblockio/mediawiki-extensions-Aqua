<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\GetHashChainInfoHandler;
use DataAccounting\Tests\Integration\API;
use DataAccounting\Verification\Entity\VerificationEntity;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;

/**
 * @group Database
 */
class HashChainInfoHandlerTest extends API {
	use HandlerTestTrait;

	/**
	 * @covers \DataAccounting\API\GetHashChainInfoHandler
	 */
	public function testHashChainInfoHandler(): void {
		// Testing the case when the rev_id is found.
		$services = [
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
			$this->getServiceContainer()->getService( 'DataAccountingTransferEntityFactory' ),
		];
		$requestData = new RequestData( [
			'pathParams' => [ 'identifier_type' => 'title' ],
			'queryParams' => [ 'identifier' => 'UTPage' ]
		] );
		// Should be denied permission unless the user is authorized.
		$this->expectContextPermissionDenied(
			new GetHashChainInfoHandler( ...$services ),
			$requestData
		);

		// Let's authorize the user. Because this API endpoint requires 'read'
		// permission for the title that is related to given rev_id.
		$services[0] = $this->createMock( PermissionManager::class );
		$services[0]->method( 'userCan' )->willReturn( true );
		$response = $this->executeHandler(
			new GetHashChainInfoHandler( ...$services ),
			$requestData
		);

		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$keys = [
			VerificationEntity::GENESIS_HASH,
			VerificationEntity::DOMAIN_ID,
			'latest_verification_hash',
			'site_info',
			'title',
			'namespace',
			'chain_height',
		];
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}

		// Testing the case when the rev_id is not found.
		$this->expectExceptionObject(
			new HttpException( "Not found", 404 )
		);

		$response = $this->executeHandler(
			new GetHashChainInfoHandler( ...$services ),
			new RequestData( [
				'pathParams' => [ 'identifier_type' => 'title' ],
				'queryParams' => [ 'identifier' => 'Test123' ]
			] )
		);

		$response = $this->executeHandler(
			new GetHashChainInfoHandler( ...$services ),
			$requestData
		);

		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$keys = [
			VerificationEntity::GENESIS_HASH,
			VerificationEntity::DOMAIN_ID,
			'latest_verification_hash',
			'site_info',
			'title',
			'namespace',
			'chain_height',
		];
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}

		// --------------------
		$requestData = new RequestData( [
			'pathParams' => [ 'identifier_type' => 'genisis_hash' ],
			'queryParams' => [ 'identifier' => '7ed23e2cfaf58e2e9c23a01378f377a09366d52fa262776551ce8221962cd49efbfce462473072c8592e21b3e2d07118e8fd79a37131696d719fe37404cade1b' ]
		] );
		$response = $this->executeHandler(
			new GetHashChainInfoHandler( ...$services ),
			$requestData
		);

		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$keys = [
			VerificationEntity::GENESIS_HASH,
			VerificationEntity::DOMAIN_ID,
			'latest_verification_hash',
			'site_info',
			'title',
			'namespace',
			'chain_height',
		];
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		// Testing the case when the genisis_hash is not found.
		$this->expectExceptionObject(
			new HttpException( "Not found", 404 )
		);

		$response = $this->executeHandler(
			new GetHashChainInfoHandler( ...$services ),
			new RequestData( [
				'pathParams' => [ 'identifier_type' => 'genisis_hash' ],
				'queryParams' => [ 'identifier' => '123' ]
			] )
		);
	}
}
