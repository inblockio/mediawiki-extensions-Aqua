<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\FileVerificationContent;
use JsonContentHandler;
use MediaWiki\Content\Transform\PreSaveTransformParams;

class FileVerificationHandler extends JsonContentHandler {
	public function __construct() {
		parent::__construct( FileVerificationContent::CONTENT_MODEL_FILE_VERIFICATION );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return FileVerificationContent::class;
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
