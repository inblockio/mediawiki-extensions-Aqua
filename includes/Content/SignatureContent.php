<?php

namespace DataAccounting\Content;

use Html;
use JsonContent;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParserOutput;
use Title;

class SignatureContent extends JsonContent {
	public const CONTENT_MODEL_SIGNATURE = 'signature';
	public const SLOT_ROLE_SIGNATURE = 'signature-slot';
	/** @var \MediaWiki\User\UserFactory  */
	private $userFactory;

	public function __construct( $text, $modelId = self::CONTENT_MODEL_SIGNATURE ) {
		parent::__construct( $text, $modelId );
		$this->userFactory = MediaWikiServices::getInstance()->getUserFactory();
	}

	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		if ( !$this->isValid() ) {
			return '';
		}
		$data = json_decode( $this->getText(), 1 );

		$content = Html::rawElement( 'div', [
			'class' => "mw-collapsible mw-collapsed"
		], Html::rawElement( 'div', [
			'style' => 'font-weight:bold;line-height:1.6;',
		], "Data Accounting Signatures" ) . $this->getSignatureContent( $data ) );

		$output->setText( $content );
	}

	public function isValid() {
		return $this->getText() === '' || parent::isValid();
	}

	private function getSignatureContent( array $data ) {
		$signatures = '';
		foreach ( $data as $signature ) {
			if ( !is_array( !$signatures ) ) {
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
		return Html::rawElement( 'div', [
			'class' => 'mw-collapsible-content'
		], $signatures );
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

		return $line;
	}
}
