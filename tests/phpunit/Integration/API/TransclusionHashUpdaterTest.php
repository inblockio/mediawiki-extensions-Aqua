<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\API\TransclusionHashUpdater;
use DataAccounting\Tests\Integration\API;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Permissions\PermissionManager;
use Title;

/**
 * @group Broken
 * @group Database
 */
class TransclusionHashUpdaterTest extends API {
	use HandlerTestTrait;

	/**
	 * This method is called before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->insertPage( 'TransclusionTest', "TestContent\n{{:ResourceTest}}" );
		$this->insertPage( 'ResourceTest', "More TestContent" );
	}

	/**
	 * @covers \DataAccounting\API\TransclusionHashUpdater
	 */
	public function testTransclusionHashUpdater(): void {
		$vEngine = $this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' );
		$resource = Title::newFromText( 'ResourceTest' );
		$vEngine->getLookup()->verificationEntityFromTitle( $resource );
		$title = Title::newFromText( 'TransclusionTest' );
		$vEngine->getLookup()->verificationEntityFromTitle( $title );
		$this->editPage( 'ResourceTest', "new testcontent" );

		$services = [
			$this->getServiceContainer()->getService( 'DataAccountingTransclusionManager' ),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getRevisionStore(),
			$this->getServiceContainer()->getPermissionManager(),
		];
		$requestData = new RequestData( [
			'method' => 'POST',
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => \FormatJson::encode( [
				'page_title' => 'TransclusionTest',
				'resource' => 'ResourceTest',
			] )
		] );
		$this->expectContextPermissionDenied(
			new TransclusionHashUpdater( ...$services ),
			$requestData
		);
		// Let's authorize the user. Because this API endpoint requires 'read'
		// permission for the title that is related to given rev_id.
		$services[3] = $this->createMock( PermissionManager::class );
		$services[3]->method( 'userCan' )->willReturn( true );
		$response = $this->executeHandler(
			new TransclusionHashUpdater( ...$services ),
			$requestData
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertSame( true, $data['success'] );

		$this->expectExceptionObject(
			new HttpException( "Invalid subject title", 500 )
		);
		$response = $this->executeHandler(
			new TransclusionHashUpdater( ...$services ),
			new RequestData( [
				'method' => 'POST',
				'headers' => [ 'Content-Type' => 'application/json' ],
				'bodyContents' => \FormatJson::encode( [
					'page_title' => 'Test123',
					'resource' => 'ResourceTest',
				] )
			] )
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertSame( false, $data['success'] );
	}
}
