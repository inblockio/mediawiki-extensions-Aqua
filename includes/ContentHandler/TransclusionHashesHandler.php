<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\TransclusionHashes;
use JsonContentHandler;
use MediaWiki\Content\Transform\PreSaveTransformParams;

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
}
