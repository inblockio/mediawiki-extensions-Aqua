<?php

namespace DataAccounting\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\LoadBalancer;

require_once __DIR__ . "/../ApiUtil.php";

class GetRevisionHashesHandler extends ContextAuthorized {
	/**
	 * @var LoadBalancer
	 */
	protected $loadBalancer;

	/**
	 * @var RevisionLookup
	 */
	protected $revisionLookup;

	/**
	 * @param PermissionManager $permissionManager
	 * @param LoadBalancer $loadBalancer
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		PermissionManager $permissionManager,
		LoadBalancer $loadBalancer,
		RevisionLookup $revisionLookup
	) {
		parent::__construct( $permissionManager );
		$this->loadBalancer = $loadBalancer;
		$this->revisionLookup = $revisionLookup;
	}

	/** @inheritDoc */
	public function run( $verification_hash ) {
		$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		// TODO this is a duplicated DB call already done in provideTitle. Optimize!
		$res = $dbr->selectRow(
			'revision_verification',
			[ 'rev_id', 'genesis_hash' ],
			[ 'verification_hash' => $verification_hash ],
		);
		$revId = $res->rev_id;
		$genesisHash = $res->genesis_hash;

		// Now we fetch the hashes
		$resHashes = $dbr->select(
			'revision_verification',
			[ 'verification_hash' ],
			[
				'genesis_hash' => $genesisHash,
				"rev_id >= $revId"
			],
			__METHOD__,
			[ 'ORDER BY' => 'rev_id' ]
		);

		$output = [];
		foreach ( $resHashes as $row ) {
			array_push( $output, $row->verification_hash );
		}
		return $output;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'verification_hash' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function provideTitle( string $verification_hash ): ?Title {
		// TODO need discussion on a way to speed up provideTitle without this
		// extra DB lookup.
		$res = $this->loadBalancer->getConnectionRef( DB_REPLICA )->selectRow(
			'revision_verification',
			'rev_id',
			[ 'verification_hash' => $verification_hash ],
		);
		if ( !$res ) {
			throw new HttpException( "Not found", 404 );
		}
		$revId = $res->rev_id;

		$revisionRecord = $this->revisionLookup->getRevisionById( $revId );
		if ( !$revisionRecord ) {
			throw new HttpException( "Not found", 404 );
		}
		return $revisionRecord->getPageAsLinkTarget();
	}
}
