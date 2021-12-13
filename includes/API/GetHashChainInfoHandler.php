<?php

namespace DataAccounting\API;

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use Wikimedia\ParamValidator\ParamValidator;
use Title;
use TitleFactory;
use Wikimedia\Rdbms\LoadBalancer;

require_once __DIR__ . "/../ApiUtil.php";
require_once __DIR__ . "/../Util.php";

class GetHashChainInfoHandler extends ContextAuthorized {

	/**
	 * @var LoadBalancer
	 */
	protected $loadBalancer;

	/**
	 * @var TitleFactory
	 */
	protected $titleFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
			PermissionManager $permissionManager,
			TitleFactory $titleFactory,
			LoadBalancer $loadBalancer
		) {
		parent::__construct( $permissionManager );
		$this->loadBalancer = $loadBalancer;
		$this->titleFactory = $titleFactory;
	}

	/** @inheritDoc */
	public function run( string $id_type, string $id ) {
		// Genesis hash
		$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		if ( $id_type === 'title' ) {
			$res = $dbr->selectRow(
				'revision_verification',
				'genesis_hash',
				[ 'page_title' => $id ],
			);
			if ( !$res ) {
				throw new HttpException( "Not found", 404 );
			}
			$genesisHash = $res->genesis_hash;
		} else {
			$genesisHash = $id;
		}

		// Latest verification hash
		$resVH = $dbr->selectRow(
			'revision_verification',
			'verification_hash',
			[ 'genesis_hash' => $genesisHash ],
			__METHOD__,
			[ 'ORDER BY' => 'rev_id DESC' ]
		);
		if ( !$resVH ) {
			throw new HttpException( "Not found", 404 );
		}

		return [
			'genesis_hash' => $genesisHash,
			'domain_id' => getDomainID(),
			'latest_verification_hash' => $resVH->verification_hash
		];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'id_type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => [ 'genesis_hash', 'title' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	private function getPageNameFromGenesisHash( string $genesisHash ) {
		$res = $this->loadBalancer->getConnectionRef( DB_REPLICA )->selectRow(
			'revision_verification',
			'page_title',
			[ 'genesis_hash' => $genesisHash ],
		);
		if ( !$res ) {
			throw new HttpException( "Not found", 404 );
		}
		return $res->page_title;
	}

	/** @inheritDoc */
	protected function provideTitle( string $id_type, string $id ): ?Title {
		if ( $id_type === 'title' ) {
			return $this->titleFactory->newFromText( $id );
		}
		$pageName = $this->getPageNameFromGenesisHash( $id );
		return $this->titleFactory->newFromText( $pageName );
	}
}
