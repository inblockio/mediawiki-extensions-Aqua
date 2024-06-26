<?php

namespace DataAccounting\Tests\Integration;

use DataAccounting\Content\TransclusionHashes;
use DataAccounting\TransclusionManager;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationLookup;
use MediaWiki\Revision\RevisionRecord;
use Title;

/**
 * @covers \DataAccounting\TransclusionManager
 */
class TransclusionManagerTest extends \MediaWikiIntegrationTestCase {
	/**
	 * @covers \DataAccounting\TransclusionManager::getTransclusionState
	 * @covers \DataAccounting\TransclusionManager::getTransclusionHashesContent
	 */
	public function testGetTransclusionState() {
		$content = $this->createMock( TransclusionHashes::class );
		$content->method( 'getResourceHashes' )->willReturn(
			[
				(object)[
					'dbkey' => 'A',
					'ns' => NS_TEMPLATE,
					VerificationEntity::VERIFICATION_HASH => null
				],
				(object)[
					'dbkey' => 'B',
					'ns' => NS_TEMPLATE,
					VerificationEntity::VERIFICATION_HASH => '123'
				],
				(object)[
					'dbkey' => 'C',
					'ns' => NS_TEMPLATE,
					VerificationEntity::VERIFICATION_HASH => '456'
				],
				(object)[
					'dbkey' => 'D',
					'ns' => NS_TEMPLATE,
					VerificationEntity::VERIFICATION_HASH => '789'
				],
			]
		);

		$revisionMock = $this->createMock( RevisionRecord::class );
		$revisionMock->method( 'hasSlot' )->willReturn( true );
		$revisionMock->method( 'getContent' )->will(
			$this->returnCallback( static function ( $role ) use ( $content ) {
				if ( $role === TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES ) {
					return $content;
				}

				return null;
			} )
		);

		$lookupMock = $this->createMock( VerificationLookup::class );
		$lookupMock->method( 'verificationEntityFromTitle' )
			->willReturnCallback( function ( Title $title ) {
				if ( $title->getDBkey() === 'B' ) {
					$rev = $this->createMock( RevisionRecord::class );
					$rev->method( 'getId' )->willReturn( 2 );
					return new VerificationEntity(
						$title,
						$rev,
						'',
						[
							VerificationEntity::VERIFICATION_HASH => '000'
						],
						new \DateTime(), '', '', '', 0, ''
					);
				}
				if ( $title->getDBkey() === 'C' ) {
					$rev = $this->createMock( RevisionRecord::class );
					$rev->method( 'getId' )->willReturn( 4 );
					return new VerificationEntity(
						$title,
						$rev,
						'',
						[
							VerificationEntity::VERIFICATION_HASH => '456'
						],
						new \DateTime(), '', '', '', 0, ''
					);
				}

				return null;
			} );
		$lookupMock->method( 'verificationEntityFromQuery' )
			->willReturnCallback( function ( $query ) {
				if ( $query[VerificationEntity::VERIFICATION_HASH] === '123' ) {
					$rev = $this->createMock( RevisionRecord::class );
					$rev->method( 'getId' )->willReturn( 1 );
					return new VerificationEntity(
						$this->createMock( Title::class ),
						$rev,
						'',
						[
							VerificationEntity::VERIFICATION_HASH => '123'
						],
						new \DateTime(), '', '', '', 0, ''
					);
				}
				if ( $query[VerificationEntity::VERIFICATION_HASH] === '456' ) {
					$rev = $this->createMock( RevisionRecord::class );
					$rev->method( 'getId' )->willReturn( 4 );
					return new VerificationEntity(
						$this->createMock( Title::class ),
						$rev,
						'',
						[
							VerificationEntity::VERIFICATION_HASH => '456'
						],
						new \DateTime(), '', '', '', 0, ''
					);
				}

				return null;
			} );
		$verificationEngineMock = $this->createMock( VerificationEngine::class );
		$verificationEngineMock->method( 'getLookup' )->willReturn( $lookupMock );

		$titleFactoryMock = $this->createMock( \TitleFactory::class );
		$titleFactoryMock->method( 'makeTitle' )->willReturnCallback( function ( $ns, $dbkey ) {
			switch ( $dbkey ) {
				case 'A':
					$title = $this->createMock( Title::class );
					$title->method( 'getDBkey' )->willReturn( 'A' );
					$title->method( 'exists' )->willReturn( false );
					return $title;
				case 'B':
					$title = $this->createMock( Title::class );
					$title->method( 'getDBkey' )->willReturn( 'B' );
					$title->method( 'exists' )->willReturn( true );
					return $title;
				case 'C':
					$title = $this->createMock( Title::class );
					$title->method( 'getDBkey' )->willReturn( 'C' );
					$title->method( 'exists' )->willReturn( true );
					return $title;
				case 'D':
					$title = $this->createMock( Title::class );
					$title->method( 'getDBkey' )->willReturn( 'D' );
					$title->method( 'exists' )->willReturn( true );
					return $title;
				default:
					return null;
			}
		} );

		$transclusionManager = new TransclusionManager(
			$titleFactoryMock, $verificationEngineMock, $this->getServiceContainer()->getRevisionStore(),
			$this->getServiceContainer()->getPageUpdaterFactory(), $this->getServiceContainer()->getWikiPageFactory()
		);

		$state = $transclusionManager->getTransclusionState( $revisionMock );

		$this->assertArrayEquals( [
			[ 'A', TransclusionManager::STATE_NO_RECORD ],
			[ 'B', TransclusionManager::STATE_NEW_VERSION ],
			[ 'C', TransclusionManager::STATE_UNCHANGED ],
			[ 'D', TransclusionManager::STATE_INVALID ],
		], array_map( static function ( $stateItem ) {
			return [ $stateItem['titleObject']->getDBkey(), $stateItem['state'] ];
		}, $state ) );
	}
}
