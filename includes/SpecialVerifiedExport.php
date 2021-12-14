<?php

namespace DataAccounting;

use Config;
use HTMLForm;
use HTMLTextAreaField;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use SpecialPage;
use Title;
use WikiExporter;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * See https://github.com/inblockio/DataAccounting/pull/147.
 * DataAccounting modifications over MediaWiki Special:Export:
 * - Remove the option to export only the latest revision.
 * - Include templates by default.
 * - Put manual page entry field first.
 * - Disable conditional display of options for now.
 */
class SpecialVerifiedExport extends SpecialPage {
	protected bool $doExport;
	protected int $pageLinkDepth;
	protected bool $templates;

	private ILoadBalancer $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer = null ) {
		parent::__construct( 'VerifiedExport' );
		$this->loadBalancer = $loadBalancer ?? MediaWikiServices::getInstance()->getDBLoadBalancer();
	}

	/**
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$config = $this->getConfig();

		$this->doExport = false;
		$request = $this->getRequest();
		$this->templates = $request->getCheck( 'templates' );
		$this->pageLinkDepth = $this->validateLinkDepth(
			$request->getInt( 'pagelink-depth', -1 )
		);
		$nsindex = '';
		$exportall = false;

		if ( $request->getCheck( 'addcat' ) ) {
			$page = $request->getText( 'pages' );
			$catname = $request->getText( 'catname' );

			if ( $catname !== '' && $catname !== null && $catname !== false ) {
				$t = Title::makeTitleSafe( NS_MAIN, $catname );
				if ( $t ) {
					/**
					 * @todo FIXME: This can lead to hitting memory limit for very large
					 * categories. Ideally we would do the lookup synchronously
					 * during the export in a single query.
					 */
					$catpages = $this->getPagesFromCategory( $t );
					if ( $catpages ) {
						if ( $page !== '' ) {
							$page .= "\n";
						}
						$page .= implode( "\n", $catpages );
					}
				}
			}
		} elseif ( $request->getCheck( 'addns' ) && $config->get( 'ExportFromNamespaces' ) ) {
			$page = $request->getText( 'pages' );
			$nsindex = $request->getText( 'nsindex', '' );

			if ( strval( $nsindex ) !== '' ) {
				/**
				 * Same implementation as above, so same @todo
				 */
				$nspages = $this->getPagesFromNamespace( $nsindex );
				if ( $nspages ) {
					$page .= "\n" . implode( "\n", $nspages );
				}
			}
		} elseif ( $request->getCheck( 'exportall' ) && $config->get( 'ExportAllowAll' ) ) {
			$this->doExport = true;
			$exportall = true;

			/* Although $page and $history are not used later on, we
			nevertheless set them to avoid that PHP notices about using
			undefined variables foul up our XML output (see call to
			doExport(...) further down) */
			$page = '';
			$history = '';
		} elseif ( $request->wasPosted() && $par == '' ) {
			// Log to see if certain parameters are actually used.
			// If not, we could deprecate them and do some cleanup, here and in WikiExporter.
			LoggerFactory::getInstance( 'export' )->debug(
				'Special:Export POST, dir: [{dir}], offset: [{offset}], limit: [{limit}]', [
				'dir' => $request->getRawVal( 'dir' ),
				'offset' => $request->getRawVal( 'offset' ),
				'limit' => $request->getRawVal( 'limit' ),
			] );

			$page = $request->getText( 'pages' );
			$rawOffset = $request->getVal( 'offset' );

			if ( $rawOffset ) {
				$offset = wfTimestamp( TS_MW, $rawOffset );
			} else {
				$offset = null;
			}

			$maxHistory = $config->get( 'ExportMaxHistory' );
			$limit = $request->getInt( 'limit' );
			$dir = $request->getVal( 'dir' );
			$history = [
				'dir' => 'asc',
				'offset' => false,
				'limit' => $maxHistory,
			];
			$historyCheck = $request->getCheck( 'history' );

			if ( !$historyCheck ) {
				if ( $limit > 0 && ( $maxHistory == 0 || $limit < $maxHistory ) ) {
					$history['limit'] = $limit;
				}

				if ( $offset !== null ) {
					$history['offset'] = $offset;
				}

				if ( strtolower( $dir ) == 'desc' ) {
					$history['dir'] = 'desc';
				}
			}

			if ( $page != '' ) {
				$this->doExport = true;
			}
		} else {
			// Default to current-only for GET requests.
			$page = $request->getText( 'pages', $par );
			$historyCheck = $request->getCheck( 'history' );

			if ( $historyCheck ) {
				$history = WikiExporter::FULL;
			} else {
				$history = WikiExporter::CURRENT;
			}

			if ( $page != '' ) {
				$this->doExport = true;
			}
		}

		if ( !$config->get( 'ExportAllowHistory' ) ) {
			// Override
			$history = WikiExporter::CURRENT;
		}

		$list_authors = $request->getCheck( 'listauthors' );
		if ( !$config->get( 'ExportAllowListContributors' ) ) {
			$list_authors = false;
		}

		if ( $this->doExport ) {
			$this->getOutput()->disable();

			// Cancel output buffering and gzipping if set
			// This should provide safer streaming for pages with history
			wfResetOutputBuffers();
			$request->response()->header( 'Content-type: application/xml; charset=utf-8' );
			$request->response()->header( 'X-Robots-Tag: noindex,nofollow' );

			if ( $request->getCheck( 'wpDownload' ) ) {
				// Provide a sane filename suggestion
				$filename = urlencode( $config->get( 'Sitename' ) . '-' . wfTimestampNow() . '.xml' );
				$request->response()->header( "Content-disposition: attachment;filename={$filename}" );
			}

			$this->doExport( $page, $history, $list_authors, $exportall );

			return;
		}

		$out = $this->getOutput();
		$out->addWikiMsg( 'exporttext' );

		if ( $page == '' ) {
			$categoryName = $request->getText( 'catname' );
		} else {
			$categoryName = '';
		}

		$formDescriptor = [];

		$formDescriptor += [
			'textarea' => [
				'class' => HTMLTextAreaField::class,
				'name' => 'pages',
				'label-message' => 'export-manual',
				'nodata' => true,
				'rows' => 10,
				'default' => $page,
				'hide-if' => [ '===', 'exportall', '1' ],
			],
		];

		$formDescriptor += [
			'catname' => [
				'type' => 'textwithbutton',
				'name' => 'catname',
				'horizontal-label' => true,
				'label-message' => 'export-addcattext',
				'default' => $categoryName,
				'size' => 40,
				'buttontype' => 'submit',
				'buttonname' => 'addcat',
				'buttondefault' => $this->msg( 'export-addcat' )->text(),
				'hide-if' => [ '===', 'exportall', '1' ],
			],
		];

