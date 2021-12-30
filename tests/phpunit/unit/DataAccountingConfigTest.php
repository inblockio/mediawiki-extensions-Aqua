<?php

namespace DataAccounting\Tests\Unit;

use DataAccounting\Config\DataAccountingConfig;
use MediaWikiIntegrationTestCase;
use GlobalVarConfig;

/**
 * @group Database
 */
class DataAccountingConfigTest extends MediaWikiIntegrationTestCase {
	private array $configData;
	private array $configDataOverride;

	protected function setUp(): void {
		parent::setUp();

		$this->configData = [
			'TestString' => 'test',
			'TestInt' => 314,
			'TestArray' => [
				'test',
				314
			],
			'TestMultiArray' => [
				'entry1' => 'test',
				'entry2' => 314
			]
		];
		$this->configDataOverride = [
			'TestString' => 'test2',
			'TestInt' => 314159,
			'TestArray' => [
				'test2',
				314159
			],
			'TestMultiArray' => [
				'entry1' => 'test2',
				'entry2' => 314159
			]
		];
	}

	/**
	 * @covers \DataAccounting\Config\Handler::configFactoryCallback
	 */
	public function testDAConfigFactory() {
		$config = $this->getServiceContainer()->getConfigFactory()->makeConfig( 'da' );
		$this->assertInstanceOf( DataAccountingConfig::class, $config );
	}

	/**
	 * @covers \DataAccounting\Config\Handler::getConfig
	 */
	public function testGetDAConfig() {
		$handler = $this->getServiceContainer()->getService( 'DataAccountingConfigHandler' );
		$this->assertInstanceOf( DataAccountingConfig::class, $handler->getConfig() );
	}

	/**
	 * @covers \DataAccounting\Config\DataAccountingConfig::get
	 */
	public function testExtendMWConfig() {
		$config = $this->getServiceContainer()->getMainConfig();
		$daConfig = $this->getServiceContainer()->getConfigFactory()->makeConfig( 'da' );
		foreach ( $this->configData as $key => $val ) {
			$this->setMwGlobals( "wg$key", $val );
		}
		foreach ( $this->configData as $key => $val ) {
			$this->assertTrue( $config->has( $key ) );
		}
		foreach ( $this->configData as $key => $val ) {
			$this->assertTrue( $daConfig->has( $key ) );
		}
		foreach ( $this->configData as $key => $val ) {
			$this->assertEquals( $val, $daConfig->get( $key ) );
		}
	}

	/**
	 * @covers \DataAccounting\Config\DataAccountingConfig::get
	 */
	public function testOverrideMWConfig() {
		$daConfig = $this->getServiceContainer()->getConfigFactory()->makeConfig( 'da' );
		foreach ( $this->configData as $key => $val ) {
			$this->setMwGlobals( "wg$key", $val );
		}
		foreach ( $this->configDataOverride as $key => $val ) {
			$this->setMwGlobals( "da$key", $val );
		}
		foreach ( $this->configDataOverride as $key => $val ) {
			$this->assertEquals( $val, $daConfig->get( $key ) );
		}
	}

	/**
	 * @covers \DataAccounting\Config\DataAccountingConfig::set
	 */
	public function testSetDBConfigFail() {
		$daConfig = $this->getServiceContainer()->getConfigFactory()->makeConfig( 'da' );
		$key = $value = 'nonExistent';
		$this->expectExceptionObject(
			new \Exception( "The config '$key' does not exist within the da config prefix" )
		);
		$daConfig->set( $key, $value );
	}

	/**
	 * @covers \DataAccounting\Config\DataAccountingConfig::set
	 */
	public function testSetDBConfigSuccess() {
		$daConfig = $this->getServiceContainer()->getConfigFactory()->makeConfig( 'da' );
		foreach ( $this->configData as $key => $val ) {
			$this->setMwGlobals( "da$key", $val );
		}
		foreach ( $this->configDataOverride as $key => $val ) {
			$daConfig->set( $key, $val );
		}
		foreach ( $this->configDataOverride as $key => $val ) {
			$this->assertEquals( $val, $daConfig->get( $key ) );
		}
	}
}
