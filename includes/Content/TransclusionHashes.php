<?php

namespace DataAccounting\Content;

use DataAccounting\TransclusionManager;
use FormatJson;
use JsonContent;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParserOutput;
use Title;

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

	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		/** @var TransclusionManager $transclusionManager */
		$transclusionManager = MediaWikiServices::getInstance()->getService(
			'DataAccountingTransclusionManager'
		);
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revision = $revisionStore->getRevisionById( $revId );
		if ( $revision === null ){
			return;
		}
		$states = $transclusionManager->getTransclusionState( $revision );

		$text = '';
		foreach ( $states as $page => $state ) {
			$text .= "* {$page} => {$state['state']}\n\n";
			$text .= " {$state['hash']} \n";
		}

		$output->setText( ( new \RawMessage( $text ) )->parseAsBlock() );
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
