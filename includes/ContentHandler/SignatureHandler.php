<?php

namespace DataAccounting\ContentHandler;

use DataAccounting\Content\SignatureContent;
use JsonContentHandler;

class SignatureHandler extends JsonContentHandler {
	public function __construct() {
		parent::__construct( SignatureContent::CONTENT_MODEL_SIGNATURE );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return SignatureContent::class;
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
}
