<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace DataAccounting;

use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\WitnessingEngine;
use FormatJson;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Hook\ImportHandlePageXMLTagHook;
use MediaWiki\Hook\XmlDumpWriterOpenPageHook;
use MWException;
use OutputPage;
use Parser;
use PPFrame;
use RequestContext;
use Skin;
use stdClass;
use TitleFactory;
use WikiImporter;

class Hooks implements
	BeforePageDisplayHook,
	ParserFirstCallInitHook,
	OutputPageParserOutputHook,
	ImportHandlePageXMLTagHook,
	XmlDumpWriterOpenPageHook
{

	private PermissionManager $permissionManager;
	private TitleFactory $titleFactory;
	private VerificationEngine $verificationEngine;
	private WitnessingEngine $witnessingEngine;

	public function __construct(
		PermissionManager $permissionManager,
		TitleFactory $titleFactory,
		VerificationEngine $verificationEngine,
		WitnessingEngine $witnessingEngine
	) {
		$this->permissionManager = $permissionManager;
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
		$this->witnessingEngine = $witnessingEngine;
	}

	/**
	 * Method for extension.json callback on extension registry
	 */
	public static function onRegistration() {
		// The following line is added to override a legacy MW behavior.
		// We want this so that all the transcluded content are properly hashed and
		// controlled at all times. Therefor the parser cache can not be used
		$GLOBALS['wgParserCacheType'] = CACHE_NONE;
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
			$out->addModules( 'publishDomainSnapshot' );
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

	/**
	 * This hook is called at the end of XmlDumpWriter::openPage, to allow
	 * extra metadata to be added.
	 *
	 * @since 1.35
	 *
	 * @param XmlDumpWriter $obj
	 * @param string &$out Output string
	 * @param stdClass $row Database row for the page
	 * @param Title $title Title of the page
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onXmlDumpWriterOpenPage( $obj, &$out, $row, $title ) {
		// This method is for verified exporter.
		$out .= \Xml::element(
			'data_accounting_chain_height',
			[],
			$this->verificationEngine->getPageChainHeight( $title->getText() )
		);
	}

	public function onXmlDumpWriterWriteRevision( \XmlDumpWriter $dumpWriter, string &$output, stdClass $page, string $text, RevisionRecord $revision ): void {
		// This method is for verified exporter.
		$xmlBuilder = new RevisionXmlBuilder(
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			$this->witnessingEngine
		);

		$output .= $xmlBuilder->getPageMetadataByRevId( $revision->getId() );
	}

	/**
	 * This hook is called when parsing an XML tag in a page.
	 *
	 * @since 1.35
	 *
	 * @param WikiImporter $reader
	 * @param array &$pageInfo Array of information
	 * @return bool|void True or no return value to continue, or false to stop further
	 *   processing of the tag
	 */
	public function onImportHandlePageXMLTag( $reader, &$pageInfo ) {
		// This method is for verified importer.
		if ( $reader->getReader()->localName !== 'data_accounting_chain_height' ) {
			return true;
		}

		$own_chain_height = $this->verificationEngine->getPageChainHeight( $pageInfo['title'] );

		if ( $own_chain_height == 0 ) {
			return false;
		}

		$imported_chain_height = $reader->nodeContents();
		if ( $own_chain_height <= $imported_chain_height ) {
			// Move and rename own page
			// Rename the page that is about to be imported
			$now = date( 'Y-m-d-H-i-s', time() );
			$newTitle = $pageInfo['title'] . "_ChainHeight_{$own_chain_height}_$now";

			$ot = $this->titleFactory->newFromText( $pageInfo['title'] );
			$nt = $this->titleFactory->newFromText( $newTitle );
			$mp = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage( $ot, $nt );
			$reason = "Resolving naming collision because imported page has longer verified chain height.";
			$createRedirect = false;

			$mp->moveIfAllowed(
				RequestContext::getMain()->getUser(),
				$reason,
				$createRedirect
			);
		}

		// This prevents continuing down the else-if statements in WikiImporter, which would reach `$tag != '#text'`
		return false;
	}
}
