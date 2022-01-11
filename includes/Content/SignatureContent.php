<?php

namespace DataAccounting\Content;

use Html;
use JsonContent;
use MediaWiki\MediaWikiServices;
use Message;
use ParserOptions;
use ParserOutput;
use Title;

class SignatureContent extends JsonContent implements DataAccountingContent {
	public const CONTENT_MODEL_SIGNATURE = 'signature';
	public const SLOT_ROLE_SIGNATURE = 'signature-slot';

	/** @var \MediaWiki\User\UserFactory  */
	private $userFactory;

	public function __construct( $text ) {
		parent::__construct( $text, static::CONTENT_MODEL_SIGNATURE );
		$this->userFactory = MediaWikiServices::getInstance()->getUserFactory();
	}

	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		if ( !$this->isValid() ) {
			return '';
		}
		$data = json_decode( $this->getText(), 1 );

		$output->setText( $this->getSignatureContent( $data ) );
	}

	public function isValid() {
		return $this->getText() === '' || parent::isValid();
	}

	private function getSignatureContent( array $data ) {
		$signatures = '';
		foreach ( $data as $signature ) {
			if ( !is_array( $signature ) ) {
				continue;
			}
			$syntaxForPage = $this->getSignatureLine( $signature );
			if ( !$syntaxForPage ) {
				$signatures .= 'Invalid signature';
			} else {
				$signatures .= $syntaxForPage;
			}

			$signatures .= '<br>';
		}
		return Html::rawElement( 'div', [], $signatures );
	}

	private function getSignatureLine( array $signature ) {
		if ( !isset( $signature['user'] ) || !isset( $signature['timestamp'] ) ) {
			return null;
		}
		$user = $this->userFactory->newFromName( $signature['user'] );
		if ( !$user ) {
			return null;
		}
		$linker = MediaWikiServices::getInstance()->getLinkRenderer();
		$line = $linker->makeLink( $user->getUserPage(), $user->getName() );
		$line .= "({$linker->makeLink( $user->getTalkPage(), 'talk' )}) ";

		$language = \RequestContext::getMain()->getLanguage();
		$viewingUser = \RequestContext::getMain()->getUser();
		$line .= $language->userTimeAndDate( $signature['timestamp'], $viewingUser );

		return Html::rawElement( 'span', [ 'class' => 'da-signature-line' ], $line );
	}

	/**
	 * @inheritDoc
	 */
	public function getItemCount(): int {
		return count( json_decode( $this->getText(), 1 ) );
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
		return Message::newFromKey( 'da-signature-slot-header' );
	}

	/**
	 * @inheritDoc
	 */
	public function shouldShow(): bool {
		return $this->mText !== '';
	}
}
