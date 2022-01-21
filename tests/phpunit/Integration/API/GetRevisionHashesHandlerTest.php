<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\GetRevisionHashesHandler;
use DataAccounting\Tests\Integration\API;
use DataAccounting\Verification\Entity\VerificationEntity;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;
use Title;

/**
 * @group Database
 */
class GetRevisionHashesHandlerTest extends API {
	use HandlerTestTrait;

	/**
	 * @covers \DataAccounting\API\GetRevisionHashesHandler
	 */
	public function testGetRevisionHashesHandler(): void {
		$vEngine = $this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' );
		$title = Title::newFromText( 'UTPage' );
		$entity = $vEngine->getLookup()->verificationEntityFromTitle( $title );
		$services = [
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
		];
		$requestData = new RequestData( [ 'pathParams' => [
			VerificationEntity::VERIFICATION_HASH => $entity->getHash(
				VerificationEntity::VERIFICATION_HASH
			),
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
		$this->assertArrayHasKey( 0, $data );
		// TODO the rev_id shouldn't be a string.
		$this->assertSame( $data[0], $entity->getHash(
			VerificationEntity::VERIFICATION_HASH
		) );

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
