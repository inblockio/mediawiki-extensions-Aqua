<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\FileHashContent;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\Transform\PreSaveTransformParams;
use ParserOutput;
use TextContentHandler;

class FileHashHandler extends TextContentHandler {
	public function __construct() {
		parent::__construct( FileHashContent::CONTENT_MODEL_FILE_HASH );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return FileHashContent::class;
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
	 * @param ParserOutput $output
	 *
	 * @return void
	 */
	protected function fillParserOutput(
		Content $content, ContentParseParams $cpoParams, ParserOutput &$output
	) {
		$output->setText( trim( $content->getText() ) );
	}
}
