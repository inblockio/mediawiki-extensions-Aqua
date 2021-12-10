<?php

namespace DataAccounting\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\LoadBalancer;

class RequestHashHandler extends ContextAuthorized {

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
	public function run( $rev_id ) {
		$res = $this->loadBalancer->getConnectionRef( DB_REPLICA )->selectRow(
			'revision_verification',
			[ 'rev_id', 'verification_hash' ],
			[ 'rev_id' => $rev_id ],
			__METHOD__
		);

		if ( !$res ) {
			throw new HttpException( "rev_id not found in the database", 404 );
		}
		return 'I sign the following page verification_hash: [0x' . $res->verification_hash . ']';
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'rev_id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function provideTitle( int $revId ): ?Title {
		$revisionRecord = $this->revisionLookup->getRevisionById( $revId );
		if ( !$revisionRecord ) {
			throw new HttpException( "Not found", 404 );
		}
		return $revisionRecord->getPageAsLinkTarget();
	}
}
