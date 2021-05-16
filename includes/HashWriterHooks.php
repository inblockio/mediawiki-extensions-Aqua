<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace MediaWiki\Extension\Example;

use MediaWiki\Revision\SlotRecord;
use DatabaseUpdater;

class HashWriterHooks implements
	\MediaWiki\Page\Hook\RevisionFromEditCompleteHook,
	\MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
{

	public function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
		$dbw = wfGetDB( DB_MASTER );
		$data = [
			'rev_id' => 2,
		];
		$dbw->insert('page_verification', $data, __METHOD__);
	}

	/**
	 * Register our database schema.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'data_accounting', dirname( __DIR__ ) . '/sql/data_accounting.sql' );
	}
}
