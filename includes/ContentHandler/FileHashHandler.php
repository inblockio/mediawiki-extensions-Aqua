<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\FileHashContent;
use MediaWiki\Content\Transform\PreSaveTransformParams;
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
}
