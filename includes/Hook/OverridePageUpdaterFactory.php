<?php

namespace DataAccounting\Hook;

use ChangeTags;
use DataAccounting\Storage\DAPageUpdaterFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\EditResultCache;
use MediaWiki\Storage\PageUpdaterFactory;

class OverridePageUpdaterFactory implements MediaWikiServicesHook {

	public function onMediaWikiServices( $services ) {
		$services->redefineService(
			'PageUpdaterFactory',
			function( MediaWikiServices $services ) {
				$editResultCache = new EditResultCache(
					$services->getMainObjectStash(),
					$services->getDBLoadBalancer(),
					new ServiceOptions(
						EditResultCache::CONSTRUCTOR_OPTIONS,
						$services->getMainConfig()
					)
				);

				return new DAPageUpdaterFactory(
					$services->getRevisionStore(),
					$services->getRevisionRenderer(),
					$services->getSlotRoleRegistry(),
					$services->getParserCache(),
					$services->getJobQueueGroup(),
					$services->getMessageCache(),
					$services->getContentLanguage(),
					$services->getDBLoadBalancerFactory(),
					$services->getContentHandlerFactory(),
					$services->getHookContainer(),
					$editResultCache,
					$services->getUserNameUtils(),
					LoggerFactory::getInstance( 'SavePage' ),
					new ServiceOptions(
						PageUpdaterFactory::CONSTRUCTOR_OPTIONS,
						$services->getMainConfig()
					),
					$services->getUserEditTracker(),
					$services->getUserGroupManager(),
					$services->getTitleFormatter(),
					$services->getContentTransformer(),
					$services->getPageEditStash(),
					$services->getTalkPageNotificationManager(),
					$services->getMainWANObjectCache(),
					$services->getPermissionManager(),
					ChangeTags::getSoftwareTags()
				);
			}
		);
	}
}
