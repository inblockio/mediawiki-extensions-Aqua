<?php

namespace DataAccounting\Hook;

use MediaWiki\Storage\PageUpdater;

interface DASaveRevisionAddSlotsHook {

	/**
	 * @param PageUpdater $updater
	 * @return void
	 */
	public function onDASaveRevisionAddSlots( PageUpdater $updater );
}
