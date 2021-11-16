<?php

declare( strict_types = 1 );

namespace DataAccounting;

interface PageVerificationRepo {

	// TODO: does this naming make sense? Which one is it: page or revision?
	public function getPageVerificationData( int $revisionId ): array;

}
