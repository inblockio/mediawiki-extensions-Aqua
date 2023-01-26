<?php

namespace DataAccounting\API;

use Exception;

class SquashRevisionsHandler extends DeleteRevisionsHandler {

	/**
	 * @param array $revisionIds
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function executeAction( array $revisionIds ) {
		$this->revisionManipulator->squashRevisions( $revisionIds );
	}
}