//		if ( $config->get( 'ExportFromNamespaces' ) ) {
			$formDescriptor += [
				'nsindex' => [
					'type' => 'namespaceselectwithbutton',
					'default' => $nsindex,
					'label-message' => 'export-addnstext',
					'horizontal-label' => true,
					'name' => 'nsindex',
					'id' => 'namespace',
					'cssclass' => 'namespaceselector',
					'buttontype' => 'submit',
					'buttonname' => 'addns',
					'buttondefault' => $this->msg( 'export-addns' )->text(),
					'hide-if' => [ '===', 'exportall', '1' ],
				],
			];
//		}

//		if ( $config->get( 'ExportAllowAll' ) ) {
			$formDescriptor += [
				'exportall' => [
					'type' => 'check',
					'label-message' => 'exportall',
					'name' => 'exportall',
					'id' => 'exportall',
					'default' => $request->wasPosted() ? $request->getCheck( 'exportall' ) : false,
				],
			];
//		}

		$formDescriptor += [
			'templates' => [
				'type' => 'check',
				'label-message' => 'export-templates',
				'name' => 'templates',
				'id' => 'wpExportTemplates',
				'default' => $request->wasPosted() ? $request->getCheck( 'templates' ) : true,
			],
		];

//		if ( $config->get( 'ExportMaxLinkDepth' ) || $this->userCanOverrideExportDepth() ) {
			$formDescriptor += [
				'pagelink-depth' => [
					'type' => 'text',
					'name' => 'pagelink-depth',
					'id' => 'pagelink-depth',
					'label-message' => 'export-pagelinks',
					'default' => '0',
					'size' => 20,
				],
			];
