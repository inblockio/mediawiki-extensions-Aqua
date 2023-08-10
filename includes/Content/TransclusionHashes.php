<?php

namespace DataAccounting\Content;

use JsonContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use Message;
use ParserOptions;
use ParserOutput;
use stdClass;
use Title;

class TransclusionHashes extends JsonContent implements DataAccountingContent {
	public const CONTENT_MODEL_TRANSCLUSION_HASHES = 'transclusion-hashes';
	public const SLOT_ROLE_TRANSCLUSION_HASHES = 'transclusion-hashes';

	/** @var bool */
	private $payAttention = false;

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, static::CONTENT_MODEL_TRANSCLUSION_HASHES );
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return $this->getText() === '' || parent::isValid();
	}

	/**
	 * @param array $hashmap
	 */
	public function setHashmap( array $hashmap ) {
		if ( !empty( $hashmap ) ) {
			$this->mText = json_encode( $hashmap );
		} else {
			$this->mText = '';
		}
	}

	/**
	 * @param Title $resourceTitle
	 * @param array $newData
	 * @return bool
	 */
	public function updateResource( Title $resourceTitle, $newData = [] ) {
		if ( !$this->isValid() ) {
			return false;
		}
		// Cannot change these...
		unset( $newData['dbkey'] );
		unset( $newData['ns'] );

		$data = [];
		$parseStatus = $this->getData();
		if ( $parseStatus->isOK() ) {
			$data = $parseStatus->getValue();
		}
		foreach ( $data as $resource ) {
			if (
				$resource->dbkey === $resourceTitle->getDBkey() &&
				$resource->ns === $resourceTitle->getNamespace()
			) {
				foreach ( $newData as $key => $value ) {
					$resource->$key = $value;
				}
				$this->mText = json_encode( $data );
				return true;
			}
		}

		return false;
	}

	/**
	 * @param LinkTarget|PageReference|Title $title
	 * @return stdClass|false if not listed
	 */
	public function getTransclusionDetails( $title ) {
		foreach ( $this->getResourceHashes() as $hashEntity ) {
			if (
				$title->getNamespace() === $hashEntity->ns && $title->getDBkey() === $hashEntity->dbkey ) {
				return $hashEntity;
			}
		}

		return false;
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
		return (array)$this->getData()->getValue();
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
		return $this->payAttention;
	}

	/**
	 * @inheritDoc
	 */
	public function getSlotHeader(): string {
		return Message::newFromKey( 'da-transclusion-hashes-slot-header' );
	}

	/**
	 * @inheritDoc
	 */
	public function shouldShow(): bool {
		return !empty( $this->getData()->getValue() );
	}
}
