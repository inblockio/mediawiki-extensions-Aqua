<?php

namespace DataAccounting\Content;

use JsonContent;
use Message;

class SignatureContent extends JsonContent implements DataAccountingContent {
	public const CONTENT_MODEL_SIGNATURE = 'signature';
	public const SLOT_ROLE_SIGNATURE = 'signature-slot';

	public function __construct( $text ) {
		parent::__construct( $text, static::CONTENT_MODEL_SIGNATURE );
	}

	public function isValid() {
		return $this->getText() === '' || parent::isValid();
	}

	/**
	 * @inheritDoc
	 */
	public function getItemCount(): int {
		return count( json_decode( $this->getText(), 1 ) );
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
		return Message::newFromKey( 'da-signature-slot-header' );
	}

	/**
	 * @inheritDoc
	 */
	public function shouldShow(): bool {
		return $this->mText !== '';
	}
}
