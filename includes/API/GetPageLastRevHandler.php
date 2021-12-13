<?php

namespace DataAccounting\API;

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use Title;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\LoadBalancer;

class GetPageLastRevHandler extends ContextAuthorized {

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
	 * @param LoadBalancer $loadBalancer
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		PermissionManager $permissionManager,
		LoadBalancer $loadBalancer,
		TitleFactory $titleFactory
	) {
		parent::__construct( $permissionManager );
		$this->loadBalancer = $loadBalancer;
		$this->titleFactory = $titleFactory;
	}

	/** @inheritDoc */
	public function run( $page_title ) {
		$res = $this->loadBalancer->getConnectionRef( DB_REPLICA )->selectRow(
			'revision_verification',
			[ 'rev_id', 'page_title', 'page_id', 'verification_hash' ],
			[ 'page_title' => $page_title ],
			__METHOD__,
			[ 'ORDER BY' => 'rev_id DESC' ]
		);
		if ( !$res ) {
			throw new HttpException( "Not found", 404 );
		}
		$output = [
			'page_title' => $res->page_title,
			'page_id' => $res->page_id,
			'rev_id' => $res->rev_id,
			'verification_hash' => $res->verification_hash,
		];
		return $output;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'page_title' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function provideTitle( string $pageName ): ?Title {
		return $this->titleFactory->newFromText( $pageName );
	}
}
