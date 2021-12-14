<?php

namespace DataAccounting\Override\Revision;

use ActorMigration;
use CommentStore;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageStoreFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\RevisionStoreFactory;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Storage\BlobStoreFactory;
use MediaWiki\Storage\NameTableStoreFactory;
use MediaWiki\User\ActorStoreFactory;
use Psr\Log\LoggerInterface;
use TitleFactory;
use WANObjectCache;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\ILBFactory;

/**
 * Override of MediaWiki\Revision\RevisionStoreFactory
 * This is really bad, but there is no other way for now
 */
class DARevisionStoreFactory extends RevisionStoreFactory {
	/** @var BlobStoreFactory */
	private $blobStoreFactory;
	/** @var ILBFactory */
	private $dbLoadBalancerFactory;
	/** @var WANObjectCache */
	private $cache;
	/** @var LoggerInterface */
	private $logger;

	/** @var CommentStore */
	private $commentStore;
	/** @var ActorMigration */
	private $actorMigration;
	/** @var ActorStoreFactory */
	private $actorStoreFactory;
	/** @var NameTableStoreFactory */
	private $nameTables;

	/** @var SlotRoleRegistry */
	private $slotRoleRegistry;

	/** @var IContentHandlerFactory */
	private $contentHandlerFactory;

	/** @var PageStoreFactory */
	private $pageStoreFactory;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param ILBFactory $dbLoadBalancerFactory
	 * @param BlobStoreFactory $blobStoreFactory
	 * @param NameTableStoreFactory $nameTables
	 * @param SlotRoleRegistry $slotRoleRegistry
	 * @param WANObjectCache $cache
	 * @param CommentStore $commentStore
	 * @param ActorMigration $actorMigration
	 * @param ActorStoreFactory $actorStoreFactory
	 * @param LoggerInterface $logger
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param PageStoreFactory $pageStoreFactory
	 * @param TitleFactory $titleFactory
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		ILBFactory $dbLoadBalancerFactory,
		BlobStoreFactory $blobStoreFactory,
		NameTableStoreFactory $nameTables,
		SlotRoleRegistry $slotRoleRegistry,
		WANObjectCache $cache,
		CommentStore $commentStore,
		ActorMigration $actorMigration,
		ActorStoreFactory $actorStoreFactory,
		LoggerInterface $logger,
		IContentHandlerFactory $contentHandlerFactory,
		PageStoreFactory $pageStoreFactory,
		TitleFactory $titleFactory,
		HookContainer $hookContainer
	) {
		parent::__construct(
			$dbLoadBalancerFactory, $blobStoreFactory, $nameTables, $slotRoleRegistry, $cache,
			$commentStore, $actorMigration, $actorStoreFactory, $logger, $contentHandlerFactory,
			$pageStoreFactory, $titleFactory, $hookContainer
		);

		$this->dbLoadBalancerFactory = $dbLoadBalancerFactory;
		$this->blobStoreFactory = $blobStoreFactory;
		$this->slotRoleRegistry = $slotRoleRegistry;
		$this->nameTables = $nameTables;
		$this->cache = $cache;
		$this->commentStore = $commentStore;
		$this->actorMigration = $actorMigration;
		$this->actorStoreFactory = $actorStoreFactory;
		$this->logger = $logger;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->pageStoreFactory = $pageStoreFactory;
		$this->titleFactory = $titleFactory;
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @since 1.32
	 *
	 * @param bool|string $dbDomain DB domain of the relevant wiki or false for the current one
	 *
	 * @return RevisionStore for the given wikiId with all necessary services
	 */
	public function getRevisionStore( $dbDomain = false ) {
		Assert::parameterType( 'string|boolean', $dbDomain, '$dbDomain' );

		$store = new DARevisionStore(
			$this->dbLoadBalancerFactory->getMainLB( $dbDomain ),
			$this->blobStoreFactory->newSqlBlobStore( $dbDomain ),
			$this->cache, // Pass local cache instance; Leave cache sharing to RevisionStore.
			$this->commentStore,
			$this->nameTables->getContentModels( $dbDomain ),
			$this->nameTables->getSlotRoles( $dbDomain ),
			$this->slotRoleRegistry,
			$this->actorMigration,
			$this->actorStoreFactory->getActorStore( $dbDomain ),
			$this->contentHandlerFactory,
			$this->pageStoreFactory->getPageStore( $dbDomain ),
			$this->titleFactory,
			$this->hookContainer,
			$dbDomain
		);

		$store->setLogger( $this->logger );

		return $store;
	}
}
