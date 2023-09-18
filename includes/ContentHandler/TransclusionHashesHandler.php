<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\TransclusionHashes;
use DataAccounting\TransclusionManager;
use DataAccounting\Verification\Entity\VerificationEntity;
use Html;
use JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\Transform\PreSaveTransformParams;
use MediaWiki\MediaWikiServices;
use Message;
use ParserOutput;

class TransclusionHashesHandler extends JsonContentHandler {
	public function __construct() {
		parent::__construct( TransclusionHashes::CONTENT_MODEL_TRANSCLUSION_HASHES );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return TransclusionHashes::class;
	}

	/**
	 * @return bool
	 */
	public function supportsCategories() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function supportsRedirects() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function supportsDirectApiEditing() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function supportsSections() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function supportsDirectEditing() {
		return false;
	}

	/**
	 * @param Content $content
	 * @param PreSaveTransformParams $pstParams
	 * @return Content
	 */
	public function preSaveTransform( Content $content, PreSaveTransformParams $pstParams ): Content {
		// This is critical, as it returns the same instance of the object on pre-transform
		// By default this would create a new instance, which screws up the reference between objects on save
		return $content;
	}

	/**
	 * @param TransclusionHashes $content
	 * @param ContentParseParams $cpoParams
	 * @param ParserOutput $parserOutput
	 *
	 * @return void
	 */
	protected function fillParserOutput(
		Content $content, ContentParseParams $cpoParams, ParserOutput &$parserOutput
	) {
		if ( $cpoParams->getPage()->getLatestRevID() !== $cpoParams->getRevId() ) {
			$parserOutput->setText(
				Message::newFromKey( "da-transclusion-hash-ui-visit-latest" )->parseAsBlock()
			);
			return;
		}
		if ( !$content->isValid() ) {
			$parserOutput->setText( '' );
			return;
		}
		/** @var TransclusionManager $transclusionManager */
		$transclusionManager = MediaWikiServices::getInstance()->getService(
			'DataAccountingTransclusionManager'
		);
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revision = $revisionStore->getRevisionById( $cpoParams->getRevId() );
		if ( $revision === null ) {
			return;
		}
		$states = $transclusionManager->getTransclusionState( $revision );
		$table = $this->drawTable( $states, $content );
		$parserOutput->addModules( [ 'ext.dataAccounting.updateTransclusionHashes' ] );

		$outputText = $table;
		// TODO: Stupid dependency
		if ( \RequestContext::getMain()->getRequest()->getBool( 'debug' ) ) {
			$outputText .= $content->rootValueTable( $content->getData()->getValue() );
			$parserOutput->addModuleStyles( [ 'mediawiki.content.json' ] );
		}
		$parserOutput->setText( $outputText );
	}

	private function drawTable( array $states, Content $content ): string {
		$table = Html::openElement( 'div', [
			'class' => 'container',
			'style' => 'width: 100%',
			'id' => 'transclusionResourceTable'
		] );
		foreach ( $states as $state ) {
			$table .= $this->drawRow( $state, $content );
		}
		$table .= Html::closeElement( 'div' );

		return $table;
	}

	private function drawRow( array $state, Content $content ): string {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$title = $state['titleObject'];
		$changeState = $state['state'];

		$item = Html::openElement( 'div', [ 'class' => 'card' ] );
		$item .= Html::openElement( 'div', [ 'class'  => 'card-body' ] );
		$item .= $title->getPrefixedText();

		$badgeClasses = [ 'badge' ];
		if (
			$changeState === TransclusionManager::STATE_NEW_VERSION ||
			$changeState === TransclusionManager::STATE_NO_RECORD
		) {
			$content->setPayAttention( true );
			$badgeClasses[] = 'badge-warning';
		}
		if ( $changeState === TransclusionManager::STATE_UNCHANGED ) {
			$badgeClasses[] = 'badge-success';
		}
		if ( $changeState === TransclusionManager::STATE_INVALID ) {
			$content->setPayAttention( true );
			$badgeClasses[] = 'badge-danger';
		}
		$item .= Html::openElement( 'span', [
			'class' => implode( ' ', $badgeClasses ),
			'style' => 'margin-left: 10px',
		] );
		$item .= Message::newFromKey( "da-transclusion-hash-ui-state-{$changeState}" )->text();
		$item .= Html::closeElement( 'span' );

		if ( $changeState === TransclusionManager::STATE_NEW_VERSION ) {
			$item .= Html::element( 'a', [
				'href' => '#',
				'class' => 'da-included-resource-update',
				'data-resource-key' => $title->getPrefixedDbKey(),
				'style' => 'margin-left: 10px;'
			], Message::newFromKey( 'da-transclusion-hash-ui-update-version' )->text() );
		}

		$item .= Html::openElement( 'div', [ 'style' => 'width: 100%; display: block' ] );
		if ( $changeState === TransclusionManager::STATE_NO_RECORD ) {
			$item .= Html::rawElement(
				'span', [ 'style' => 'margin-right: 20px' ],
				$linkRenderer->makeKnownLink(
					$title, Message::newFromKey( 'da-transclusion-hashes-link-create' )->text(),
					[ 'action' => 'edit' ]
				)
			);
		}
		if ( $state[VerificationEntity::VERIFICATION_HASH] !== null ) {
			$item .= Html::rawElement(
				'span', [ 'style' => 'margin-right: 20px' ],
				$linkRenderer->makeLink(
					$title, Message::newFromKey( 'da-transclusion-hashes-link-included' )->text()
				)
			);
		}
		if ( $changeState === TransclusionManager::STATE_NEW_VERSION ) {
			$item .= Html::rawElement(
				'span', [ 'style' => 'margin-right: 20px' ],
				$linkRenderer->makeLink(
					$title,
					Message::newFromKey( 'da-transclusion-hashes-link-latest' )->text(),
					[ 'version' => 'latest' ]
				)
			);
		}

		$item .= Html::closeElement( 'div' );
		$item .= Html::closeElement( 'div' );
		$item .= Html::closeElement( 'div' );

		return Html::rawElement( 'div', [ 'class' => 'da-translusion-hashes-item container' ], $item );
	}
}
