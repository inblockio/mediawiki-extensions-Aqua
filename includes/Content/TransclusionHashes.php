<?php

namespace DataAccounting\Content;

use DataAccounting\TransclusionManager;
use Html;
use JsonContent;
use MediaWiki\MediaWikiServices;
use Message;
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

	/**
	 * @param Title $resourceToUpdate
	 * @param string $hash
	 * @return bool
	 */
	public function updateHashForResource( Title $resourceToUpdate, $hash ): bool {
		if ( !$this->isValid() ) {
			return false;
		}
		$data = $this->getData()->getValue();
		foreach ( $data as $resource ) {
			if ( $resource->dbkey === $resourceToUpdate->getDBkey() && $resource->ns = $resourceToUpdate->getNamespace() ) {
				$resource->hash = $hash;
				$this->mText = json_encode( $data );
				return true;
			}
		}

		return false;
	}

	/**
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput $output
	 */
	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		if ( $title->getLatestRevID() !== $revId ) {
			$output->setText(
				Message::newFromKey("da-transclusion-hash-ui-visit-latest" )->parseAsBlock()
			);
			return;
		}
		if ( !$this->isValid() ) {
			$output->setText( '' );
			return;
		}
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
		$table = $this->drawTable( $states );
		$output->addModules( 'ext.dataAccounting.updateTransclusionHashes' );

		$outputText = $table;
		// TODO: Stupid dependency
		if ( \RequestContext::getMain()->getRequest()->getBool( 'debug' ) ) {
			$outputText .= $this->rootValueTable( $this->getData()->getValue() );
			$output->addModuleStyles( 'mediawiki.content.json' );
		}
		$output->setText( $outputText );
	}

	private function drawTable( array $states ): string {
		$table = Html::openElement( 'table', [
			'class' => 'wikitable',
			'style' => 'width: 100%',
			'id' => 'transclusionResourceTable'
		] );
		foreach ( $states as $state ) {
			$table .= $this->drawRow( $state );
		}
		$table .= Html::closeElement( 'table' );

		return $table;
	}

	private function drawRow( array $state ): string {
		$row = Html::openElement( 'tr' );

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$title = $state['titleObject'];
		$changeState = $state['state'];

		$row .= Html::rawElement( 'td', [], $linkRenderer->makeLink( $title ) );

		$row .= Html::rawElement(
			'td', [],
			Message::newFromKey( "da-transclusion-hash-ui-state-{$changeState}" )->text()
		);
		if ( $changeState !== TransclusionManager::STATE_UNCHANGED ) {
			$row .= Html::rawElement(
				'td', [],
				Html::element( 'a', [
					'href' => '#',
					'class' => 'da-included-resource-update',
					'data-resource-key' => $title->getPrefixedDbKey(),
				], Message::newFromKey( 'da-transclusion-hash-ui-update-version' )->text() )
			);
		}

		$row .= Html::closeElement( 'tr' );

		return $row;
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
