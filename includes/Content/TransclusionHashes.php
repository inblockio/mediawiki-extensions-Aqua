<?php

namespace DataAccounting\Content;

use DataAccounting\HashLookup;
use DataAccounting\TransclusionManager;
use Html;
use JsonContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use Message;
use ParserOptions;
use ParserOutput;
use stdClass;
use Title;

class TransclusionHashes extends JsonContent {
	public const CONTENT_MODEL_TRANSCLUSION_HASHES = 'transclusion-hashes';
	public const SLOT_ROLE_TRANSCLUSION_HASHES = 'transclusion-hashes';

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
		$this->mText = json_encode( $hashmap );
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

		$data = $this->getData()->getValue();
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
				Message::newFromKey( "da-transclusion-hash-ui-visit-latest" )->parseAsBlock()
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
		if ( $revision === null ) {
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
		if ( $changeState === TransclusionManager::STATE_NEW_VERSION ) {
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

	public function serialize( $format = null ) {
		// We dont want to / need to expose dbkey and ns to API
		$toSerialize = array_map( static function( $hashEntity ) {
			unset( $hashEntity->dbkey );
			unset( $hashEntity->ns );
			return $hashEntity;
		}, $this->getResourceHashes() );

		return json_encode( $toSerialize );
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
}
