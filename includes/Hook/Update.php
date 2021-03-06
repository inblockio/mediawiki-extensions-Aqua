<?php

namespace DataAccounting\Hook;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class Update implements LoadExtensionSchemaUpdatesHook {
	/**
	 * Register our database schema.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = dirname( dirname( __DIR__ ) );

		$updater->addExtensionTable(
			'revision_verification', "$base/sql/data_accounting.sql"
		);
		$updater->addExtensionTable(
			'witness_events', "$base/sql/data_accounting.sql"
		);
		$updater->addExtensionTable(
			'witness_page', "$base/sql/data_accounting.sql"
		);
		$updater->addExtensionTable(
			'witness_merkle_tree', "$base/sql/data_accounting.sql"
		);
		$updater->addExtensionTable(
			'da_settings', "$base/sql/data_accounting.sql"
		);

		// TODO: Register patches
	}
}
