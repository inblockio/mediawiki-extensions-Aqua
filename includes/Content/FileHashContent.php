<?php

namespace DataAccounting\Content;

use DataAccounting\Verification\Hasher;
use File;
use Message;
use ParserOptions;
use ParserOutput;
use TextContent;
use Title;

class FileHashContent extends TextContent implements DataAccountingContent {
	public const CONTENT_MODEL_FILE_HASH = 'file-hash';
	public const SLOT_ROLE_FILE_HASH = 'file_hash';

	public function __construct( $text ) {
		parent::__construct( $text, static::CONTENT_MODEL_FILE_HASH );
	}

	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		if ( $this->mText === '' ) {
			$output->setText( Message::newFromKey( 'da-file-hash-no-hash' )->text() );
		} else {
			$output->setText(
				Message::newFromKey( 'da-file-hash-hash' )
					->params( trim( $this->getText() ) )
					->parseAsBlock()
			);
		}
	}

	/**
	 * @param File $file
	 * @return bool
	 */
	public function setHashFromFile( File $file ): bool {
		$path = $file->getLocalRefPath();
		if ( !$path || !file_exists( $path ) ) {
			return false;
		}

		$content = file_get_contents( $path );
		if ( !$content ) {
			return false;
		}

		$hasher = new Hasher();
		$this->mText = $hasher->getHashSum( $content );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getItemCount(): int {
		return 0;
	}

	/**
	 * @inheritDoc
	 */
	public function requiresAction(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getSlotHeader(): string {
		return 'File content hash';
	}

	/**
	 * @inheritDoc
	 */
	public function shouldShow(): bool {
		return $this->mText !== '';
	}
}
