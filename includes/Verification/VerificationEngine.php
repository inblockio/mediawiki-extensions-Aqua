<?php

namespace DataAccounting\Verification;

class VerificationEngine {
	private $verificationLookup;

	public function __construct( VerificationLookup $verificationLookup ) {
		$this->verificationLookup = $verificationLookup;
	}

	public function getLookup(): VerificationLookup {
		return $this->verificationLookup;
	}
}
