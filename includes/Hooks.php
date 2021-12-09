<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace DataAccounting;

use FormatJson;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SkinTemplateNavigationHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MovePage;
use MWException;
use OutputPage;
use Parser;
use PPFrame;
use RequestContext;
use Skin;
use SkinTemplate;
use stdClass;
use Title;

require_once 'ApiUtil.php';

class Hooks implements
	BeforePageDisplayHook,
	ParserFirstCallInitHook,
	SkinTemplateNavigationHook,
	OutputPageParserOutputHook
{

	private PermissionManager $permissionManager;

	public function __construct( PermissionManager $permissionManager ) {
		$this->permissionManager = $permissionManager;
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
		if ( $this->permissionManager->userCan( 'read', $out->getUser(), $out->getTitle() ) ) {
			// Load our module on all pages
			$out->addModules( 'ext.DataAccounting.signMessage' );
			$out->addModules( 'publishDomainManifest' );
		}
	}

	public function onOutputPageParserOutput( $out, $parserOutput ): void {
		global $wgServer;
		$apiVersion = ServerInfo::DA_API_VERSION;
		$out->addMeta( "data-accounting-mediawiki", $wgServer );
		$out->addMeta( "data-accounting-api-version", $apiVersion );
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
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 *
	 * @param SkinTemplate $skin
	 * @param array &$cactions
	 */
	public function onSkinTemplateNavigation( $skin, &$cactions ): void {
		$action = $skin->getRequest()->getText( 'action' );

		if ( $skin->getTitle()->getNamespace() !== NS_SPECIAL ) {
			if ( !$this->permissionManager->userCan( 'edit', $skin->getUser(), $skin->getTitle() ) ) {
				return;
			}
			$cactions['actions']['daact'] = [
				'class' => $action === 'daact' ? 'selected' : false,
				'text' => $skin->msg( 'contentaction-daact' )->text(),
				'href' => $skin->getTitle()->getLocalURL( 'action=daact' ),
			];
		}
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

	public static function onXmlDumpWriterOpenPage( \XmlDumpWriter $dumpWriter, string &$output, stdClass $page, \Title $title ): void {
		// This method is for verified exporter.
		$output .= \Xml::element(
			'data_accounting_chain_height',
			[],
			(string)getPageChainHeight( $title->getText() )
		);
	}

	public static function onXmlDumpWriterWriteRevision( \XmlDumpWriter $dumpWriter, string &$output, stdClass $page, string $text, RevisionRecord $revision ): void {
		// This method is for verified exporter.
		$xmlBuilder = new RevisionXmlBuilder(
			MediaWikiServices::getInstance()->getDBLoadBalancer()
		);

		$output .= $xmlBuilder->getPageMetadataByRevId( $revision->getId() );
	}

	public static function onImportHandlePageXMLTag( $importer, array &$pageInfo ): bool {
		// This method is for verified importer.
		if ( $importer->getReader()->localName !== 'data_accounting_chain_height' ) {
			return true;
		}

		$own_chain_height = getPageChainHeight( $pageInfo['title'] );

		if ( $own_chain_height == 0 ) {
			return false;
		}

		$imported_chain_height = $importer->nodeContents();
		if ( $own_chain_height <= $imported_chain_height ) {
			// Move and rename own page
			// Rename the page that is about to be imported
			$now = date( 'Y-m-d-H-i-s', time() );
			$newTitle = $pageInfo['title'] . "_ChainHeight_{$own_chain_height}_$now";

			$ot = Title::newFromText( $pageInfo['title'] );
			$nt = Title::newFromText( $newTitle );
			$mp = new MovePage( $ot, $nt );

			$mp->moveIfAllowed(
				RequestContext::getMain()->getUser(),
				"Resolving naming collision because imported page has longer verified chain height.",
				false
			);
		}

		// This prevents continuing down the else-if statements in WikiImporter, which would reach `$tag != '#text'`
		return false;
	}
}
