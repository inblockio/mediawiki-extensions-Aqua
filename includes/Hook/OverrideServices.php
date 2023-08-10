<?php

namespace DataAccounting\Hook;

use ChangeTags;
use DataAccounting\Override\MultiSlotRevisionRenderer;
use DataAccounting\Override\Revision\DARevisionStoreFactory;
use DataAccounting\Override\Storage\DAPageEditStash;
use DataAccounting\Override\Storage\DAPageUpdaterFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\EditResultCache;
use MediaWiki\Storage\PageEditStash;
use MediaWiki\Storage\PageUpdaterFactory;
use ObjectCache;
use UnexpectedValueException;

class OverrideServices implements MediaWikiServicesHook {

	public function onMediaWikiServices( $services ) {
		// Used to add a hook on PageUpdater::doUpdate
		// This hook is used to allow same-edit setting of additional slots
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
					$services->getParsoidOutputAccess(),
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
					$services->getWikiPageFactory(),
					ChangeTags::getSoftwareTags()
				);
			}
		);

		// Used to redefine RevisionStore::newNullRevision
		// Otherwise, its called on file upload, and makes uncontrollable revision
		$services->redefineService(
			'RevisionStoreFactory',
			function( MediaWikiServices $services ) {
				$config = $services->getMainConfig();

				if ( $config->has( 'MultiContentRevisionSchemaMigrationStage' ) ) {
					if ( $config->get( 'MultiContentRevisionSchemaMigrationStage' ) !== SCHEMA_COMPAT_NEW ) {
						throw new UnexpectedValueException(
							'The MultiContentRevisionSchemaMigrationStage setting is no longer supported!'
						);
					}
				}

				return new DARevisionStoreFactory(
					$services->getDBLoadBalancerFactory(),
					$services->getBlobStoreFactory(),
					$services->getNameTableStoreFactory(),
					$services->getSlotRoleRegistry(),
					$services->getMainWANObjectCache(),
					$services->getCommentStore(),
					$services->getActorMigration(),
					$services->getActorStoreFactory(),
					LoggerFactory::getInstance( 'RevisionStore' ),
					$services->getContentHandlerFactory(),
					$services->getPageStoreFactory(),
					$services->getTitleFactory(),
					$services->getHookContainer()
				);
			}
		);

		// Used to change how slots are rendered
		// It changes rendering of the DataAccounting slots only
		$services->redefineService(
			'RevisionRenderer',
			function( MediaWikiServices $services ) {
				$renderer = new MultiSlotRevisionRenderer(
					$services->getDBLoadBalancer(),
					$services->getSlotRoleRegistry(),
					$services->getContentRenderer()
				);
				$renderer->setLogger( LoggerFactory::getInstance( 'SaveParse' ) );
				return $renderer;
			}
		);

		$services->redefineService(
			'PageEditStash',
			function( MediaWikiServices $services ) {
				return new DAPageEditStash(
					ObjectCache::getLocalClusterInstance(),
					$services->getDBLoadBalancer(),
					LoggerFactory::getInstance( 'StashEdit' ),
					$services->getStatsdDataFactory(),
					$services->getUserEditTracker(),
					$services->getUserFactory(),
					$services->getWikiPageFactory(),
					$services->getHookContainer(),
					defined( 'MEDIAWIKI_JOB_RUNNER' ) || $GLOBALS[ 'wgCommandLineMode' ]
						? PageEditStash::INITIATOR_JOB_OR_CLI
						: PageEditStash::INITIATOR_USER
				);
			}
		);
	}
}
