<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use MediaWiki\Rest\HttpException;

use DataAccounting\API\VerifyPageHandler;
use DataAccounting\API\GetPageAllRevsHandler;
use DataAccounting\API\GetPageByRevIdHandler;
use DataAccounting\API\GetPageLastRevHandler;
use DataAccounting\API\RequestHashHandler;

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
	 * @covers \DataAccounting\API\VerifyPageHandler
	 */
	public function testVerifyPage(): void {
		// Testing the case when the rev_id is found.
		$response = $this->executeHandler(
			new VerifyPageHandler(),
			new RequestData( [ 'pathParams' => [ 'rev_id' => '1' ] ] )
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
		try {
			$response = $this->executeHandler(
				new VerifyPageHandler(),
				new RequestData( [ 'pathParams' => [ 'rev_id' => '0' ] ] )
			);
		} catch ( HttpException $ex ) {
			$this->assertSame( 'rev_id not found in the database', $ex->getMessage() );
		}
	}

	/**
	 * @covers \DataAccounting\API\GetPageAllRevsHandler
	 */
	public function testGetPageAllRevs(): void {
		// Testing the case when the page doesn't exist.
		$response = $this->executeHandler(
			new GetPageAllRevsHandler(),
			new RequestData( [ 'pathParams' => [ 'page_title' => 'IDONTEXIST IDONTEXIST' ] ] )
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertSame( $data, [] );

		// Testing the case when the page exists.
		$response = $this->executeHandler(
			new GetPageAllRevsHandler(),
			new RequestData( [ 'pathParams' => [ 'page_title' => 'UTPage' ] ] )
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertSame( [ [
			'page_title' => 'UTPage',
			'page_id' => '1',
			'rev_id' => '1'
		] ], $data );
	}

	/**
	 * @covers \DataAccounting\API\GetPageByRevIdHandler
	 */
	public function testGetPageByRevId(): void {
		// Testing the case when the rev_id is not found.
		try {
			$response = $this->executeHandler(
				new GetPageByRevIdHandler(),
				new RequestData( [ 'pathParams' => [ 'rev_id' => '0' ] ] )
			);
		} catch ( HttpException $ex ) {
			$this->assertSame( 'rev_id not found in the database', $ex->getMessage() );
		}

		// Testing the case when the rev_id is found.
		$response = $this->executeHandler(
			new VerifyPageHandler(),
			new RequestData( [ 'pathParams' => [ 'rev_id' => '1' ] ] )
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$keys = [
			'rev_id',
			'domain_id',
			'verification_hash',
			'time_stamp',
			'signature',
			'public_key',
			'wallet_address',
			'witness_event_id',
		];
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		$this->assertSame( 1, $data['rev_id'] );
	}

	/**
	 * @covers \DataAccounting\API\GetPageLastRevHandler
	 */
	public function testGetPageLastRev(): void {
		// Testing the case when the page is not found.
		try {
			$response = $this->executeHandler(
				new GetPageLastRevHandler(),
				new RequestData( [ 'pathParams' => [ 'page_title' => 'IDONTEXIST IDONTEXIST' ] ] )
			);
		} catch ( HttpException $ex ) {
			$this->assertSame( 'page_title not found in the database', $ex->getMessage() );
		}

		// Testing the case when the page is found.
		$response = $this->executeHandler(
			new GetPageLastRevHandler(),
			new RequestData( [ 'pathParams' => [ 'page_title' => 'UTPage' ] ] )
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
		$this->assertSame( '1', $data['rev_id'] );
		$this->assertSame( 'UTPage', $data['page_title'] );
	}

	/**
	 * @covers \DataAccounting\API\RequestHashHandler
	 */
	public function testRequestHash(): void {
		// Testing the case when the rev_id is not found.
		try {
			$response = $this->executeHandler(
				new RequestHashHandler(),
				new RequestData( [ 'pathParams' => [ 'rev_id' => '0' ] ] )
			);
		} catch ( HttpException $ex ) {
			$this->assertSame( 'rev_id not found in the database', $ex->getMessage() );
		}

		// Testing the case when the rev_id is found.
		$response = $this->executeHandler(
			new RequestHashHandler(),
			new RequestData( [ 'pathParams' => [ 'rev_id' => '1' ] ] )
		);
		$this->assertJsonContentType( $response );
		$data = $this->getJsonBody( $response );
		$this->assertIsArray( $data, 'Body must be a JSON array' );
		$this->assertArrayHasKey( 'value', $data );
		$this->assertStringStartsWith(
			'I sign the following page verification_hash: [0x',
			$data['value'],
		);
	}
}
