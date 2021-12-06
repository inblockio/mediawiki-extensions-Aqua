<?php

namespace DataAccounting;

use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use Wikimedia\Rdbms\ILoadBalancer;

class HashLookup {
	/** @var ILoadBalancer */
	private $lb;
	/** @var RevisionStore */
	private $revisionStore;

	public function __construct( ILoadBalancer $loadBalancer, RevisionStore $revisionStore ) {
		$this->lb = $loadBalancer;
		$this->revisionStore = $revisionStore;
	}

	public function getRevisionForHash( string $hash ): ?RevisionRecord {
		$res = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'revision_verification',
			[ 'rev_id' ],
			[ 'verification_hash' => $hash ],
			__METHOD__
		);

		if ( !$res ) {
			return null;
		}

		return $this->revisionStore->getRevisionById( $res->rev_id );
	}
}
