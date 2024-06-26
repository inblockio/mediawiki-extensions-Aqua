<?php

namespace DataAccounting\API;

use DateTime;
use InvalidArgumentException;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\SimpleHandler;
use TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ILoadBalancer;

class RecentChangesHandler extends SimpleHandler {

	/** @var PermissionManager */
	private $permissionManager;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param ILoadBalancer $loadBalancer
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		PermissionManager $permissionManager, ILoadBalancer $loadBalancer, TitleFactory $titleFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->loadBalancer = $loadBalancer;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @return true
	 */
	public function needsReadAccess() {
		return true;
	}

	/** @inheritDoc */
	public function run() {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$params = $this->getValidatedParams();
		$limit = $params['count'];
		$since = $params['since'] ?? null;

		$conds = [];
		if ( $since ) {
			$time = DateTime::createFromFormat( 'YmdHis', $since );
			if ( !$time ) {
				throw new InvalidArgumentException( 'Invalid value for "since" parameter' );
			}
			$conds[] = 'page_touched >= ' . $db->addQuotes( $time->format( 'YmdHis' ) );
		}

		$res = $db->select(
			[ 'p' => 'page', 'rv' => 'revision_verification' ],
			[
				'p.page_id', 'p.page_title', 'p.page_namespace', 'p.page_latest',
				'p.page_touched', 'rv.verification_hash'
			],
			$conds,
			__METHOD__,
			[ 'LIMIT' => $limit, 'ORDER BY' => 'rev_id DESC' ],
			[
				'rv' => [ 'INNER JOIN', 'page_latest = rev_id' ]
			]
		);

		$changes = [];
		foreach ( $res as $row ) {
			$title = $this->titleFactory->newFromRow( $row );
			if ( !$title->exists() ) {
				// for sanity
				continue;
			}
			$changes[] = [
				'title' => $title->getPrefixedText(),
				'revision' => (int)$row->page_latest,
				'hash' => $row->verification_hash,
				'touched' => $row->page_touched,
			];
		}

		return $this->getResponseFactory()->createJson( $changes );
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'count' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 500,
			],
			'since' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
