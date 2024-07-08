<?php

namespace DataAccounting\Content;

use JsonContent;
use Message;

class WitnessContent extends JsonContent implements DataAccountingContent {
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

	/**
	 * There is always only one witness slot.
	 *
	 * @inheritDoc
	 */
	public function getItemCount(): int {
		return $this->isValid() ? 1 : 0;
	}

	/**
	 * @inheritDoc
	 */
	public function requiresAction(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getSlotHeader(): string {
		return Message::newFromKey( 'da-witness-slot-header' );
	}

	/**
	 * @inheritDoc
	 */
	public function shouldShow(): bool {
		return $this->mText !== '';
	}
}
