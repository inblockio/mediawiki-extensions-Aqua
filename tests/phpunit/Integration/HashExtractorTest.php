<?php

namespace DataAccounting\Tests\Integration;

use DataAccounting\Util\TransclusionHashExtractor;
use DataAccounting\Verification\Entity\VerificationEntity;

/**
 * @group Database
 */
class HashExtractorTest extends \MediaWikiIntegrationTestCase {
	/** @var TransclusionHashExtractor */
	private $hashExtractor;

	protected function setUp(): void {
		parent::setUp();

		$this->insertPage( 'Template:A', 'A' );
		$this->insertPage( 'Template:B', '{{A}}B' );
		$pageContent = file_get_contents( dirname( dirname( __DIR__ ) ) . '/fixtures/transclusion.txt' );
		$this->insertPage( 'Test_Transclusion', $pageContent );

		$title = $this->getServiceContainer()->getTitleFactory()->newFromText( 'Test_Transclusion' );
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->parse( $pageContent, $title, \ParserOptions::newFromAnon() );
		$this->hashExtractor = new TransclusionHashExtractor(
			$pageContent,
			$title,
			$parser->getOutput(),
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getService( 'DataAccountingVerificationEngine' ),
			$this->getServiceContainer()->getParserFactory()
		);
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
	public function testExtraction() {
		$hashMap = $this->hashExtractor->getHashmap();
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
}
