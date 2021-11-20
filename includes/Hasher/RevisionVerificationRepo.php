<?php

declare( strict_types = 1 );

namespace DataAccounting\Hasher;

interface RevisionVerificationRepo {

	public function getRevisionVerificationData( int $revisionId ): array;

}
