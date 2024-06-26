<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\FileHashContent;
use DataAccounting\Content\WitnessContent;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\Transform\PreSaveTransformParams;
use ParserOutput;
use TextContentHandler;

class WitnessHandler extends TextContentHandler {
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
	 *
	 * @return void
	 */
	protected function fillParserOutput(
		Content $content, ContentParseParams $cpoParams, ParserOutput &$output
	) {
		$output->setText( '' );
	}
}
