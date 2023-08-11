<?php

namespace DataAccounting\Override\Storage;

use JobQueueGroup;
use Language;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Parsoid\ParsoidOutputAccess;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRenderer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Storage\DerivedPageDataUpdater;
use MediaWiki\Storage\EditResultCache;
use MediaWiki\Storage\PageEditStash;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\PageUpdaterFactory;
use MediaWiki\User\TalkPageNotificationManager;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use MessageCache;
use ParserCache;
use Psr\Log\LoggerInterface;
use TitleFormatter;
use WANObjectCache;
use Wikimedia\Rdbms\ILBFactory;
use WikiPage;

/**
 * Due to everything being private is parent class,
 * we need to pretty much copy-paste all of the code
 */
class DAPageUpdaterFactory extends PageUpdaterFactory {
	/**
	 * Options that have to be present in the ServiceOptions object passed to the constructor.
	 * @note must include PageUpdater::CONSTRUCTOR_OPTIONS
	 * @internal
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::ArticleCountMethod,
		MainConfigNames::RCWatchCategoryMembership,
		MainConfigNames::PageCreationLog,
		MainConfigNames::UseAutomaticEditSummaries,
		MainConfigNames::ManualRevertSearchRadius,
		MainConfigNames::UseRCPatrol,
		MainConfigNames::ParsoidCacheConfig,
	];

	/** @var RevisionStore */
	private $revisionStore;

	/** @var ILBFactory */
	private $loadbalancerFactory;

	/** @var IContentHandlerFactory */
	private $contentHandlerFactory;

	/** @var HookContainer */
	private $hookContainer;

	/** @var LoggerInterface */
	private $logger;

	/** @var ServiceOptions */
	private $options;

	/** @var SlotRoleRegistry */
	private $slotRoleRegistry;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var TitleFormatter */
	private $titleFormatter;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var string[] */
	private $softwareTags;

	/**
	 * @param RevisionStore $revisionStore
	 * @param RevisionRenderer $revisionRenderer
	 * @param SlotRoleRegistry $slotRoleRegistry
	 * @param ParserCache $parserCache
	 * @param ParsoidOutputAccess $parsoidOutputAccess
	 * @param JobQueueGroup $jobQueueGroup
	 * @param MessageCache $messageCache
	 * @param Language $contLang
	 * @param ILBFactory $loadbalancerFactory
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param HookContainer $hookContainer
	 * @param EditResultCache $editResultCache
	 * @param UserNameUtils $userNameUtils
	 * @param LoggerInterface $logger
	 * @param ServiceOptions $options
	 * @param UserEditTracker $userEditTracker
	 * @param UserGroupManager $userGroupManager
	 * @param TitleFormatter $titleFormatter
	 * @param ContentTransformer $contentTransformer
	 * @param PageEditStash $pageEditStash
	 * @param TalkPageNotificationManager $talkPageNotificationManager
	 * @param WANObjectCache $mainWANObjectCache
	 * @param PermissionManager $permissionManager
	 * @param WikiPageFactory $wikiPageFactory
	 * @param string[] $softwareTags
	 */
	public function __construct(
		RevisionStore $revisionStore,
		RevisionRenderer $revisionRenderer,
		SlotRoleRegistry $slotRoleRegistry,
		ParserCache $parserCache,
		ParsoidOutputAccess $parsoidOutputAccess,
		JobQueueGroup $jobQueueGroup,
		MessageCache $messageCache,
		Language $contLang,
		ILBFactory $loadbalancerFactory,
		IContentHandlerFactory $contentHandlerFactory,
		HookContainer $hookContainer,
		EditResultCache $editResultCache,
		UserNameUtils $userNameUtils,
		LoggerInterface $logger,
		ServiceOptions $options,
		UserEditTracker $userEditTracker,
		UserGroupManager $userGroupManager,
		TitleFormatter $titleFormatter,
		ContentTransformer $contentTransformer,
		PageEditStash $pageEditStash,
		TalkPageNotificationManager $talkPageNotificationManager,
		WANObjectCache $mainWANObjectCache,
		PermissionManager $permissionManager,
		WikiPageFactory $wikiPageFactory,
		array $softwareTags
	) {
		parent::__construct( $revisionStore, $revisionRenderer, $slotRoleRegistry, $parserCache, $parsoidOutputAccess,
		$jobQueueGroup, $messageCache, $contLang, $loadbalancerFactory, $contentHandlerFactory, $hookContainer,
		$editResultCache, $userNameUtils, $logger, $options, $userEditTracker, $userGroupManager, $titleFormatter,
		$contentTransformer, $pageEditStash, $talkPageNotificationManager, $mainWANObjectCache, $permissionManager,
		$wikiPageFactory, $softwareTags );

		$this->loadbalancerFactory = $loadbalancerFactory;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->hookContainer = $hookContainer;
		$this->slotRoleRegistry = $slotRoleRegistry;
		$this->userEditTracker = $userEditTracker;
		$this->userGroupManager = $userGroupManager;
		$this->titleFormatter = $titleFormatter;
		$this->softwareTags = $softwareTags;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->options = $options;
		$this->logger = $logger;
		$this->revisionStore = $revisionStore;
	}

	public function newPageUpdater(
		PageIdentity $page,
		UserIdentity $user
	): PageUpdater {
		$page = $this->wikiPageFactory->newFromTitle( $page );

		return $this->newPageUpdaterForDerivedPageDataUpdater(
			$page,
			$user,
			$this->newDerivedPageDataUpdater( $page )
		);
	}

	/**
	 * Return a PageUpdater for building an update to a page, reusing the state of
	 * an existing DerivedPageDataUpdater.
	 *
	 * @param WikiPage $page
	 * @param UserIdentity $user
	 * @param DerivedPageDataUpdater $derivedPageDataUpdater
	 *
	 * @return PageUpdater
	 * @internal needed by WikiPage to back the WikiPage::newPageUpdater method.
	 *
	 * @since 1.37
	 */
	public function newPageUpdaterForDerivedPageDataUpdater(
		WikiPage $page,
		UserIdentity $user,
		DerivedPageDataUpdater $derivedPageDataUpdater
	): PageUpdater {
		$pageUpdater = new DAPageUpdater(
			$user,
			$page, // NOTE: eventually, PageUpdater should not know about WikiPage
			$derivedPageDataUpdater,
			$this->loadbalancerFactory->getMainLB(),
			$this->revisionStore,
			$this->slotRoleRegistry,
			$this->contentHandlerFactory,
			$this->hookContainer,
			$this->userEditTracker,
			$this->userGroupManager,
			$this->titleFormatter,
			new ServiceOptions(
				PageUpdater::CONSTRUCTOR_OPTIONS,
				$this->options
			),
			$this->softwareTags,
			$this->logger
		);

		$pageUpdater->setUsePageCreationLog(
			$this->options->get( MainConfigNames::PageCreationLog ) );
		$pageUpdater->setUseAutomaticEditSummaries(
			$this->options->get( MainConfigNames::UseAutomaticEditSummaries )
		);

		return $pageUpdater;
	}
}
