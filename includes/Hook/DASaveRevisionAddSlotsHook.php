<?php

namespace DataAccounting\Hook;

use MediaWiki\Storage\PageUpdater;
use WikiPage;

interface DASaveRevisionAddSlotsHook {

	/**
	 * @param PageUpdater $updater
	 * @param WikiPage $wikiPage
	 * @param string|null $rawText
	 * @return void
	 */
	public function onDASaveRevisionAddSlots( PageUpdater $updater, WikiPage $wikiPage, ?string $rawText );
}
