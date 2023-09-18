<?php

namespace DataAccounting\Override\Storage;

use BagOStuff;
use Content;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentity;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Storage\PageEditStash;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Override to prevent stashing edits, because it will mess up
 * with custom slots
 */
class DAPageEditStash extends PageEditStash {
	/**
	 * @param BagOStuff $cache
	 * @param ILoadBalancer $lb
	 * @param LoggerInterface $logger
	 * @param StatsdDataFactoryInterface $stats
	 * @param UserEditTracker $userEditTracker
	 * @param UserFactory $userFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param HookContainer $hookContainer
	 * @param int $initiator Class INITIATOR__* constant
	 */
	public function __construct(
		BagOStuff $cache,
		ILoadBalancer $lb,
		LoggerInterface $logger,
		StatsdDataFactoryInterface $stats,
		UserEditTracker $userEditTracker,
		UserFactory $userFactory,
		WikiPageFactory $wikiPageFactory,
		HookContainer $hookContainer,
		$initiator
	) {
		parent::__construct(
			$cache,
			$lb,
			$logger,
			$stats,
			$userEditTracker,
			$userFactory,
			$wikiPageFactory,
			$hookContainer,
			$initiator
		);
	}

	public function parseAndCache( $pageUpdater, Content $content, UserIdentity $user, string $summary ) {
		return static::ERROR_UNCACHEABLE;
	}

	public function checkCache( PageIdentity $page, Content $content, UserIdentity $user ) {
		return false;
	}
}
