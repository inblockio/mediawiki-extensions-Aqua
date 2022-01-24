<?php

namespace DataAccounting\Tests\Integration;

use DataAccounting\Config\DataAccountingConfig;
use DataAccounting\Util\TransclusionHashExtractor;
use DataAccounting\Verification\Entity\VerificationEntity;
use Exception;
use ParserOutput;
use Title;

/**
 * @group Database
 */
class HashExtractorTest extends \MediaWikiIntegrationTestCase {
	/** @var string */
	private $pageContent = '';
	/** @var Title */
	private $title;
	/** @var ParserOutput */
	private $po;

	protected function setUp(): void {
		parent::setUp();

		$this->insertPage( 'Template:A', 'A' );
		$this->insertPage( 'Template:B', '{{A}}B' );
		$this->pageContent = file_get_contents( dirname( dirname( __DIR__ ) ) . '/fixtures/transclusion.txt' );
		$this->insertPage( 'Test_Transclusion', $this->pageContent );
		$this->title = $this->getServiceContainer()->getTitleFactory()->newFromText( 'Test_Transclusion' );
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->parse( $this->pageContent, $this->title, \ParserOptions::newFromAnon() );
		$this->po = $parser->getOutput();
	}

	/**
	 * @return bool
	 */
	public function needsDB() {
		return true;
	}

	/**
	 * @covers \DataAccounting\Util\TransclusionHashExtractor::getHashmap
	 */
	public function testStrictTransclusion() {
		$hashExtractor = $this->getHashExtractor( true );
		$hashMap = $hashExtractor->getHashmap();
		$this->assertTrue( count( $hashMap ) === 6 );
		$titlesFromMap = array_map( function( $trans ) {
			unset( $trans[VerificationEntity::VERIFICATION_HASH] );
			return $trans;
		}, $hashMap );

		$this->assertArrayEquals( [
			[
				'dbkey' => 'Foo.png',
				'ns' => NS_FILE
			],
			[
				'dbkey' => 'Bar.png',
				'ns' => NS_FILE
			],
			[
				'dbkey' => 'B',
				'ns' => NS_TEMPLATE
			],
			[
				'dbkey' => 'C',
				'ns' => NS_TEMPLATE
			],
			[
				'dbkey' => 'D',
				'ns' => NS_TEMPLATE
			],
			[
				'dbkey' => 'FromMain',
				'ns' => NS_MAIN
			]
		], $titlesFromMap );
	}

	/**
	 * @covers \DataAccounting\Util\TransclusionHashExtractor::getHashmap
	 */
	public function testNonStrictTransclusion() {
		$hashExtractor = $this->getHashExtractor( false );
		$hashMap = $hashExtractor->getHashmap();
		$this->assertTrue( count( $hashMap ) === 7 );
		$titlesFromMap = array_map( function( $trans ) {
			unset( $trans[VerificationEntity::VERIFICATION_HASH] );
			return $trans;
		}, $hashMap );

		$this->assertArrayEquals( [
			[
				'dbkey' => 'Foo.png',
				'ns' => NS_FILE
			],
			[
				'dbkey' => 'Bar.png',
				'ns' => NS_FILE
			],
			[
				'dbkey' => 'B',
				'ns' => NS_TEMPLATE
			],
			[
				'dbkey' => 'A',
				'ns' => NS_TEMPLATE
			],
			[
				'dbkey' => 'C',
				'ns' => NS_TEMPLATE
			],
			[
				'dbkey' => 'D',
				'ns' => NS_TEMPLATE
			],
			[
				'dbkey' => 'FromMain',
				'ns' => NS_MAIN
			]
		], $titlesFromMap );
	}

	/**
	 * @param bool $strict
	 * @return TransclusionHashExtractor
	 * @throws Exception
	 */
	private function getHashExtractor( bool $strict ) {
		$configMock = $this->createMock( DataAccountingConfig::class );
		$configMock->method( 'get' )->willReturn( $strict );

		return new TransclusionHashExtractor(
			$this->pageContent,
			$this->title,
			$this->po,
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
			$this->getServiceContainer()->getParserFactory(),
			$configMock
		);
	}
}