//		}

		$formDescriptor += [
			'wpDownload' => [
				'type' => 'check',
				'name' => 'wpDownload',
				'id' => 'wpDownload',
				'default' => $request->wasPosted() ? $request->getCheck( 'wpDownload' ) : true,
				'label-message' => 'export-download',
			],
		];

//		if ( $config->get( 'ExportAllowListContributors' ) ) {
			$formDescriptor += [
				'listauthors' => [
					'type' => 'check',
					'label-message' => 'exportlistauthors',
					'default' => $request->wasPosted() ? $request->getCheck( 'listauthors' ) : false,
					'name' => 'listauthors',
					'id' => 'listauthors',
				],
			];
//		}

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitTextMsg( 'export-submit' );
		$htmlForm->prepareForm()->displayForm( false );
		$this->addHelpLink( 'Help:Export' );
	}

	/**
	 * @return bool
	 */
	protected function userCanOverrideExportDepth(): bool {
		return $this->getAuthority()->isAllowed( 'override-export-depth' );
	}

	/**
	 * Do the actual page exporting
	 *
	 * @param string $page User input on what page(s) to export
	 * @param array $history One of the WikiExporter history export constants
	 * @param bool $list_authors Whether to add distinct author list (when
	 *   not returning full history)
	 * @param bool $exportall Whether to export everything
	 */
	protected function doExport(
		string $page,
		array $history,
		bool $list_authors,
		bool $exportall
	) {
		$pages = [];
		// If we are grabbing everything, enable full history and ignore the rest
		if ( $exportall ) {
			$history = WikiExporter::FULL;
		} else {
			$pageSet = []; // Inverted index of all pages to look up

			// Split up and normalize input
			foreach ( explode( "\n", $page ) as $pageName ) {
				$pageName = trim( $pageName );
				$title = Title::newFromText( $pageName );
				if ( $title && !$title->isExternal() && $title->getText() !== '' ) {
					// Only record each page once!
					$pageSet[$title->getPrefixedText()] = true;
				}
			}

			// Set of original pages to pass on to further manipulation...
			$inputPages = array_keys( $pageSet );

			// Look up any linked pages if asked...
			if ( $this->templates ) {
				$pageSet = $this->getTemplates( $inputPages, $pageSet );
			}
			$linkDepth = $this->pageLinkDepth;
			if ( $linkDepth ) {
				$pageSet = $this->getPageLinks( $inputPages, $pageSet, $linkDepth );
			}

			$pages = array_keys( $pageSet );

			// Normalize titles to the same format and remove dupes, see T19374
			foreach ( $pages as $k => $v ) {
				$pages[$k] = str_replace( ' ', '_', $v );
			}

			$pages = array_unique( $pages );
		}

		/* Ok, let's get to it... */
		$db = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );

		$exporter = new WikiExporter( $db, $history );
		$exporter->list_authors = $list_authors;
		$exporter->openStream();

		if ( $exportall ) {
			$exporter->allPages();
		} else {
			foreach ( $pages as $page ) {
				# T10824: Only export pages the user can read
				$title = Title::newFromText( $page );
				if ( $title === null ) {
					// @todo Perhaps output an <error> tag or something.
					continue;
				}

				if ( !$this->getAuthority()->authorizeRead( 'read', $title ) ) {
					// @todo Perhaps output an <error> tag or something.
					continue;
				}

				$exporter->pageByTitle( $title );
			}
		}

		$exporter->closeStream();
	}

	/**
	 * @param Title $title
	 * @return string[]
	 */
	protected function getPagesFromCategory( Title $title ): array {
		$maxPages = $this->getConfig()->get( 'ExportPagelistLimit' );

		$name = $title->getDBkey();

		$dbr = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );
		$res = $dbr->select(
			[ 'page', 'categorylinks' ],
			[ 'page_namespace', 'page_title' ],
			[ 'cl_from=page_id', 'cl_to' => $name ],
			__METHOD__,
			[ 'LIMIT' => $maxPages ]
		);

		$pages = [];

		foreach ( $res as $row ) {
			$pages[] = Title::makeName( $row->page_namespace, $row->page_title );
		}

		return $pages;
	}

	/**
	 * @param int $nsindex
	 * @return string[]
	 */
	protected function getPagesFromNamespace( int $nsindex ): array {
		$maxPages = $this->getConfig()->get( 'ExportPagelistLimit' );

		$dbr = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );
		$res = $dbr->select(
			'page',
			[ 'page_namespace', 'page_title' ],
			[ 'page_namespace' => $nsindex ],
			__METHOD__,
			[ 'LIMIT' => $maxPages ]
		);

		$pages = [];

		foreach ( $res as $row ) {
			$pages[] = Title::makeName( $row->page_namespace, $row->page_title );
		}

		return $pages;
	}

	/**
	 * Expand a list of pages to include templates used in those pages.
	 * @param array $inputPages List of titles to look up
	 * @param array $pageSet Associative array indexed by titles for output
	 * @return array Associative array index by titles
	 */
	protected function getTemplates( array $inputPages, array $pageSet ): array {
		return $this->getLinks( $inputPages, $pageSet,
			'templatelinks',
			[ 'namespace' => 'tl_namespace', 'title' => 'tl_title' ],
			[ 'page_id=tl_from' ]
		);
	}

	/**
	 * Validate link depth setting, if available.
	 */
	protected function validateLinkDepth( int $depth ): int {
		if ( $depth < 0 ) {
			return 0;
		}

		if ( !$this->userCanOverrideExportDepth() ) {
			$maxLinkDepth = $this->getConfig()->get( 'ExportMaxLinkDepth' );
			if ( $depth > $maxLinkDepth ) {
				return $maxLinkDepth;
			}
		}

		/*
		 * There's a HARD CODED limit of 5 levels of recursion here to prevent a
		 * crazy-big export from being done by someone setting the depth
		 * number too high. In other words, last resort safety net.
		 */

		return intval( min( $depth, 5 ) );
	}

	/**
	 * Expand a list of pages to include pages linked to from that page.
	 */
	protected function getPageLinks( array $inputPages, array $pageSet, int $depth ): array {
		for ( ; $depth > 0; --$depth ) {
			$pageSet = $this->getLinks(
				$inputPages, $pageSet, 'pagelinks',
				[ 'namespace' => 'pl_namespace', 'title' => 'pl_title' ],
				[ 'page_id=pl_from' ]
			);
			$inputPages = array_keys( $pageSet );
		}

		return $pageSet;
	}

	/**
	 * Expand a list of pages to include items used in those pages.
	 */
	protected function getLinks(
		array $inputPages,
		array $pageSet,
		string $table,
		array $fields,
		array $join
	): array {
		$dbr = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );

		foreach ( $inputPages as $page ) {
			$title = Title::newFromText( $page );

			if ( $title ) {
				$pageSet[$title->getPrefixedText()] = true;
				/// @todo FIXME: May or may not be more efficient to batch these
				///        by namespace when given multiple input pages.
				$result = $dbr->select(
					[ 'page', $table ],
					$fields,
					array_merge(
						$join,
						[
							'page_namespace' => $title->getNamespace(),
							'page_title' => $title->getDBkey()
						]
					),
					__METHOD__
				);

				foreach ( $result as $row ) {
					$template = Title::makeTitle( $row->namespace, $row->title );
					$pageSet[$template->getPrefixedText()] = true;
				}
			}
		}

		return $pageSet;
	}

	protected function getGroupName(): string {
		return 'pagetools';
	}

	public function getConfig(): Config {
		return MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'da' );
	}
}
