<?php

namespace DataAccounting\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\LoadBalancer;

class VerifyPageHandler extends ContextAuthorized {

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
		# Expects rev_id as input and returns verification_hash(required),
		# signature(optional), public_key(optional), wallet_address(optional),
		# witness_id(optional)

		$res = $this->loadBalancer->getConnectionRef( DB_REPLICA )->selectRow(
			'revision_verification',
			[
				'rev_id',
				'domain_id',
				'verification_hash',
				'time_stamp',
				'signature',
				'public_key',
				'wallet_address',
				'witness_event_id'
			],
			[ 'rev_id' => $rev_id ],
			__METHOD__
		);

		if ( !$res ) {
			throw new HttpException( "Not found", 404 );
		}

		$output = [
			'rev_id' => $rev_id,
			'domain_id' => $res->domain_id,
			'verification_hash' => $res->verification_hash,
			'time_stamp' => $res->time_stamp,
			'signature' => $res->signature,
			'public_key' => $res->public_key,
			'wallet_address' => $res->wallet_address,
			'witness_event_id' => $res->witness_event_id,
		];
		return $output;
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
