<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\FileHashContent;
use DataAccounting\Content\WitnessContent;
use Html;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\Transform\PreSaveTransformParams;
use ParserOutput;
use TextContentHandler;

class WitnessHandler extends TextContentHandler {
	private const LINK_TO_ETHERSCAN = 'https://sepolia.etherscan.io/tx/';

	public function __construct() {
		parent::__construct( WitnessContent::CONTENT_MODEL_WITNESS );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return WitnessContent::class;
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
		return $content;
	}

	/**
	 * @param FileHashContent $content
	 * @param ContentParseParams $cpoParams
	 * @param ParserOutput &$parserOutput
	 *
	 * @return void
	 */
	protected function fillParserOutput(
		Content $content, ContentParseParams $cpoParams, ParserOutput &$parserOutput
	) {
		if ( !$content->isValid() ) {
			return;
		}
		$data = json_decode( $content->getText(), 1 );

		$parserOutput->setText( $this->getWitnessContent( $data ) );
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	private function getWitnessContent( array $data ) {
		$witnessNetwork = $data['witness_network'];
		$transactionHash = $data['witness_event_transaction_hash'];
		$timestamp = $data['timestamp'];
		if ( !$witnessNetwork || !$transactionHash ) {
			return Html::rawElement( 'div', [], 'Invalid witness' );
		}

		$language = \RequestContext::getMain()->getLanguage();
		$viewingUser = \RequestContext::getMain()->getUser();

		$lineContent = \Message::newFromKey( 'da-witness-network-label' )->text() . ': ' . ucfirst( $witnessNetwork );
		$lineContent .= Html::rawElement( 'br' );
		$linkToEtherscan = self::LINK_TO_ETHERSCAN . $transactionHash;
		$lineContent .= Html::rawElement( 'a', [ 'href' => $linkToEtherscan ], $transactionHash );

		$lineContent .= Html::rawElement( 'span', [
			'class' => 'badge badge-light',
			'style' => 'margin-left: 10px',
		], $language->userTimeAndDate( $timestamp, $viewingUser ) );

		$line = Html::rawElement( 'span', [ 'class' => 'da-witness-line' ], $lineContent );

		return Html::rawElement( 'div', [], $line );
	}
}
