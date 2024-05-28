<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace DataAccounting;

use FormatJson;
use Html;
use IContextSource;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use Message;
use MWException;
use OutputPage;
use Parser;
use PPFrame;
use RequestContext;
use Skin;

class Hooks implements
	BeforePageDisplayHook,
	ParserFirstCallInitHook
{

	private PermissionManager $permissionManager;

	public function __construct( PermissionManager $permissionManager ) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * Method for extension.json callback on extension registry
	 */
	public static function onRegistration() {
		// The following line is added to override a legacy MW behavior.
		// We want this so that all the transcluded content are properly hashed and
		// controlled at all times. Therefor the parser cache can not be used
		$GLOBALS['wgParserCacheType'] = CACHE_NONE;

		$GLOBALS['wgTweekiSkinSpecialElements']['NEWPAGE'] = static::class . '::createNewPageButton';
		$GLOBALS['wgTweekiSkinSpecialElements']['INBOX'] = static::class . '::createInboxButton';
	}

	/**
	 * Generate HTML for the "new page" button
	 * @param Skin $skin
	 * @param IContextSource $context
	 */
	public static function createNewPageButton( $skin, $context ) {
		$pm = MediaWikiServices::getInstance()->getPermissionManager();
		$user = RequestContext::getMain()->getUser();
		$canCreate = $pm->userHasRight( $user, 'createpage' );
		$canUpload = $pm->userHasRight( $user, 'upload' );

		if ( !$canCreate && !$canUpload ) {
			return;
		}
		$button = Html::openElement( 'div', [ 'class' => 'dropdown', 'style' => 'margin: 0 10px 0 10px' ] );
		$button .= Html::rawElement( 'button', [
			'class' => 'btn btn-link dropdown-toggle ',
			'type' => 'button',
			'style' => 'color: white',
			'id' => 'aqua-new-button',
			'data-toggle' => 'dropdown',
			'aria-haspopup' => 'true',
			'aria-expanded' => 'false'
		], Html::element( 'i', [ 'class' => 'fas fa-plus-circle' ] ) );

		$button .= Html::openElement(
			'div', [ 'class' => 'dropdown-menu', 'aria-labelledby' => 'aqua-new-button' ]
		);

		if ( $canCreate ) {
			$button .= Html::element(
				'a', [ 'class' => 'dropdown-item', 'id' => 'aqua-new-page', 'href' => '#' ],
				Message::newFromKey( 'da-ui-new-page-create-label' )->text()
			);
		}

		if ( $canUpload ) {
			$specialUpload = MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'Upload' );
			$button .= Html::element(
				'a', [
					'class' => 'dropdown-item',
					'id' => 'aqua-new-file',
					'href' => $specialUpload->getPageTitle()->getLocalURL()
				], Message::newFromKey( 'da-ui-new-page-upload-label' )->text()
			);
		}

		$button .= Html::closeElement( 'div' );
		$button .= Html::closeElement( 'div' );

		echo $button;
	}

	public static function createInboxButton( $skin, $context ) {
		$pm = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$pm->userHasAllRights( RequestContext::getMain()->getUser(), 'createpage', 'edit' ) ) {
			return;
		}
		$specialInbox = MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'Inbox' );
		$pendingCount = $specialInbox->getInboxCount();
		$badge = '';
		if ( $pendingCount > 0 ) {
			$badge = Html::element( 'span', [
				'style' => 'position: absolute;background: white;border-radius: 100px;color: black;width: 12px;height: 12px;font-size: 8px;'
			], $pendingCount );
		}
		echo Html::rawElement( 'a', [
			'class' => 'btn btn-link ',
			'style' => 'color: white',
			'id' => 'aqua-inbox-button',
			'href' => $specialInbox->getPageTitle()->getLocalURL()
		], Html::element( 'i', [ 'class' => 'fas fa-inbox' ] ) . $badge );
	}

	/**
	 * Customisations to OutputPage right before page display.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$wgServer = $GLOBALS['wgServer'];
		$apiVersion = ServerInfo::DA_API_VERSION;
		$out->addMeta( "data-accounting-mediawiki", $wgServer );
		$out->addMeta( "data-accounting-api-version", $apiVersion );

		if ( $this->permissionManager->userCan( 'read', $out->getUser(), $out->getTitle() ) ) {
			// Load our module on all pages
			$out->addModules( 'ext.DataAccounting.signMessage' );
			$out->addModules( 'publishDomainSnapshot' );
		}
		if ( $this->permissionManager->userHasRight( $out->getUser(), 'createpage' ) ) {
			$out->addModules( 'ext.DataAccounting.createPage' );
		}
	}

	/**
	 * Register parser hooks.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @see https://www.mediawiki.org/wiki/Manual:Parser_functions
	 *
	 * @param Parser $parser
	 *
	 * @throws MWException
	 */
	public function onParserFirstCallInit( $parser ) {
		// Add the following to a wiki page to see how it works:
		// <dump>test</dump>
		// <dump foo="bar" baz="quux">test content</dump>
		$parser->setHook( 'dump', [ self::class, 'parserTagDump' ] );

		// Add the following to a wiki page to see how it works:
		// {{#echo: hello }}
		$parser->setFunctionHook( 'echo', [ self::class, 'parserFunctionEcho' ] );

		// Add the following to a wiki page to see how it works:
		// {{#showme: hello | hi | there }}
		$parser->setFunctionHook( 'showme', [ self::class, 'parserFunctionShowme' ] );
	}

	/**
	 * Parser hook handler for <dump>
	 *
	 * @param string $data The content of the tag.
	 * @param array $attribs The attributes of the tag.
	 * @param Parser $parser Parser instance available to render
	 *  wikitext into html, or parser methods.
	 * @param PPFrame $frame Can be used to see what template
	 *  arguments ({{{1}}}) this hook was used with.
	 *
	 * @return string HTML to insert in the page.
	 */
	public static function parserTagDump( $data, $attribs, $parser, $frame ) {
		$dump = [
			'content' => $data,
			'atributes' => (object)$attribs,
		];
		// Very important to escape user data with htmlspecialchars() to prevent
		// an XSS security vulnerability.
		$html = '<pre>Dump Tag: '
			. htmlspecialchars( FormatJson::encode( $dump, /*prettyPrint=*/ true ) )
			. '</pre>';

		return $html;
	}

	/**
	 * Parser function handler for {{#echo: .. }}
	 *
	 * @param Parser $parser
	 * @param string $value
	 *
	 * @return string HTML to insert in the page.
	 */
	public static function parserFunctionEcho( Parser $parser, $value ) {
		return '<strong>Echo says: ' . htmlspecialchars( $value ) . '</strong>';
	}

	/**
	 * Parser function handler for {{#showme: .. | .. }}
	 *
	 * @param Parser $parser
	 * @param string $value
	 * @param string ...$args
	 *
	 * @return string HTML to insert in the page.
	 */
	public static function parserFunctionShowme( Parser $parser, string $value, ...$args ) {
		$showme = [
			'value' => $value,
			'arguments' => $args,
		];

		return '<pre>Showme Function: '
			. htmlspecialchars( FormatJson::encode( $showme, /*prettyPrint=*/ true ) )
			. '</pre>';
	}
}
