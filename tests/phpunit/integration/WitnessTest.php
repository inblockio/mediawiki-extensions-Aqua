<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use MediaWikiIntegrationTestCase;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use Wikimedia\Message\MessageValue;
use MediaWiki\Rest\RequestData;
use MediaWiki\Permissions\PermissionManager;
use Title;
use TitleFactory;
use Wikimedia\Rdbms\LoadBalancer;

use DataAccounting\API\GetWitnessDataHandler;
use DataAccounting\API\RequestMerkleProofHandler; // untested
use DataAccounting\API\WriteStoreWitnessTxHandler;
use DataAccounting\SpecialWitness;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\WitnessingEngine;

/**
 * @covers \DataAccounting\API\WriteStoreWitnessTxHandler
 * @covers \DataAccounting\SpecialWitness
 * @covers DataAccounting\API\GetWitnessDataHandler
 */
class WitnessTest extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	private RequestData $requestData;
	private PermissionManager $permissionManager;
	private PermissionManager $permissionManagerMock;
	private LoadBalancer $lb;
	private TitleFactory $titleFactory;
	private VerificationEngine $verificationEngine;
	private WitnessingEngine $witnessingEngine;

	public function setUp(): void {
		$requestData = new RequestData( [
			'method' => 'POST',
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( [
			'witness_event_id' => 1,
			'account_address' => '0xa2026582b94feb9124231fbf7b052c39218954c2',
			'transaction_hash' => '0x473b0b7b9ad818b9af02c0ab73cd9b186b28b6208c13f7d07554ace0915ca88e',
			'witness_network' => "goerli",
		] ) ] );
		$this->requestData = $requestData;
		$this->permissionManager = $this->getServiceContainer()->getPermissionManager();
		$this->lb = $this->getServiceContainer()->getDBLoadBalancer();
		$this->titleFactory = $this->getServiceContainer()->getTitleFactory();
		$this->verificationEngine = $this->getServiceContainer()->getService(
			'DataAccountingVerificationEngine'
		);
		$this->witnessingEngine = $this->getServiceContainer()->getService(
			'DataAccountingWitnessingEngine'
		);

		// Mock permission manager that allows any access.
		$pmMock = $this->createMock( PermissionManager::class );
		$pmMock->method( 'userHasRight' )->willReturn( true );
		$this->permissionManagerMock = $pmMock;
	}

	public function testPermissionDenied(): void {
		// Should be denied permission unless the user is authorized.
		$this->expectExceptionObject(
			new LocalizedHttpException(
				MessageValue::new( 'rest-permission-denied-revision' )->plaintextParams(
					'You are not allowed to use the REST API'
				),
				403
			)
		);
		$this->executeHandler(
			new WriteStoreWitnessTxHandler(
				$this->permissionManager,
				$this->verificationEngine,
				$this->witnessingEngine
			),
			$this->requestData
		);
	}

	public function testNoDomainManifestYet(): void {
		// Expects error when domain manifest is not yet generated.
		$this->expectExceptionObject(
			new HttpException( "witness_event_id not found in the witness_page table.", 404 )
		);
		$this->executeHandler(
			new WriteStoreWitnessTxHandler(
				$this->permissionManagerMock,
				$this->verificationEngine,
				$this->witnessingEngine
			),
			$this->requestData
		);
	}

	public function testWitnessDataBeforeGenerate(): void {
		$this->expectExceptionObject(
			new HttpException( "Not found", 404 )
		);
		$this->executeHandler(
			new GetWitnessDataHandler( $this->witnessingEngine ),
			new RequestData( [ 'pathParams' => [ 'witness_event_id' => 1 ] ] )
		);
	}

	public function testWitness(): void {
		$sp = new SpecialWitness(
			$this->permissionManagerMock,
			$this->lb,
			$this->titleFactory,
			$this->verificationEngine,
			$this->witnessingEngine
		);
		$sp->getOutput()->setTitle( Title::newFromText( 'Witness' ) );
		// Test that generating domain manifest works.
		$sp->generateDomainManifest( [] );

		// Test for getWitnessData.
		$data = $this->executeHandlerAndGetBodyData(
			new GetWitnessDataHandler( $this->witnessingEngine ),
			new RequestData( [ 'pathParams' => [ 'witness_event_id' => 1 ] ] )
		);
		$keys = [
			"domain_id",
			"domain_manifest_title",
			"witness_hash",
			"witness_event_verification_hash",
			"witness_network",
			"smart_contract_address",
			"domain_manifest_genesis_hash",
			"merkle_root",
			"witness_event_transaction_hash",
			"sender_account_address",
			"source"
		];
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		$this->assertSame( null, $data["witness_hash"] );
		$this->assertSame(
			"PUBLISH WITNESS HASH TO BLOCKCHAIN TO POPULATE",
			$data["witness_event_transaction_hash"]
		);
		$this->assertSame(
			"PUBLISH WITNESS HASH TO BLOCKCHAIN TO POPULATE",
			$data["sender_account_address"]
		);
		$this->assertSame( null, $data["source"] );

		// Publish!
		$data = $this->executeHandlerAndGetBodyData(
			new WriteStoreWitnessTxHandler(
				$this->permissionManagerMock,
				$this->verificationEngine,
				$this->witnessingEngine
			),
			$this->requestData
		);
		$this->assertSame( [ "value" => true ], $data );

		// Test for getWitnessData after publish.
		$data = $this->executeHandlerAndGetBodyData(
			new GetWitnessDataHandler( $this->witnessingEngine ),
			new RequestData( [ 'pathParams' => [ 'witness_event_id' => 1 ] ] )
		);
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		$this->assertNotNull( $data["witness_hash"] );
		$this->assertSame(
			"0x473b0b7b9ad818b9af02c0ab73cd9b186b28b6208c13f7d07554ace0915ca88e",
			$data["witness_event_transaction_hash"]
		);
		$this->assertSame(
			"0xa2026582b94feb9124231fbf7b052c39218954c2",
			$data["sender_account_address"]
		);
		$this->assertSame( "default", $data["source"] );
	}
}
