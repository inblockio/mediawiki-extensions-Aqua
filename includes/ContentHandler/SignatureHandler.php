<?php

namespace DataAccounting\ContentHandler;

use Content;
use DataAccounting\Content\SignatureContent;
use Html;
use JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use ParserOutput;

class SignatureHandler extends JsonContentHandler {

	/**
	 * @var UserFactory
	 */
	private $userFactory;

	public function __construct() {
		parent::__construct( SignatureContent::CONTENT_MODEL_SIGNATURE );
		$this->userFactory = MediaWikiServices::getInstance()->getUserFactory();
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

	/**
	 * @param SignatureContent $content
	 * @param ContentParseParams $cpoParams
	 * @param ParserOutput $parserOutput
	 *
	 * @return void
	 */
	protected function fillParserOutput(
		Content $content, ContentParseParams $cpoParams, ParserOutput &$parserOutput
	) {
		if ( !$content->isValid() ) {
			return;
		}
		$data = json_decode( $content->getText(), 1 );

		$parserOutput->setText( $this->getSignatureContent( $data ) );
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
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

	/**
	 * @param array $signature
	 *
	 * @return string|null
	 */
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

		$language = \RequestContext::getMain()->getLanguage();
		$viewingUser = \RequestContext::getMain()->getUser();
		$line .= Html::rawElement( 'span', [
			'class' => 'badge badge-light',
			'style' => 'margin-left: 10px',
		], $language->userTimeAndDate( $signature['timestamp'], $viewingUser ) );

		return Html::rawElement( 'span', [ 'class' => 'da-signature-line' ], $line );
	}
}
