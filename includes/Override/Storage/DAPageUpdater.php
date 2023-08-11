<?php

namespace DataAccounting\Override\Storage;

use Content;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Storage\DerivedPageDataUpdater;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\PreparedUpdate;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
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
	/** @var WikiPage */
	private $wikiPage;
	/** @var bool */
	private $shouldEmit = true;
	/** @var string|null */
	private $rawMainRoleText = null;
	/** @var bool */
	private $alreadyFired = false;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		UserIdentity $author, WikiPage $wikiPage, DerivedPageDataUpdater $derivedDataUpdater,
		ILoadBalancer $loadBalancer, RevisionStore $revisionStore, SlotRoleRegistry $slotRoleRegistry,
		IContentHandlerFactory $contentHandlerFactory, HookContainer $hookContainer,
		UserEditTracker $userEditTracker, UserGroupManager $userGroupManager, TitleFormatter $titleFormatter,
		ServiceOptions $serviceOptions, array $softwareTags, LoggerInterface $logger
	) {
		parent::__construct(
			$author, $wikiPage, $derivedDataUpdater, $loadBalancer, $revisionStore,
			$slotRoleRegistry, $contentHandlerFactory, $hookContainer, $userEditTracker,
			$userGroupManager, $titleFormatter, $serviceOptions, $softwareTags, $logger
		);
		$this->hookContainer = $hookContainer;
		$this->wikiPage = $wikiPage;
	}

	/**
	 * @param string $role
	 * @param Content $content
	 *
	 * @return DAPageUpdater
	 */
	public function setContent( $role, Content $content ) {
		// Save raw content of the MAIN slot, so that transclusions
		// can be pulled on it, on `DASaveRevisionAddSlots` hook
		if ( $role === SlotRecord::MAIN ) {
			$this->rawMainRoleText = $content->getText();
		}
		return parent::setContent( $role, $content );
	}

	/**
	 * @param int $flags
	 *
	 * @return PreparedUpdate
	 */
	public function prepareUpdate( int $flags = 0 ) : PreparedUpdate {
		// CUSTOM PART START
		// We fire a hook to allow subscribers to add their own contents to slots
		if ( $this->shouldEmit && !$this->alreadyFired ) {
			$this->alreadyFired = true;
			// Add slots when preparing the content, otherwise the content slots will be locked
			// (won't be set again). Later, on MultiContentSave hook, we will the actual data to the slots
			$this->hookContainer->run( 'DASaveRevisionAddSlots', [ $this, $this->wikiPage, $this->rawMainRoleText ] );
		}
		// CUSTOM PART END
		return parent::prepareUpdate( $flags );
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
