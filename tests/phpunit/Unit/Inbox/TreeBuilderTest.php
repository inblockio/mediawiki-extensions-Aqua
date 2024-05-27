<?php

namespace DataAccounting\Tests\Inbox;

use DataAccounting\Inbox\TreeBuilder;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationLookup;
use Language;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use PHPUnit\Framework\TestCase;
use Title;

class TreeBuilderTest extends TestCase {

	/**
	 * @covers \DataAccounting\Inbox\TreeBuilder::buildPreImportTree
	 * @dataProvider provideData
	 */
	public function testBuildPreImportTree( $local, $remote, $expected ) {
		$linkTarget = $this->createMock( Title::class );
		$linkTarget->method( 'getFullURL' )->willReturn( '' );
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageAsLinkTarget' )->willReturn( $linkTarget );
		$revisionLookupMock = $this->createMock( RevisionLookup::class );
		$revisionLookupMock->method( 'getRevisionById' )->willReturn( $revision );

		$builder = new TreeBuilder(
			$this->getVerificationEngineMock( $local, $remote ), $revisionLookupMock
		);
		$data = $builder->buildPreImportTree(
			$this->makeTitleMock( $remote ),
			$this->makeTitleMock( $local ),
			$this->createMock( Language::class ),
			$this->createMock( UserIdentity::class )
		);
		unset( $data['remote'] );
		unset( $data['local'] );
		foreach ( $data['tree'] as &$entry ) {
			// This is just for display purposes, not tested
			$entry['revisionData'] = [];
		}
		$this->assertSame( $expected, $data );
	}

	/**
	 * @return array[]
	 */
	public function provideData() {
		return [
			'remote-changes' => [
				// [ page_id, [ rev_id => hash ] ]
				'localPage' => [ 1, [ 1 => 'h1', 2 => 'h2' ] ],
				'remotePage' => [ 2, [ 3 => 'h1', 4 => 'h2', 5 => 'h3', 6 => 'h4' ] ],
				'expectedTree' => [
					'tree' => [
						'h1' => [
							'revision' => 1, 'diff' => false, 'source' => 'local', 'parent' => '',
							'domain' => '', 'revisionData' => []
						],
						'h2' => [
							'revision' => 2, 'diff' => false, 'source' => 'local', 'parent' => 'h1',
							'domain' => '', 'revisionData' => []
						],
						'h3' => [
							'revision' => 5, 'diff' => true, 'source' => 'remote', 'parent' => 'h2',
							'domain' => '', 'revisionData' => []
						],
						'h4' => [
							'revision' => 6, 'diff' => true, 'source' => 'remote', 'parent' => 'h3',
							'domain' => '', 'revisionData' => []
						],
					],
					'change-type' => 'remote'
				]
			],
			'local-changes' => [
				'localPage' => [ 1, [ 1 => 'h1', 2 => 'h2', 3 => 'h3' ] ],
				'remotePage' => [ 2, [ 4 => 'h1', 5 => 'h2' ] ],
				'expectedTree' => [
					'tree' => [
						'h1' => [
							'revision' => 1, 'diff' => false, 'source' => 'local', 'parent' => '',
							'domain' => '', 'revisionData' => []
						],
						'h2' => [
							'revision' => 2, 'diff' => false, 'source' => 'local', 'parent' => 'h1',
							'domain' => '', 'revisionData' => []
						],
						'h3' => [
							'revision' => 3, 'diff' => true, 'source' => 'local', 'parent' => 'h2',
							'domain' => '', 'revisionData' => []
						],
					],
					'change-type' => 'local',
				],
			],
			'local-and-remote-changes' => [
				'localPage' => [ 1, [ 1 => 'h1', 2 => 'h2', 3 => 'h3' ] ],
				'remotePage' => [ 2, [ 4 => 'h1', 5 => 'h2', 6 => 'h4', 7 => 'h5' ] ],
				'expectedTree' => [
					'tree' => [
						'h1' => [
							'revision' => 1, 'diff' => false, 'source' => 'local', 'parent' => '',
							'domain' => '', 'revisionData' => []
						],
						'h2' => [
							'revision' => 2, 'diff' => false, 'source' => 'local', 'parent' => 'h1',
							'domain' => '', 'revisionData' => []
						],
						'h3' => [
							'revision' => 3, 'diff' => true, 'source' => 'local', 'parent' => 'h2',
							'domain' => '', 'revisionData' => []
						],
						'h4' => [
							'revision' => 6, 'diff' => true, 'source' => 'remote', 'parent' => 'h2',
							'domain' => '', 'revisionData' => []
						],
						'h5' => [
							'revision' => 7, 'diff' => true, 'source' => 'remote', 'parent' => 'h4',
							'domain' => '', 'revisionData' => []
						],
					],
					'change-type' => 'both',
				],
			]
		];
	}

	/**
	 * @param array $local
	 * @param array $remote
	 *
	 * @return VerificationEngine|VerificationEngine&\PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function getVerificationEngineMock( $local, $remote ) {
		$lookup = $this->createMock( VerificationLookup::class );
		$lookup->method( 'allVerificationEntitiesFromQuery' )->willReturnCallback( function( $conds ) use ( $local, $remote ) {
			if ( $conds['page_id'] === $local[0] ) {
				return $this->makeEntitiesMock( $local );
			}
			if ( $conds['page_id'] === $remote[0] ) {
				return $this->makeEntitiesMock( $remote );
			}
			return [];
		} );
		$lookup->method( 'verificationEntityFromTitle' )->willReturnCallback( function( $title ) use ( $local, $remote ) {
			if ( $title->getArticleID() === $local[0] ) {
				$entities = $this->makeEntitiesMock( $local );
			}
			if ( $title->getArticleID() === $remote[0] ) {
				$entities = $this->makeEntitiesMock( $remote );
			}
			return array_pop( $entities );
		} );
		$engineMock = $this->createMock( VerificationEngine::class );
		$engineMock->method( 'getLookup' )->willReturn( $lookup );
		return $engineMock;
	}

	/**
	 * @param $data
	 *
	 * @return \PHPUnit\Framework\MockObject\MockObject|Title|Title&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function makeTitleMock( $data ) {
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'getArticleID' )->willReturn( $data[0] );
		return $titleMock;
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	private function makeEntitiesMock( $data ) {
		$revHashes = $data[1];

		$entities = [];
		foreach ( $revHashes as $revId => $hash ) {
			$revision = $this->createMock( RevisionRecord::class );
			$revision->method( 'getId' )->willReturn( $revId );
			$title = $this->makeTitleMock( $data );

			$entity = $this->createMock( VerificationEntity::class );
			$entity->method( 'getRevision' )->willReturn( $revision );
			$entity->method( 'getTitle' )->willReturn( $title );
			$entity->method( 'getHash' )->willReturnCallback( function( $type ) use ( $revId, $revHashes, $hash ) {
				if ( !$type || $type === VerificationEntity::VERIFICATION_HASH ) {
					return $hash;
				}
				if ( $type === VerificationEntity::PREVIOUS_VERIFICATION_HASH ) {
					if ( array_keys( $revHashes )[0] !== $revId ) {
						return $revHashes[$revId - 1];
					}
				}
				return '';
			} );
			$entities[] = $entity;
		}

		return $entities;
	}
}
