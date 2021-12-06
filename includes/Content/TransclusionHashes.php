<?php

namespace DataAccounting\Content;

use FormatJson;
use JsonContent;

class TransclusionHashes extends JsonContent {
	public const CONTENT_MODEL_TRANSCLUSION_HASHES = 'transclusion-hashes';
	public const SLOT_ROLE_TRANSCLUSION_HASHES = 'transclusion-hashes';

	public function __construct( $text, $modelId = self::CONTENT_MODEL_TRANSCLUSION_HASHES ) {
		parent::__construct( $text, $modelId );
	}

	public function isValid() {
		return $this->getText() === '' || parent::isValid();
	}

	/**
	 * @param array $hashmap
	 */
	public function setHashmap( array $hashmap ) {
		$this->mText = json_encode( $hashmap );
	}

	/**
	 * @return array
	 */
	public function getResourceHashes(): array {
		if ( !$this->isValid() ) {
			return [];
		}
		if ( !$this->getData()->isGood() ) {
			return [];
		}
		return (array) $this->getData()->getValue();
	}
}
