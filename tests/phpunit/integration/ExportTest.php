<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use DataAccounting\Transfer\Exporter;
use DataAccounting\Transfer\ExportSpecification;
use DataAccounting\Verification\Entity\VerificationEntity;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \DataAccounting\Hooks
 */
class ExportTest extends MediaWikiIntegrationTestCase {

	public function testVerificationDataIsPresentInOutput(): void {
		/** @var Exporter $exporter */
		$exporter = MediaWikiServices::getInstance()->getService( 'DataAccountingExporter' );

		$title = $this->insertPage( 'ExportTestPage' )['title'];
		$contents = $exporter->getExportContents(
			new ExportSpecification( [
				[ $title ]
			] )
		);

		$this->assertArrayHasKey( 'pages', $contents, 'Export contents must have \"pages\" key' );

		$pages = $contents['pages'];
		$this->assertTrue( count( $pages ) === 1, 'Only one page should be exported' );

		$page = $pages[0];
		$this->assertArrayHasKey( 'revisions', $page, 'Exported page must have \"revisions\"' );
		$this->assertArrayHasKey( VerificationEntity::GENESIS_HASH, $page );
		$this->assertArrayHasKey( VerificationEntity::DOMAIN_ID, $page );
		$this->assertArrayHasKey( 'latest_verification_hash', $page );
		$this->assertArrayHasKey( 'title', $page );
		$this->assertArrayHasKey( 'namespace', $page );
		$this->assertArrayHasKey( 'chain_height', $page );
		$this->assertEquals( 'ExportTestPage', $page['title'] );
		$this->assertEquals( 0, $page['namespace'] );
		$this->assertEquals( 1, $page['chain_height'] );

		foreach ( $page['revisions'] as $vh => $revision ) {
			$revision = $revision->jsonSerialize();
			$this->assertArrayHasKey( 'verification_context', $revision );
			$this->assertArrayHasKey( 'content', $revision );
			$this->assertArrayHasKey( 'metadata', $revision );
			$this->assertArrayHasKey( 'signature', $revision );
			$this->assertArrayHasKey( 'witness', $revision );

			$this->assertArrayHasKey( VerificationEntity::CONTENT_HASH, $revision['content'] );
			$this->assertArrayHasKey( VerificationEntity::VERIFICATION_HASH, $revision['metadata'] );
			$this->assertArrayHasKey( VerificationEntity::METADATA_HASH, $revision['metadata'] );
			$this->assertArrayHasKey( 'time_stamp', $revision['metadata'] );
			$this->assertArrayHasKey( 'previous_verification_hash', $revision['metadata'] );
			$this->assertArrayHasKey( 'domain_id', $revision['metadata'] );
		}
	}

}
