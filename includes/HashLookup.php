<?php

namespace DataAccounting;

use File;
use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

class HashLookup {
	public const HASH_TYPE_VERIFICATION = 'verification_hash';
	public const HASH_TYPE_CONTENT = 'hash_content';
	public const HASH_TYPE_METADATA = 'hash_metadata';
	public const HASH_TYPE_SIGNATURE = 'signature_hash';

	/** @var ILoadBalancer */
	private $lb;
	/** @var RevisionStore */
	private $revisionStore;

	public function __construct( ILoadBalancer $loadBalancer, RevisionStore $revisionStore ) {
		$this->lb = $loadBalancer;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @param Title $title
	 * @param string $type
	 * @return string|null
	 */
	public function getLatestHashForTitle( Title $title, $type = self::HASH_TYPE_VERIFICATION ): ?string {
		$res = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'revision_verification',
			[ $type ],
			[ 'page_title' => $title->getPrefixedDBkey() ],
			__METHOD__,
			[
				'ORDER BY' => [ 'rev_id DESC' ],
			]
		);

		if ( !$res ) {
			return null;
		}

		return $res->$type ?? null;
	}

	/**
	 * @param string $hash
	 * @return RevisionRecord|null
	 */
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

	/**
	 * @param RevisionRecord $revision
	 * @param string $type
	 * @return string|null
	 */
	public function getHashForRevision( RevisionRecord $revision, $type = self::HASH_TYPE_VERIFICATION ): ?string {
		$res = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'revision_verification',
			[ $type ],
			[ 'rev_id' => $revision->getId() ],
			__METHOD__
		);

		if ( !$res ) {
			return null;
		}

		return $res->$type ?? null;
	}
}
