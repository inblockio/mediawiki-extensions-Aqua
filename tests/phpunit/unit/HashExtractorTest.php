<?php

namespace DataAccounting\Tests\Unit;

use DataAccounting\Util\TransclusionHashExtractor;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationLookup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DataAccounting\Util\TransclusionHashExtractor
 */
class HashExtractorTest extends TestCase {
	private $titleFactory;
	private $verificationEngine;
	private $lookup;

	protected function setUp(): void {
		parent::setUp();

		$this->titleFactory = $this->createMock( \TitleFactory::class );
		$this->verificationEngine = $this->createMock( VerificationEngine::class );
		$this->lookup = $this->createMock( VerificationLookup::class );
		$this->verificationEngine->method( 'getLookup' )->willReturn( $this->lookup );
	}

	/**
	 * @param array $inputData
	 * @param array $expected
	 * @dataProvider provideTestData
	 */
	public function testGetHashmap( $inputData, $expected ) {
		$poMock = $this->createMock( \ParserOutput::class );
		$poMock->method( 'getTemplates' )->willReturn( $inputData['templates'] );
		$poMock->method( 'getImages' )->willReturn( $inputData['images'] );
		$poMock->method( 'getLinks' )->willReturn( $inputData['links'] );

		$this->titleFactory->method( 'makeTitle' )->willReturnCallback(
			function( $ns, $dbkey ) use ( $inputData ) {
				if ( !isset( $inputData['revs'][$dbkey] ) ) {
					return \Title::makeTitle( $ns, $dbkey );
				}
				$mock = $this->createMock( \Title::class );
				$mock->method( 'getDBkey' )->willReturn( $dbkey );
				$mock->method( 'getNamespace' )->willReturn( $ns );
				$mock->method( 'getLatestRevID' )->willReturn( $inputData['revs'][$dbkey] );
				$mock->method( 'exists' )->willReturn( true );
				return $mock;
			}
		);

		$this->lookup->method( 'verificationEntityFromRevId' )->willReturnCallback(
			function( $revId ) use ( $inputData ) {
				$mock = $this->createMock( VerificationEntity::class );
				if ( isset( $inputData['hashes'][$revId] ) ) {
					$hashes = $inputData['hashes'][$revId];
					$mock->method( 'getHash' )->willReturnCallback(
						function( $type ) use ( $hashes ) {
							return $hashes[$type] ?? null;
						}
					);
				}
				return $mock;
			}
		);

		$subject = $this->titleFactory->makeTitle( NS_HELP, 'Subject' );
		$extractor = new TransclusionHashExtractor(
			$subject, $poMock, $this->titleFactory, $this->verificationEngine
		);

		$this->assertSame( $expected, $extractor->getHashmap() );
	}

	public function provideTestData() {
		return [
			[
				[
					'templates' => [],
					'images' => [],
					'links' => []
				],
				[]
			],
			[
				[
					'templates' => [
						NS_TEMPLATE => [
							'Foo' => 1,
							'Bar' => 2,
						],
						// This is the subject page, so should not be included (self-reference)
						NS_HELP => [
							'Subject' => 0,
						],
						NS_MEDIAWIKI => [
							'Test.js' => 0
						]
					],
					'images' => [
						'File:Test.png' => null,
						'File:Test.jpg' => null
					],
					'links' => [],
					'revs' => [
						'Foo' => 123
					],
					'hashes' => [
						123 => [
							VerificationEntity::GENESIS_HASH => '123',
							VerificationEntity::VERIFICATION_HASH => '456',
							VerificationEntity::CONTENT_HASH => '789'
						]
					]
				],
				[
					[
						'dbkey' => 'File:Test.png',
						'ns' => NS_FILE,
						'revid' => 0,
						VerificationEntity::GENESIS_HASH => null,
						VerificationEntity::VERIFICATION_HASH => null,
						VerificationEntity::CONTENT_HASH => null,
					],
					[
						'dbkey' => 'File:Test.jpg',
						'ns' => NS_FILE,
						'revid' => 0,
						VerificationEntity::GENESIS_HASH => null,
						VerificationEntity::VERIFICATION_HASH => null,
						VerificationEntity::CONTENT_HASH => null,
					],
					[
						'dbkey' => 'Foo',
						'ns' => NS_TEMPLATE,
						'revid' => 123,
						VerificationEntity::GENESIS_HASH => '123',
						VerificationEntity::VERIFICATION_HASH => '456',
						VerificationEntity::CONTENT_HASH => '789',
					],
					[
						'dbkey' => 'Bar',
						'ns' => NS_TEMPLATE,
						'revid' => 0,
						VerificationEntity::GENESIS_HASH => null,
						VerificationEntity::VERIFICATION_HASH => null,
						VerificationEntity::CONTENT_HASH => null,
					],
					[
						'dbkey' => 'Test.js',
						'ns' => NS_MEDIAWIKI,
						'revid' => 0,
						VerificationEntity::GENESIS_HASH => null,
						VerificationEntity::VERIFICATION_HASH => null,
						VerificationEntity::CONTENT_HASH => null,
					]
				]
			]
		];
	}
}
