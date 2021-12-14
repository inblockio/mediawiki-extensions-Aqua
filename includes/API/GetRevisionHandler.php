<?php

namespace DataAccounting\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\LoadBalancer;
use MediaWiki\Revision\SlotRecord;
use function DataAccounting\getWitnessData;
use function DataAccounting\requestMerkleProof;

require_once __DIR__ . "/../ApiUtil.php";

class GetRevisionHandler extends ContextAuthorized {

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
		$res = $this->loadBalancer->getConnectionRef( DB_REPLICA )->selectRow(
			'revision_verification',
			[
				'verification_context',
				// content
				'rev_id',
				'content_hash',
				// metadata
				'domain_id',
				'time_stamp',
				'previous_verification_hash',
				'metadata_hash',
				// signature
				'signature',
				'public_key',
				'wallet_address',
				'signature_hash',
				'witness_event_id'
			],
			[ 'verification_hash' => $verification_hash ],
			__METHOD__
		);

		if ( !$res ) {
			throw new HttpException( "Not found", 404 );
		}
		$revisionRecord = $this->revisionLookup->getRevisionById( $res->rev_id );

		$contentOutput = [
			'rev_id' => $res->rev_id,
			'content' => $revisionRecord->getContent( SlotRecord::MAIN )->serialize(),
			'content_hash' => $res->content_hash,
		];

		$metadataOutput = [
			'domain_id' => $res->domain_id,
			'time_stamp' => $res->time_stamp,
			'previous_verification_hash' => $res->previous_verification_hash,
			'metadata_hash' => $res->metadata_hash,
		];

		$signatureOutput = [
			'signature' => $res->signature,
			'public_key' => $res->public_key,
			'wallet_address' => $res->wallet_address,
			'signature_hash' => $res->signature_hash,
		];

		$witnessOutput = null;
		if ( $res->witness_event_id !== null ) {
			// TODO harden these 2 steps.
			$witnessOutput = getWitnessData( $res->witness_event_id );
			$witnessOutput['structured_merkle_proof'] = requestMerkleProof( $res->witness_event_id, $verification_hash );
		}

		$output = [
			'verification_context' => $res->verification_context,
			'content' => $contentOutput,
			'metadata' => $metadataOutput,
			'signature' => $signatureOutput,
			'witness' => $witnessOutput,
		];
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
