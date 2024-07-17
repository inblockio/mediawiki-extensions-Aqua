<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\FileHashContent;
use DataAccounting\Content\WitnessContent;
use Html;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\Transform\PreSaveTransformParams;
use MediaWiki\MediaWikiServices;
use ParserOutput;
use TextContentHandler;
use Wikimedia\Rdbms\IDatabase;

class WitnessHandler extends TextContentHandler {
	private const LINK_TO_ETHERSCAN = 'https://sepolia.etherscan.io/tx/';

	/** @var IDatabase */
	private IDatabase $db;

	public function __construct() {
		parent::__construct( WitnessContent::CONTENT_MODEL_WITNESS );
		$this->db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
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
	 *
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
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	) {
		if ( !$content->isValid() ) {
			return;
		}
		$data = json_decode( $content->getText(), 1 );
		$domainSnapshotTitle = $data['domain_snapshot_title'];
		$timestamp = $data['timestamp'];

		$parserOutput->setText( $this->getWitnessContent( $domainSnapshotTitle, $timestamp ) );
	}

	/**
	 * @param string $domainSnapshotTitle
	 * @param string $timestamp
	 *
	 * @return string
	 */
	private function getWitnessContent( string $domainSnapshotTitle, string $timestamp ) {
		$row = $this->db->selectRow(
			'witness_events',
			[
				'witness_network',
				'witness_event_transaction_hash'
			],
			[ 'domain_snapshot_title' => $domainSnapshotTitle ],
			__METHOD__
		);

		if ( !$row ) {
			return Html::rawElement( 'div', [], 'Invalid witness' );
		}

		$witnessNetwork = $row->witness_network;
		$transactionHash = $row->witness_event_transaction_hash;
		if ( !$witnessNetwork || !$transactionHash ) {
			return Html::rawElement( 'div', [], 'Invalid witness' );
		}

		$language = \RequestContext::getMain()->getLanguage();
		$viewingUser = \RequestContext::getMain()->getUser();

		$lineContent = \Message::newFromKey( 'da-witness-network-label' )->text() . ': ' . ucfirst( $witnessNetwork );
		$lineContent .= Html::rawElement( 'br' );

		$linkToDomainSnapshot = \Title::newFromDBkey( $domainSnapshotTitle )->getFullURL();
		$lineContent .= Html::rawElement(
			'a',
			[ 'href' => $linkToDomainSnapshot ],
			\Message::newFromKey( 'da-witness-domain-snapshot-label' )->text()
		);
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
