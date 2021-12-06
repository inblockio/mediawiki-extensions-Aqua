<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\TransclusionHashes;
use JsonContentHandler;
use MediaWiki\Content\Transform\PreSaveTransformParams;

class TransclusionHashesHandler extends JsonContentHandler {
	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId = TransclusionHashes::CONTENT_MODEL_TRANSCLUSION_HASHES ) {
		parent::__construct( $modelId );
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

	public function preSaveTransform( Content $content, PreSaveTransformParams $pstParams ): Content {
		erroR_log( "PR: " . spl_object_id( $content ) );
		return $content;
	}
}
