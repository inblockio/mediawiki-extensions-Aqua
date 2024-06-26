<?php

namespace DataAccounting\Content;

class WitnessContent extends \JsonContent {
	public const CONTENT_MODEL_WITNESS = 'witness';
	public const SLOT_ROLE_WITNESS = 'witness-slot';

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, static::CONTENT_MODEL_WITNESS );
	}

	public function isValid() {
		return $this->getText() === '' || parent::isValid();
	}
}
