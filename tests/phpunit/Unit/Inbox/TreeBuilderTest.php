<?php

namespace DataAccounting\Tests\Inbox;

use DataAccounting\Inbox\TreeBuilder;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationLookup;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;
use Title;

class TreeBuilderTest extends TestCase {

	/**
	 * @covers \DataAccounting\Inbox\TreeBuilder::buildPreImportTree
	 * @dataProvider provideData
	 */
	public function testBuildPreImportTree( $local, $remote, $expected ) {
		$builder = new TreeBuilder( $this->getVerificationEngineMock( $local, $remote ) );
		$data = $builder->buildPreImportTree( $this->makeTitleMock( $remote ), $this->makeTitleMock( $local ) );
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
						'h1' => [ 'revisions' => [ 1, 3 ], 'diff' => false, 'source' => 'local', 'parent' => '' ],
						'h2' => [ 'revisions' => [ 2, 4 ], 'diff' => false, 'source' => 'local', 'parent' => 'h1' ],
						'h3' => [ 'revisions' => [ 5 ], 'diff' => true, 'source' =>  'remote', 'parent' => 'h2' ],
						'h4' => [ 'revisions' => [ 6 ], 'diff' => true, 'source' => 'remote', 'parent' => 'h3'],
					],
					'change-type' => 'remote'
				]
			],
			'local-changes' => [
				'localPage' => [ 1, [ 1 => 'h1', 2 => 'h2', 3 => 'h3' ] ],
				'remotePage' => [ 2, [ 4 => 'h1', 5 => 'h2' ] ],
				'expectedTree' => [
					'tree' => [
						'h1' => [ 'revisions' => [ 1, 4 ], 'diff' => false, 'source' => 'local', 'parent' => '' ],
						'h2' => [ 'revisions' => [ 2, 5 ], 'diff' => false, 'source' => 'local', 'parent' => 'h1' ],
						'h3' => [ 'revisions' => [ 3 ], 'diff' => true, 'source' =>  'local', 'parent' => 'h2' ],
					],
					'change-type' => 'local',
				],
			],
			'local-and-remote-changes' => [
				'localPage' => [ 1, [ 1 => 'h1', 2 => 'h2', 3 => 'h3' ] ],
				'remotePage' => [ 2, [ 4 => 'h1', 5 => 'h2', 6 => 'h4', 7 => 'h5' ] ],
				'expectedTree' => [
					'tree' => [
						'h1' => [ 'revisions' => [ 1, 4 ], 'diff' => false, 'source' => 'local', 'parent' => ''  ],
						'h2' => [ 'revisions' => [ 2, 5 ], 'diff' => false, 'source' => 'local', 'parent' => 'h1'  ],
						'h3' => [ 'revisions' => [ 3 ], 'diff' => true, 'source' =>  'local', 'parent' => 'h2'  ],
						'h4' => [ 'revisions' => [ 6 ], 'diff' => true, 'source' => 'remote', 'parent' => 'h2' ],
						'h5' => [ 'revisions' => [ 7 ], 'diff' => true, 'source' => 'remote', 'parent' => 'h4' ],
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
						return $revHashes[$revId-1];
					}
				}
				return '';
			} );
			$entities[] = $entity;
		}

		return $entities;
	}
}