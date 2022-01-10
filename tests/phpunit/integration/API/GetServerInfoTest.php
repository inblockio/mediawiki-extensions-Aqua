<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests\Integration\API;

use DataAccounting\ServerInfo;
use DataAccounting\API\GetServerInfoHandler;
use DataAccounting\Tests\Integration\API;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;

/**
 * @group Database
 */
class GetServerInfo extends API {
	use HandlerTestTrait;

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
}
