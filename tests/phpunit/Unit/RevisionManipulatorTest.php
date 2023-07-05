<?php

namespace DataAccounting\Tests;

use DataAccounting\RevisionManipulator;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationLookup;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserIdentity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Cannot run tests on the container ATM
 *
 * @group broken
 */
class RevisionManipulatorTest extends TestCase {

	/**
	 *
	 * @covers \DataAccounting\RevisionManipulator::deleteRevisions
	 *
	 */
	public function testDeleteRevisions() {
		// Just a simple, basic functionality test, no integration test
		$manipulator = new RevisionManipulator(
			$this->getLBMock(),
			$this->getRevisionStoreMock(),
			$this->getVerificationEngineMock()
		);
		$revisionIds = [ 2, 3, 4 ];
		$manipulator->deleteRevisions( $revisionIds );
	}

	/**
	 * @covers \DataAccounting\RevisionManipulator::squashRevisions
	 */
	public function testSquashRevisions() {
		$revisionStoreMock = $this->getRevisionStoreMock();
		$revisionStoreMock->expects( $this->once() )->method( 'insertRevisionOn' );
		$revisionStoreMock->method( 'insertRevisionOn' )->willReturn(
			$revisionStoreMock->getRevisionById( 5 )
		);

		$manipulator = new RevisionManipulator(
			$this->getLBMock(),
			$revisionStoreMock,
			$this->getVerificationEngineMock()
		);
		$revisionIds = [ 2, 3, 4 ];
		$manipulator->squashRevisions( $revisionIds );
	}

	/**
	 * @return RevisionStore&MockObject
	 */
	private function getRevisionStoreMock() {
		$revisionStoreMock = $this->createMock( RevisionStore::class );
		$revisionStoreMock->method( 'getRevisionById' )->willReturnCallback( function ( $id )  {
			$revMock = $this->createMock( RevisionRecord::class );
			$revMock->method( 'getId' )->willReturn( $id );
			$revMock->method( 'getPageId' )->willReturn( 1 );
			$revMock->method( 'getPage' )->willReturnCallback( function() {
				$page = $this->createMock( PageIdentity::class );
				$page->method( 'getId' )->willReturn( 1 );
			} );
			$revMock->method( 'getTimestamp' )->willReturn( '20220101010101' );
			$revMock->method( 'getUser' )->willReturn( $this->createMock( UserIdentity::class ) );
			$revMock->method( 'getSlotRoles' )->willReturn( [ 'main' ] );
			$revMock->method( 'getContent' )->willReturn( $this->createMock( \Content::class ) );
		} );
		$revisionStoreMock->method( 'getPreviousRevision' )->willReturnCallback(
			static function ( $rev ) use ( $revisionStoreMock ) {
				$revisionStoreMock->getRevisionById( $rev->getId() - 1 );
			}
		);
		return $revisionStoreMock;
	}

	/**
	 * @return VerificationEngine&MockObject
	 */
	private function getVerificationEngineMock() {
		$lookupMock = $this->createMock( VerificationLookup::class );
		$lookupMock->expects( $this->exactly( 3 ) )
			->method( 'deleteForRevId' )
			->withConsecutive(
				[ 1 ],
				[ 2 ],
				[ 3 ],
			);
		$verificationEngineMock = $this->createMock( VerificationEngine::class );
		$verificationEngineMock->method( 'getLookup' )->willReturn( $lookupMock );
		$verificationEngineMock->expects( $this->once() )->method( 'buildAndUpdateVerificationData' );

		return $verificationEngineMock;
	}

	/**
	 * @return ILoadBalancer&MockObject
	 */
	private function getLBMock() {
		$dbMock = $this->createMock( \Database::class );
		$dbMock->expects( $this->exactly( 6 ) )
			->method( 'delete' )
			->withConsecutive(
				[ 'revision', [ 'rev_id' => 2 ] ],
				[ 'ip_changes', [ 'ipc_rev_id' => 2 ] ],
				[ 'revision', [ 'rev_id' => 3 ] ],
				[ 'ip_changes', [ 'ipc_rev_id' => 3 ] ],
				[ 'revision', [ 'rev_id' => 4 ] ],
				[ 'ip_changes', [ 'ipc_rev_id' => 4 ] ],
			);
		$dbMock->expects( $this->once() )
			->method( 'update' )
			->with(
				'page',
				[ 'page_latest' => 1 ],
				[ 'page_id' => 1 ],
				__METHOD__
			);
		$lbMock = $this->createMock( ILoadBalancer::class );
		$lbMock->method( 'getConnection' )->willReturn( $dbMock );

		return $lbMock;
	}
}
