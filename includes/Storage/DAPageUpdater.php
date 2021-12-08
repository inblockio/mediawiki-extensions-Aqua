<?php

namespace DataAccounting\Storage;

use CommentStoreComment;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Storage\DerivedPageDataUpdater;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use TitleFormatter;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;

/**
 * Override of MediaWiki\Storage\PageUpdater
 *
 * Allows saving additional slots in the same edit as the main slot
 * @package DataAccounting\Storage
 */
class DAPageUpdater extends PageUpdater {
	/** @var HookContainer */
	private $hookContainer;
	/** @var bool */
	private $shouldEmit = true;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		UserIdentity $author, WikiPage $wikiPage, DerivedPageDataUpdater $derivedDataUpdater,
		ILoadBalancer $loadBalancer, RevisionStore $revisionStore, SlotRoleRegistry $slotRoleRegistry,
		IContentHandlerFactory $contentHandlerFactory, HookContainer $hookContainer,
		UserEditTracker $userEditTracker, UserGroupManager $userGroupManager, TitleFormatter $titleFormatter,
		ServiceOptions $serviceOptions, array $softwareTags
	) {
		parent::__construct(
			$author, $wikiPage, $derivedDataUpdater, $loadBalancer, $revisionStore,
			$slotRoleRegistry, $contentHandlerFactory, $hookContainer, $userEditTracker,
			$userGroupManager, $titleFormatter, $serviceOptions, $softwareTags
		);
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function saveRevision( CommentStoreComment $summary, int $flags = 0 )  {
		// CUSTOM PART START
		// We fire a hook to allow subscribers to add their own contents to slots
		if ( $this->shouldEmit ) {
			$this->hookContainer->run( 'DASaveRevisionAddSlots', [ $this ] );
		}
		// CUSTOM PART END
		return parent::saveRevision( $summary, $flags );
	}

	/**
	 * Turn off all subscribers to save event
	 */
	public function subscribersOff() {
		$this->shouldEmit = false;
	}

	/**
	 * Turn on all subscribers to save event
	 */
	public function subscribersOn() {
		$this->shouldEmit = true;
	}
}
