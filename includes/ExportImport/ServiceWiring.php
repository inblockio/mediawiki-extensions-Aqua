<?php

use MediaWiki\MediaWikiServices;

use DataAccounting\VerifiedWikiImporterFactory;

// This file is needed for verified importer.
// If not needed anymore, delete ServiceWiringFiles in extension.json.

return [
	'VerifiedWikiImporterFactory' => static function ( MediaWikiServices $services ): VerifiedWikiImporterFactory {
		return new VerifiedWikiImporterFactory(
			$services->getMainConfig(),
			$services->getHookContainer(),
			$services->getContentLanguage(),
			$services->getNamespaceInfo(),
			$services->getTitleFactory(),
			$services->getWikiPageFactory(),
			$services->getWikiRevisionUploadImporter(),
			$services->getPermissionManager(),
			$services->getContentHandlerFactory(),
			$services->getSlotRoleRegistry()
		);
	}
];
