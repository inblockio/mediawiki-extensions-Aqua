<?php

declare( strict_types = 1 );

namespace DataAccounting;

interface PageVerificationRepo {

	public function getRevisionVerificationData( int $revisionId ): array;

}
