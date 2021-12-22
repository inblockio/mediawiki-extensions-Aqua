<?php

namespace DataAccounting\Content;

use File;
use JsonContent;

class FileVerificationContent extends JsonContent {
	public const CONTENT_MODEL_FILE_VERIFICATION = 'file-verification';
	public const SLOT_ROLE_FILE_VERIFICATION = 'file-verification-slot';

	public function __construct( $text, $modelId = self::CONTENT_MODEL_FILE_VERIFICATION ) {
		parent::__construct( $text, $modelId );
	}

	/**
	 * @param File $file
	 * @return FileVerificationContent|null
	 */
	public static function newFromFile( File $file ): ?FileVerificationContent {
		$path = $file->getLocalRefPath();
		if ( !$path || !file_exists( $path ) ) {
			return null;
		}

		$content = file_get_contents( $path );
		if ( !$content ) {
			return null;
		}
		return new static( json_encode( [
			'hash' => hash( "sha3-512", $content, false ),
		] ) );
	}

	public function isValid() {
		return $this->getText() === '' || parent::isValid();
	}
}
