<?php
/**
 * MediaWiki page data importer.
 *
 * Copyright © 2003,2005 Brion Vibber <brion@pobox.com>
 * https://www.mediawiki.org/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

namespace DataAccounting;

use MediaWiki\Cache\CacheKeyHelper;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SlotRoleRegistry;

// DA modification; For typing.
use ImportSource;
use Config;
use Content;
use ContentHandler;
use Language;
use NamespaceInfo;
use TitleFactory;
use UploadRevisionImporter;
use XMLReader;
use UploadSourceAdapter;
use NaiveImportTitleFactory;
use ImportTitleFactory;
use ExternalUserNames;
use Status;
use NamespaceAwareForeignTitleFactory;
use RequestContext;
use MediaWiki\MediaWikiServices;
use WikiRevision;
use Title;
use ForeignTitle;
use DeferredUpdates;
use SiteStatsUpdate;

/**
 * This class is cloned from Mediawiki 1.37.0's WikiImporter. Almost the same
 * except that:
 * 1. handleVerification is implemented
 * 2. handleRevision does handleVerification
 * 3. processVerification is implemented, which writes verification info into the DB
 * 4. processRevision does processVerification
 * 5. handleWitness for witness-related operation
 *
 * You can search for "Data Accounting modification" in this file to see
 * DA-specific code.
 */

/**
 * XML file reader for the page data importer.
 *
 * implements Special:Import
 * @ingroup SpecialPage
 */
class VerifiedWikiImporter {
	private XMLReader $reader;

	private ?array $foreignNamespaces = null;

	/** @var callable|array|null */
	private $mLogItemCallback;

	/** @var callable|array|null */
	private $mUploadCallback;

	/** @var callable|array|null */
	private $mRevisionCallback;

	/** @var callable|array|null */
	private $mPageCallback;

	/** @var callable|array|null */
	private $mSiteInfoCallback;

	/** @var callable|array|null */
	private $mPageOutCallback;

	/** @var callable|array|null */
	private $mNoticeCallback;

	private ?bool $mDebug = null;

	private ?bool $mImportUploads = null;

	private ?string $mImageBasePath = null;

	private bool $mNoUpdates = false;

	private int $pageOffset = 0;

	private Config $config;

	private ImportTitleFactory $importTitleFactory;

	private HookRunner $hookRunner;

	private array $countableCache = [];

	private bool $disableStatisticsUpdate = false;

	private ExternalUserNames $externalUserNames;

	private Language $contentLanguage;

	private NamespaceInfo $namespaceInfo;

	private TitleFactory $titleFactory;

	private WikiPageFactory $wikiPageFactory;

	private UploadRevisionImporter $uploadRevisionImporter;

	private PermissionManager $permissionManager;

	private IContentHandlerFactory $contentHandlerFactory;

	private SlotRoleRegistry $slotRoleRegistry;

	/**
	 * Creates an ImportXMLReader drawing from the source provided
	 * @param ImportSource $source
	 * @param Config $config
	 * @param HookContainer $hookContainer
	 * @param Language $contentLanguage
	 * @param NamespaceInfo $namespaceInfo
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UploadRevisionImporter $uploadRevisionImporter
	 * @param PermissionManager $permissionManager
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param SlotRoleRegistry $slotRoleRegistry
	 * @throws MWException
	 */
	public function __construct(
		ImportSource $source,
		Config $config,
		HookContainer $hookContainer,
		Language $contentLanguage,
		NamespaceInfo $namespaceInfo,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		UploadRevisionImporter $uploadRevisionImporter,
		PermissionManager $permissionManager,
		IContentHandlerFactory $contentHandlerFactory,
		SlotRoleRegistry $slotRoleRegistry
	) {
		$this->reader = new XMLReader();
		$this->config = $config;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->contentLanguage = $contentLanguage;
		$this->namespaceInfo = $namespaceInfo;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->uploadRevisionImporter = $uploadRevisionImporter;
		$this->permissionManager = $permissionManager;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->slotRoleRegistry = $slotRoleRegistry;

		if ( !in_array( 'uploadsource', stream_get_wrappers() ) ) {
			stream_wrapper_register( 'uploadsource', UploadSourceAdapter::class );
		}
		$id = UploadSourceAdapter::registerSource( $source );

		// Enable the entity loader, as it is needed for loading external URLs via
		// XMLReader::open (T86036)
		$oldDisable = libxml_disable_entity_loader( false );
		if ( defined( 'LIBXML_PARSEHUGE' ) ) {
			$status = $this->reader->open( "uploadsource://$id", null, LIBXML_PARSEHUGE );
		} else {
			$status = $this->reader->open( "uploadsource://$id" );
		}
		if ( !$status ) {
			$error = libxml_get_last_error();
			libxml_disable_entity_loader( $oldDisable );
			throw new MWException( 'Encountered an internal error while initializing WikiImporter object: ' .
				$error->message );
		}
		libxml_disable_entity_loader( $oldDisable );

		// Default callbacks
		$this->setPageCallback( [ $this, 'beforeImportPage' ] );
		$this->setRevisionCallback( [ $this, "importRevision" ] );
		$this->setUploadCallback( [ $this, 'importUpload' ] );
		$this->setLogItemCallback( [ $this, 'importLogItem' ] );
		$this->setPageOutCallback( [ $this, 'finishImportPage' ] );

		$this->importTitleFactory = new NaiveImportTitleFactory(
			$this->contentLanguage,
			$this->namespaceInfo,
			$this->titleFactory
		);
		$this->externalUserNames = new ExternalUserNames( 'imported', false );
	}

	/**
	 * @return null|XMLReader
	 */
	public function getReader(): ?XMLReader {
		return $this->reader;
	}

	/**
	 * @param string $err
	 */
	public function throwXmlError( string $err ) {
		$this->debug( "FAILURE: $err" );
		wfDebug( "WikiImporter XML error: $err" );
	}

	/**
	 * @param string $data
	 */
	public function debug( string $data ) {
		if ( $this->mDebug ) {
			wfDebug( "IMPORT: $data" );
		}
	}

	/**
	 * @param string $data
	 */
	public function warn( string $data ) {
		wfDebug( "IMPORT: $data" );
	}

	/**
	 * @param string $msg
	 * @param mixed ...$params
	 */
	public function notice( string $msg, ...$params ) {
		if ( is_callable( $this->mNoticeCallback ) ) {
			call_user_func( $this->mNoticeCallback, $msg, $params );
		} else { # No ImportReporter -> CLI
			// T177997: the command line importers should call setNoticeCallback()
			// for their own custom callback to echo the notice
			wfDebug( wfMessage( $msg, $params )->text() );
		}
	}

	/**
	 * Set debug mode...
	 * @param bool $debug
	 */
	public function setDebug( bool $debug ) {
		$this->mDebug = $debug;
	}

	/**
	 * Set 'no updates' mode. In this mode, the link tables will not be updated by the importer
	 * @param bool $noupdates
	 */
	public function setNoUpdates( bool $noupdates ) {
		$this->mNoUpdates = $noupdates;
	}

	/**
	 * Sets 'pageOffset' value. So it will skip the first n-1 pages
	 * and start from the nth page. It's 1-based indexing.
	 * @param int $nthPage
	 * @since 1.29
	 */
	public function setPageOffset( int $nthPage ) {
		$this->pageOffset = $nthPage;
	}

	/**
	 * Set a callback that displays notice messages
	 *
	 * @param callable|array $callback
	 * @return callable|array|null
	 */
	public function setNoticeCallback( $callback ) {
		return wfSetVar( $this->mNoticeCallback, $callback );
	}

	/**
	 * Sets the action to perform as each new page in the stream is reached.
	 * @param callable|array $callback
	 * @return callable|array|null
	 */
	public function setPageCallback( $callback ) {
		$previous = $this->mPageCallback;
		$this->mPageCallback = $callback;
		return $previous;
	}

	/**
	 * Sets the action to perform as each page in the stream is completed.
	 * Callback accepts the page title (as a Title object), a second object
	 * with the original title form (in case it's been overridden into a
	 * local namespace), and a count of revisions.
	 *
	 * @param callable|array $callback
	 * @return callable|array|null
	 */
	public function setPageOutCallback( $callback ) {
		$previous = $this->mPageOutCallback;
		$this->mPageOutCallback = $callback;
		return $previous;
	}

	/**
	 * Sets the action to perform as each page revision is reached.
	 * @param callable|array $callback
	 * @return callable|array|null
	 */
	public function setRevisionCallback( $callback ) {
		$previous = $this->mRevisionCallback;
		$this->mRevisionCallback = $callback;
		return $previous;
	}

	/**
	 * Sets the action to perform as each file upload version is reached.
	 * @param callable|array $callback
	 * @return callable|null
	 */
	public function setUploadCallback( $callback ) {
		$previous = $this->mUploadCallback;
		$this->mUploadCallback = $callback;
		return $previous;
	}

	/**
	 * Sets the action to perform as each log item reached.
	 * @param callable|array $callback
	 * @return callable|array|null
	 */
	public function setLogItemCallback( $callback ) {
		$previous = $this->mLogItemCallback;
		$this->mLogItemCallback = $callback;
		return $previous;
	}

	/**
	 * Sets the action to perform when site info is encountered
	 * @param callable|array $callback
	 * @return callable|array|null
	 */
	public function setSiteInfoCallback( $callback ) {
		$previous = $this->mSiteInfoCallback;
		$this->mSiteInfoCallback = $callback;
		return $previous;
	}

	/**
	 * Sets the factory object to use to convert ForeignTitle objects into local
	 * Title objects
	 * @param ImportTitleFactory $factory
	 */
	public function setImportTitleFactory( ImportTitleFactory $factory ) {
		$this->importTitleFactory = $factory;
	}

	/**
	 * Set a target namespace to override the defaults
	 * @param int|null $namespace
	 * @return bool
	 */
	public function setTargetNamespace( ?int $namespace ): bool {
		if ( $namespace === null ) {
			// Don't override namespaces
			$this->setImportTitleFactory(
				new NaiveImportTitleFactory(
					$this->contentLanguage,
					$this->namespaceInfo,
					$this->titleFactory
				)
			);
			return true;
		} elseif (
			$namespace >= 0 &&
			$this->namespaceInfo->exists( intval( $namespace ) )
		) {
			$namespace = intval( $namespace );
			$this->setImportTitleFactory(
				new NamespaceImportTitleFactory(
					$this->namespaceInfo,
					$this->titleFactory,
					$namespace
				)
			);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set a target root page under which all pages are imported
	 * @param string|null $rootpage
	 * @return Status
	 */
	public function setTargetRootPage( ?string $rootpage ): Status {
		$status = Status::newGood();
		$nsInfo = $this->namespaceInfo;
		if ( $rootpage === null ) {
			// No rootpage
			$this->setImportTitleFactory(
				new NaiveImportTitleFactory(
					$this->contentLanguage,
					$nsInfo,
					$this->titleFactory
				)
			);
		} elseif ( $rootpage !== '' ) {
			$rootpage = rtrim( $rootpage, '/' ); // avoid double slashes
			$title = Title::newFromText( $rootpage );

			if ( !$title || $title->isExternal() ) {
				$status->fatal( 'import-rootpage-invalid' );
			} elseif ( !$nsInfo->hasSubpages( $title->getNamespace() ) ) {
				$displayNSText = $title->getNamespace() === NS_MAIN
					? wfMessage( 'blanknamespace' )->text()
					: $this->contentLanguage->getNsText( $title->getNamespace() );
				$status->fatal( 'import-rootpage-nosubpage', $displayNSText );
			} else {
				// set namespace to 'all', so the namespace check in processTitle() can pass
				$this->setTargetNamespace( null );
				$this->setImportTitleFactory(
					new SubpageImportTitleFactory(
						$nsInfo,
						$this->titleFactory,
						$title
					)
				);
			}
		}
		return $status;
	}

	public function setImageBasePath( string $dir ) {
		$this->mImageBasePath = $dir;
	}

	public function setImportUploads( bool $import ) {
		$this->mImportUploads = $import;
	}

	/**
	 * @since 1.31
	 * @param string $usernamePrefix Prefix to apply to unknown (and possibly also known) usernames
	 * @param bool $assignKnownUsers Whether to apply the prefix to usernames that exist locally
	 */
	public function setUsernamePrefix( string $usernamePrefix, bool $assignKnownUsers ) {
		$this->externalUserNames = new ExternalUserNames( $usernamePrefix, $assignKnownUsers );
	}

	/**
	 * Statistics update can cause a lot of time
	 * @since 1.29
	 */
	public function disableStatisticsUpdate() {
		$this->disableStatisticsUpdate = true;
	}

	/**
	 * Default per-page callback. Sets up some things related to site statistics
	 * @param array $titleAndForeignTitle Two-element array, with Title object at
	 * index 0 and ForeignTitle object at index 1
	 * @return bool
	 */
	public function beforeImportPage( array $titleAndForeignTitle ): bool {
		$title = $titleAndForeignTitle[0];
		$page = $this->wikiPageFactory->newFromTitle( $title );
		$this->countableCache['title_' . $title->getPrefixedText()] = $page->isCountable();
		return true;
	}

	/**
	 * Default per-revision callback, performs the import.
	 * @param WikiRevision $revision
	 * @return bool
	 */
	public function importRevision( WikiRevision $revision ): bool {
		if ( !$revision->getContentHandler()->canBeUsedOn( $revision->getTitle() ) ) {
			$this->notice( 'import-error-bad-location',
				$revision->getTitle()->getPrefixedText(),
				$revision->getID(),
				$revision->getModel(),
				$revision->getFormat()
			);

			return false;
		}

		try {
			return $revision->importOldRevision();
		} catch ( MWContentSerializationException $ex ) {
			$this->notice( 'import-error-unserialize',
				$revision->getTitle()->getPrefixedText(),
				$revision->getID(),
				$revision->getModel(),
				$revision->getFormat()
			);
		}

		return false;
	}

	/**
	 * Default per-revision callback, performs the import.
	 * @param WikiRevision $revision
	 * @return bool
	 */
	public function importLogItem( WikiRevision $revision ): bool {
		return $revision->importLogItem();
	}

	/**
	 * Dummy for now...
	 * @param WikiRevision $revision
	 * @return bool
	 */
	public function importUpload( WikiRevision $revision ): bool {
		$status = $this->uploadRevisionImporter->import( $revision );
		return $status->isGood();
	}

	/**
	 * Mostly for hook use
	 * @param PageIdentity $pageIdentity
	 * @param ForeignTitle $foreignTitle
	 * @param int $revCount
	 * @param int $sRevCount
	 * @param array $pageInfo
	 * @return bool
	 */
	public function finishImportPage( PageIdentity $pageIdentity, ForeignTitle $foreignTitle,
		int $revCount, int $sRevCount, array $pageInfo
	): bool {
		// Update article count statistics (T42009)
		// The normal counting logic in WikiPage->doEditUpdates() is designed for
		// one-revision-at-a-time editing, not bulk imports. In this situation it
		// suffers from issues of replica DB lag. We let WikiPage handle the total page
		// and revision count, and we implement our own custom logic for the
		// article (content page) count.
		if ( !$this->disableStatisticsUpdate ) {
			$page = $this->wikiPageFactory->newFromTitle( $pageIdentity );

			$page->loadPageData( 'fromdbmaster' );
			$content = $page->getContent();
			if ( $content === null ) {
				wfDebug( __METHOD__ . ': Skipping article count adjustment for ' . $pageIdentity .
					' because WikiPage::getContent() returned null' );
			} else {
				// No user is available
				$user = RequestContext::getMain()->getUser();
				$editInfo = $page->prepareContentForEdit( $content, null, $user );
				$countKey = 'title_' . CacheKeyHelper::getKeyForPage( $pageIdentity );
				$countable = $page->isCountable( $editInfo );
				if ( array_key_exists( $countKey, $this->countableCache ) &&
					$countable != $this->countableCache[$countKey] ) {
					DeferredUpdates::addUpdate( SiteStatsUpdate::factory( [
						'articles' => ( (int)$countable - (int)$this->countableCache[$countKey] )
					] ) );
				}
			}
		}

		$title = Title::castFromPageIdentity( $pageIdentity );
		return $this->hookRunner->onAfterImportPage( $title, $foreignTitle,
			$revCount, $sRevCount, $pageInfo );
	}

	/**
	 * Notify the callback function of site info
	 * @param array $siteInfo
	 * @return mixed|false
	 */
	private function siteInfoCallback( array $siteInfo ) {
		if ( isset( $this->mSiteInfoCallback ) ) {
			return call_user_func_array(
				$this->mSiteInfoCallback,
				[ $siteInfo, $this ]
			);
		} else {
			return false;
		}
	}

	/**
	 * Notify the callback function when a new "<page>" is reached.
	 * @param array $title
	 */
	public function pageCallback( array $title ) {
		if ( isset( $this->mPageCallback ) ) {
			call_user_func( $this->mPageCallback, $title );
		}
	}

	/**
	 * Notify the callback function when a "</page>" is closed.
	 * @param PageIdentity $pageIdentity
	 * @param ForeignTitle $foreignTitle
	 * @param int $revCount
	 * @param int $sucCount Number of revisions for which callback returned true
	 * @param array $pageInfo Associative array of page information
	 */
	private function pageOutCallback( PageIdentity $pageIdentity, ForeignTitle $foreignTitle,
			int $revCount, int $sucCount, array $pageInfo ) {
		if ( isset( $this->mPageOutCallback ) ) {
			call_user_func_array( $this->mPageOutCallback, func_get_args() );
		}
	}

	/**
	 * Notify the callback function of a revision
	 * @param WikiRevision $revision
	 * @return bool|mixed
	 */
	private function revisionCallback( WikiRevision $revision ) {
		if ( isset( $this->mRevisionCallback ) ) {
			return call_user_func_array(
				$this->mRevisionCallback,
				[ $revision, $this ]
			);
		} else {
			return false;
		}
	}

	/**
	 * Notify the callback function of a new log item
	 * @param WikiRevision $revision
	 * @return mixed|false
	 */
	private function logItemCallback( WikiRevision $revision ) {
		if ( isset( $this->mLogItemCallback ) ) {
			return call_user_func_array(
				$this->mLogItemCallback,
				[ $revision, $this ]
			);
		} else {
			return false;
		}
	}

	/**
	 * Retrieves the contents of the named attribute of the current element.
	 * @param string $attr The name of the attribute
	 * @return string The value of the attribute or an empty string if it is not set in the current
	 * element.
	 */
	public function nodeAttribute( $attr ): string {
		return $this->reader->getAttribute( $attr );
	}

	/**
	 * Shouldn't something like this be built-in to XMLReader?
	 * Fetches text contents of the current element, assuming
	 * no sub-elements or such scary things.
	 * @return string
	 * @internal
	 */
	public function nodeContents(): string {
		if ( $this->reader->isEmptyElement ) {
			return "";
		}
		$buffer = "";
		while ( $this->reader->read() ) {
			switch ( $this->reader->nodeType ) {
				case XMLReader::TEXT:
				case XMLReader::CDATA:
				case XMLReader::SIGNIFICANT_WHITESPACE:
					$buffer .= $this->reader->value;
					break;
				case XMLReader::END_ELEMENT:
					return $buffer;
			}
		}

		$this->reader->close();
		return '';
	}

	/**
	 * Primary entry point
	 * @throws Exception
	 * @throws MWException
	 * @return bool
	 */
	public function doImport(): bool {
		// Calls to reader->read need to be wrapped in calls to
		// libxml_disable_entity_loader() to avoid local file
		// inclusion attacks (T48932).
		$oldDisable = libxml_disable_entity_loader( true );
		try {
			$this->reader->read();

			if ( $this->reader->localName != 'mediawiki' ) {
				libxml_disable_entity_loader( $oldDisable );
				throw new MWException( "Expected <mediawiki> tag, got " .
					$this->reader->localName );
			}
			$this->debug( "<mediawiki> tag is correct." );

			$this->debug( "Starting primary dump processing loop." );

			$keepReading = $this->reader->read();
			$skip = false;
			$pageCount = 0;
			while ( $keepReading ) {
				$tag = $this->reader->localName;
				if ( $this->pageOffset ) {
					if ( $tag === 'page' ) {
						$pageCount++;
					}
					if ( $pageCount < $this->pageOffset ) {
						$keepReading = $this->reader->next();
						continue;
					}
				}
				$type = $this->reader->nodeType;

				if ( !$this->hookRunner->onImportHandleToplevelXMLTag( $this ) ) {
					// Do nothing
				} elseif ( $tag == 'mediawiki' && $type == XMLReader::END_ELEMENT ) {
					break;
				} elseif ( $tag == 'siteinfo' ) {
					$this->handleSiteInfo();
				} elseif ( $tag == 'page' ) {
					$this->handlePage();
				} elseif ( $tag == 'logitem' ) {
					$this->handleLogItem();
				} elseif ( $tag != '#text' ) {
					$this->warn( "Unhandled top-level XML tag $tag" );

					$skip = true;
				}

				if ( $skip ) {
					$keepReading = $this->reader->next();
					$skip = false;
					$this->debug( "Skip" );
				} else {
					$keepReading = $this->reader->read();
				}
			}
		} finally {
			libxml_disable_entity_loader( $oldDisable );
			$this->reader->close();
		}

		return true;
	}

	private function handleSiteInfo() {
		$this->debug( "Enter site info handler." );
		$siteInfo = [];

		// Fields that can just be stuffed in the siteInfo object
		$normalFields = [ 'sitename', 'base', 'generator', 'case' ];

		while ( $this->reader->read() ) {
			if ( $this->reader->nodeType == XMLReader::END_ELEMENT &&
					$this->reader->localName == 'siteinfo' ) {
				break;
			}

			$tag = $this->reader->localName;

			if ( $tag == 'namespace' ) {
				$this->foreignNamespaces[$this->nodeAttribute( 'key' )] =
					$this->nodeContents();
			} elseif ( in_array( $tag, $normalFields ) ) {
				$siteInfo[$tag] = $this->nodeContents();
			}
		}

		$siteInfo['_namespaces'] = $this->foreignNamespaces;
		$this->siteInfoCallback( $siteInfo );
	}

	private function handleLogItem() {
		$this->debug( "Enter log item handler." );
		$logInfo = [];

		// Fields that can just be stuffed in the pageInfo object
		$normalFields = [ 'id', 'comment', 'type', 'action', 'timestamp',
			'logtitle', 'params' ];

		while ( $this->reader->read() ) {
			if ( $this->reader->nodeType == XMLReader::END_ELEMENT &&
					$this->reader->localName == 'logitem' ) {
				break;
			}

			$tag = $this->reader->localName;

			if ( !$this->hookRunner->onImportHandleLogItemXMLTag( $this, $logInfo ) ) {
				// Do nothing
			} elseif ( in_array( $tag, $normalFields ) ) {
				$logInfo[$tag] = $this->nodeContents();
			} elseif ( $tag == 'contributor' ) {
				$logInfo['contributor'] = $this->handleContributor();
			} elseif ( $tag != '#text' ) {
				$this->warn( "Unhandled log-item XML tag $tag" );
			}
		}

		$this->processLogItem( $logInfo );
	}

	/**
	 * @param array $logInfo
	 * @return mixed|false
	 */
	private function processLogItem( array $logInfo ) {
		$revision = new WikiRevision( $this->config );

		if ( isset( $logInfo['id'] ) ) {
			$revision->setID( $logInfo['id'] );
		}
		$revision->setType( $logInfo['type'] );
		$revision->setAction( $logInfo['action'] );
		if ( isset( $logInfo['timestamp'] ) ) {
			$revision->setTimestamp( $logInfo['timestamp'] );
		}
		if ( isset( $logInfo['params'] ) ) {
			$revision->setParams( $logInfo['params'] );
		}
		if ( isset( $logInfo['logtitle'] ) ) {
			// @todo Using Title for non-local titles is a recipe for disaster.
			// We should use ForeignTitle here instead.
			$revision->setTitle( Title::newFromText( $logInfo['logtitle'] ) );
		}

		$revision->setNoUpdates( $this->mNoUpdates );

		if ( isset( $logInfo['comment'] ) ) {
			$revision->setComment( $logInfo['comment'] );
		}

		if ( isset( $logInfo['contributor']['ip'] ) ) {
			$revision->setUserIP( $logInfo['contributor']['ip'] );
		}

		if ( !isset( $logInfo['contributor']['username'] ) ) {
			$revision->setUsername( $this->externalUserNames->addPrefix( 'Unknown user' ) );
		} else {
			$revision->setUsername(
				$this->externalUserNames->applyPrefix( $logInfo['contributor']['username'] )
			);
		}

		return $this->logItemCallback( $revision );
	}

	private function handlePage() {
		// Handle page data.
		$this->debug( "Enter page handler." );
		$pageInfo = [ 'revisionCount' => 0, 'successfulRevisionCount' => 0 ];

		// Fields that can just be stuffed in the pageInfo object
		$normalFields = [ 'title', 'ns', 'id', 'redirect', 'restrictions' ];

		$skip = false;
		$badTitle = false;

		while ( $skip ? $this->reader->next() : $this->reader->read() ) {
			if ( $this->reader->nodeType == XMLReader::END_ELEMENT &&
					$this->reader->localName == 'page' ) {
				break;
			}

			$skip = false;

			$tag = $this->reader->localName;

			if ( $badTitle ) {
				// The title is invalid, bail out of this page
				$skip = true;
			} elseif ( !$this->hookRunner->onImportHandlePageXMLTag( $this, $pageInfo ) ) {
				// Do nothing
			} elseif ( in_array( $tag, $normalFields ) ) {
				// An XML snippet:
				// <page>
				//     <id>123</id>
				//     <title>Page</title>
				//     <redirect title="NewTitle"/>
				//     ...
				// Because the redirect tag is built differently, we need special handling for that case.
				if ( $tag == 'redirect' ) {
					$pageInfo[$tag] = $this->nodeAttribute( 'title' );
				} else {
					$pageInfo[$tag] = $this->nodeContents();
				}
			} elseif ( $tag == 'revision' || $tag == 'upload' ) {
				if ( !isset( $title ) ) {
					$title = $this->processTitle( $pageInfo['title'],
						$pageInfo['ns'] ?? null );

					// $title is either an array of two titles or false.
					if ( is_array( $title ) ) {
						$this->pageCallback( $title );
						list( $pageInfo['_title'], $foreignTitle ) = $title;
					} else {
						$badTitle = true;
						$skip = true;
					}
				}

				if ( $title ) {
					if ( $tag == 'revision' ) {
						$this->handleRevision( $pageInfo );
					} else {
						$this->handleUpload( $pageInfo );
					}
				}
			} elseif ( $tag != '#text' ) {
				$this->warn( "Unhandled page XML tag $tag" );
				$skip = true;
			}
		}

		// @note $pageInfo is only set if a valid $title is processed above with
		//       no error. If we have a valid $title, then pageCallback is called
		//       above, $pageInfo['title'] is set and we do pageOutCallback here.
		//       If $pageInfo['_title'] is not set, then $foreignTitle is also not
		//       set since they both come from $title above.
		if ( array_key_exists( '_title', $pageInfo ) ) {
			/** @var Title $title */
			$title = $pageInfo['_title'];
			$this->pageOutCallback(
				$title,
				$foreignTitle,
				$pageInfo['revisionCount'],
				$pageInfo['successfulRevisionCount'],
				$pageInfo
			);
		}
	}

	/**
	 * @param array &$pageInfo
	 */
	private function handleRevision( array &$pageInfo ) {
		$this->debug( "Enter revision handler" );
		$revisionInfo = [];

		$normalFields = [ 'id', 'parentid', 'timestamp', 'comment', 'minor', 'origin',
			'model', 'format', 'text', 'sha1' ];

		$skip = false;

		while ( $skip ? $this->reader->next() : $this->reader->read() ) {
			if ( $this->reader->nodeType == XMLReader::END_ELEMENT &&
					$this->reader->localName == 'revision' ) {
				break;
			}

			$tag = $this->reader->localName;

			if ( !$this->hookRunner->onImportHandleRevisionXMLTag(
				$this, $pageInfo, $revisionInfo )
			) {
				// Do nothing
			} elseif ( in_array( $tag, $normalFields ) ) {
				$revisionInfo[$tag] = $this->nodeContents();
			} elseif ( $tag == 'content' ) {
				// We can have multiple content tags, so make this an array.
				$revisionInfo[$tag][] = $this->handleContent();
			} elseif ( $tag == 'contributor' ) {
				$revisionInfo['contributor'] = $this->handleContributor();
			} elseif ( $tag == 'verification' ) { // Data accounting modification
				$revisionInfo['verification'] = $this->handleVerification();
			} elseif ( $tag != '#text' ) {
				$this->warn( "Unhandled revision XML tag $tag" );
				$skip = true;
			}
		}

		$pageInfo['revisionCount']++;
		if ( $this->processRevision( $pageInfo, $revisionInfo ) ) {
			$pageInfo['successfulRevisionCount']++;
		}
	}

	private function handleContent() {
		$this->debug( "Enter content handler" );
		$contentInfo = [];

		$normalFields = [ 'role', 'origin', 'model', 'format', 'text' ];

		$skip = false;

		while ( $skip ? $this->reader->next() : $this->reader->read() ) {
			if ( $this->reader->nodeType == XMLReader::END_ELEMENT &&
				$this->reader->localName == 'content' ) {
				break;
			}

			$tag = $this->reader->localName;

			if ( !$this->hookRunner->onImportHandleContentXMLTag(
				$this, $contentInfo )
			) {
				// Do nothing
			} elseif ( in_array( $tag, $normalFields ) ) {
				$contentInfo[$tag] = $this->nodeContents();
			} elseif ( $tag != '#text' ) {
				$this->warn( "Unhandled content XML tag $tag" );
				$skip = true;
			}
		}

		return $contentInfo;
	}

	/**
	 * @param Title $title
	 * @param int $revisionId
	 * @param array $contentInfo
	 *
	 * @return Content
	 * @throws MWException
	 */
	private function makeContent( Title $title, int $revisionId, array $contentInfo ): Content {
		global $wgMaxArticleSize;

		if ( !isset( $contentInfo['text'] ) ) {
			throw new MWException( 'Missing text field in import.' );
		}

		// Make sure revisions won't violate $wgMaxArticleSize, which could lead to
		// database errors and instability. Testing for revisions with only listed
		// content models, as other content models might use serialization formats
		// which aren't checked against $wgMaxArticleSize.
		if ( ( !isset( $contentInfo['model'] ) ||
				in_array( $contentInfo['model'], [
					'wikitext',
					'css',
					'json',
					'javascript',
					'text',
					''
				] ) ) &&
			strlen( $contentInfo['text'] ) > $wgMaxArticleSize * 1024
		) {
			throw new MWException( 'The text of ' .
				( $revisionId ?
					"the revision with ID $revisionId" :
					'a revision'
				) . " exceeds the maximum allowable size ($wgMaxArticleSize KiB)" );
		}

		$role = $contentInfo['role'] ?? SlotRecord::MAIN;
		$model = $contentInfo['model'] ?? $this->getDefaultContentModel( $title, $role );
		$handler = $this->getContentHandler( $model );

		$text = $handler->importTransform( $contentInfo['text'] );

		return $handler->unserializeContent( $text );
	}

	/**
	 * @param array $pageInfo
	 * @param array $revisionInfo
	 * @throws MWException
	 * @return mixed|false
	 */
	private function processRevision( array $pageInfo, array $revisionInfo ) {
		$revision = new WikiRevision( $this->config );

		$revId = $revisionInfo['id'] ?? 0;
		if ( $revId ) {
			$revision->setID( $revisionInfo['id'] );
		}

		$title = $pageInfo['_title'];
		$revision->setTitle( $title );

		$content = $this->makeContent( $title, $revId, $revisionInfo );
		$revision->setContent( SlotRecord::MAIN, $content );

		foreach ( $revisionInfo['content'] ?? [] as $slotInfo ) {
			if ( !isset( $slotInfo['role'] ) ) {
				throw new MWException( "Missing role for imported slot." );
			}

			$content = $this->makeContent( $title, $revId, $slotInfo );
			$revision->setContent( $slotInfo['role'], $content );
		}
		$revision->setTimestamp( $revisionInfo['timestamp'] ?? wfTimestampNow() );

		if ( isset( $revisionInfo['comment'] ) ) {
			$revision->setComment( $revisionInfo['comment'] );
		}

		if ( isset( $revisionInfo['minor'] ) ) {
			$revision->setMinor( true );
		}
		if ( isset( $revisionInfo['contributor']['ip'] ) ) {
			$revision->setUserIP( $revisionInfo['contributor']['ip'] );
		} elseif ( isset( $revisionInfo['contributor']['username'] ) ) {
			$revision->setUsername(
				$this->externalUserNames->applyPrefix( $revisionInfo['contributor']['username'] )
			);
		} else {
			$revision->setUsername( $this->externalUserNames->addPrefix( 'Unknown user' ) );
		}
		if ( isset( $revisionInfo['sha1'] ) ) {
			$revision->setSha1Base36( $revisionInfo['sha1'] );
		}
		$revision->setNoUpdates( $this->mNoUpdates );

		$out = $this->revisionCallback( $revision );

		// Data Accounting modification.
		// We need to do this after the callback, which is `importRevision`,
		// because we need the newly generated revision id.
		$this->processVerification( $revisionInfo['verification'], $title );
		return $out;
	}

	/**
	 * @param array &$pageInfo
	 * @return mixed
	 */
	private function handleUpload( array &$pageInfo ) {
		$this->debug( "Enter upload handler" );
		$uploadInfo = [];

		$normalFields = [ 'timestamp', 'comment', 'filename', 'text',
			'src', 'size', 'sha1base36', 'archivename', 'rel' ];

		$skip = false;

		while ( $skip ? $this->reader->next() : $this->reader->read() ) {
			if ( $this->reader->nodeType == XMLReader::END_ELEMENT &&
					$this->reader->localName == 'upload' ) {
				break;
			}

			$tag = $this->reader->localName;

			if ( !$this->hookRunner->onImportHandleUploadXMLTag( $this, $pageInfo ) ) {
				// Do nothing
			} elseif ( in_array( $tag, $normalFields ) ) {
				$uploadInfo[$tag] = $this->nodeContents();
			} elseif ( $tag == 'contributor' ) {
				$uploadInfo['contributor'] = $this->handleContributor();
			} elseif ( $tag == 'contents' ) {
				$contents = $this->nodeContents();
				$encoding = $this->reader->getAttribute( 'encoding' );
				if ( $encoding === 'base64' ) {
					$uploadInfo['fileSrc'] = $this->dumpTemp( base64_decode( $contents ) );
					$uploadInfo['isTempSrc'] = true;
				}
			} elseif ( $tag != '#text' ) {
				$this->warn( "Unhandled upload XML tag $tag" );
				$skip = true;
			}
		}

		if ( $this->mImageBasePath && isset( $uploadInfo['rel'] ) ) {
			$path = "{$this->mImageBasePath}/{$uploadInfo['rel']}";
			if ( file_exists( $path ) ) {
				$uploadInfo['fileSrc'] = $path;
				$uploadInfo['isTempSrc'] = false;
			}
		}

		if ( $this->mImportUploads ) {
			return $this->processUpload( $pageInfo, $uploadInfo );
		}
	}

	/**
	 * @param string $contents
	 * @return string
	 */
	private function dumpTemp( string $contents ) {
		$filename = tempnam( wfTempDir(), 'importupload' );
		file_put_contents( $filename, $contents );
		return $filename;
	}

	/**
	 * @param array $pageInfo
	 * @param array $uploadInfo
	 * @return mixed
	 */
	private function processUpload( array $pageInfo, array $uploadInfo ) {
		$revision = new WikiRevision( $this->config );
		$revId = $pageInfo['id'];
		$title = $pageInfo['_title'];
		$content = $this->makeContent( $title, $revId, $uploadInfo );

		$revision->setTitle( $title );
		$revision->setID( $revId );
		$revision->setTimestamp( $uploadInfo['timestamp'] );
		$revision->setContent( SlotRecord::MAIN, $content );
		$revision->setFilename( $uploadInfo['filename'] );
		if ( isset( $uploadInfo['archivename'] ) ) {
			$revision->setArchiveName( $uploadInfo['archivename'] );
		}
		$revision->setSrc( $uploadInfo['src'] );
		if ( isset( $uploadInfo['fileSrc'] ) ) {
			$revision->setFileSrc( $uploadInfo['fileSrc'],
				!empty( $uploadInfo['isTempSrc'] )
			);
		}
		if ( isset( $uploadInfo['sha1base36'] ) ) {
			$revision->setSha1Base36( $uploadInfo['sha1base36'] );
		}
		$revision->setSize( intval( $uploadInfo['size'] ) );
		$revision->setComment( $uploadInfo['comment'] );

		if ( isset( $uploadInfo['contributor']['ip'] ) ) {
			$revision->setUserIP( $uploadInfo['contributor']['ip'] );
		}
		if ( isset( $uploadInfo['contributor']['username'] ) ) {
			$revision->setUsername(
				$this->externalUserNames->applyPrefix( $uploadInfo['contributor']['username'] )
			);
		}
		$revision->setNoUpdates( $this->mNoUpdates );

		return call_user_func( $this->mUploadCallback, $revision );
	}

	private function handleContributor(): array {
		$this->debug( "Enter contributor handler." );

		if ( $this->reader->isEmptyElement ) {
			return [];
		}

		$fields = [ 'id', 'ip', 'username' ];
		$info = [];

		while ( $this->reader->read() ) {
			if ( $this->reader->nodeType == XMLReader::END_ELEMENT &&
					$this->reader->localName == 'contributor' ) {
				break;
			}

			$tag = $this->reader->localName;

			if ( in_array( $tag, $fields ) ) {
				$info[$tag] = $this->nodeContents();
			}
		}

		return $info;
	}

	/**
	 * @param string $text
	 * @param string|null $ns
	 * @return array|false
	 */
	private function processTitle( string $text, ?string $ns = null ) {
		if ( $this->foreignNamespaces === null ) {
			$foreignTitleFactory = new NaiveForeignTitleFactory(
				$this->contentLanguage
			);
		} else {
			$foreignTitleFactory = new NamespaceAwareForeignTitleFactory(
				$this->foreignNamespaces );
		}

		$foreignTitle = $foreignTitleFactory->createForeignTitle( $text,
			intval( $ns ) );

		$title = $this->importTitleFactory->createTitleFromForeignTitle(
			$foreignTitle );

		$commandLineMode = $this->config->get( 'CommandLineMode' );
		if ( $title === null ) {
			# Invalid page title? Ignore the page
			$this->notice( 'import-error-invalid', $foreignTitle->getFullText() );
			return false;
		} elseif ( $title->isExternal() ) {
			$this->notice( 'import-error-interwiki', $title->getPrefixedText() );
			return false;
		} elseif ( !$title->canExist() ) {
			$this->notice( 'import-error-special', $title->getPrefixedText() );
			return false;
		} elseif ( !$commandLineMode ) {
			$user = RequestContext::getMain()->getUser();

			if ( !$this->permissionManager->userCan( 'edit', $user, $title ) ) {
				# Do not import if the importing wiki user cannot edit this page
				$this->notice( 'import-error-edit', $title->getPrefixedText() );

				return false;
			}
		}

		return [ $title, $foreignTitle ];
	}

	private function getContentHandler( string $model ): ContentHandler {
		return $this->contentHandlerFactory->getContentHandler( $model );
	}

	private function getDefaultContentModel( Title $title, string $role ): string {
		return $this->slotRoleRegistry
			->getRoleHandler( $role )
			->getDefaultModel( $title );
	}

	/**
	 * Data Accounting modification
	 * @return array|null
	 */
	private function handleVerification(): ?array {
		if ( $this->getReader()->isEmptyElement ) {
			return null;
		}
		$verificationInfo = [];
		$normalFields = [
			'domain_id',
			'genesis_hash',
			'rev_id',
			'verification_hash',
			'previous_verification_hash',
			'time_stamp',
			'signature',
			'public_key',
			'wallet_address',
			'verification_context',
			'content_hash',
			'metadata_hash',
			'signature_hash'
		];
		while ( $this->getReader()->read() ) {
			if ( $this->getReader()->nodeType == XMLReader::END_ELEMENT &&
				$this->getReader()->localName == 'verification' ) {
				break;
			}

			$tag = $this->getReader()->localName;

			if ( in_array( $tag, $normalFields ) ) {
				$verificationInfo[$tag] = $this->nodeContents();
			} elseif ( $tag == 'witness' ) {
				$verificationInfo[$tag] = $this->handleWitness();
			}
		}

		return $verificationInfo;
	}

	/**
	 * Data Accounting modification
	 * @param array|null $verificationInfo
	 * @param string $title
	 */
	private static function processVerification( ?array $verificationInfo, string $title ) {
		$table = 'revision_verification';
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );

		if ( $verificationInfo !== null ) {
			$verificationInfo['page_title'] = $title;
			$verificationInfo['source'] = 'imported';
			unset( $verificationInfo["rev_id"] );

			$res = $dbw->select(
				$table,
				[ 'revision_verification_id', 'rev_id', 'page_title', 'source' ],
				[ 'page_title' => $title ],
				__METHOD__,
				[ 'ORDER BY' => 'revision_verification_id' ]
			);
			$last_row = [];
			foreach ( $res as $row ) {
				$last_row = $row;
			}
			if ( empty( $last_row ) ) {
				// Do nothing if empty
				return;
			}

			// Witness-specific
			// TODO move this to processWitness
			if ( isset( $verificationInfo['witness'] ) ) {
				$witnessInfo = $verificationInfo['witness'];
				$structured_merkle_proof = json_decode( $witnessInfo['structured_merkle_proof'], true );
				unset( $witnessInfo['structured_merkle_proof'] );

				//Check if witness_event_verification_hash is already present,
				//if so skip import into witness_events

				$rowWitness = $dbw->selectRow(
					'witness_events',
					[ 'witness_event_id', 'witness_event_verification_hash' ],
					[ 'witness_event_verification_hash' => $witnessInfo['witness_event_verification_hash'] ]
				);
				if ( !$rowWitness ) {
					$witnessInfo['source'] = 'imported';
					$witnessInfo['domain_manifest_title'] = 'N/A';
					$dbw->insert(
						'witness_events',
						$witnessInfo,
					);
					$local_witness_event_id = getMaxWitnessEventId( $dbw );
					if ( $local_witness_event_id === null ) {
						$local_witness_event_id = 1;
					}
				} else {
					$local_witness_event_id = $rowWitness->witness_event_id;
				}

				// Patch revision_verification table to use the local version of
				// witness_event_id instead of from the foreign version.
				$dbw->update(
					'revision_verification',
					[ 'witness_event_id' => $local_witness_event_id ],
					[ 'revision_verification_id' => $last_row->revision_verification_id ],
				);

				// Check if merkle tree proof is present, if so skip, if not
				// import AND attribute to the correct witness_id
				$revision_verification_hash = $verificationInfo['verification_hash'];

				$rowProof = $dbw->selectRow(
					'witness_merkle_tree',
					[ 'witness_event_id' ],
					[
						'left_leaf=\'' . $revision_verification_hash . '\'' .
						' OR right_leaf=\'' . $revision_verification_hash . '\''
					]
				);

				if ( !$rowProof ) {
					$latest_witness_event_id = $dbw->selectRow(
						'witness_events',
						[ 'max(witness_event_id) as witness_event_id' ],
						''
					)->witness_event_id;

					foreach ( $structured_merkle_proof as $row ) {
						$row["witness_event_id"] = $latest_witness_event_id;
						$dbw->insert(
							'witness_merkle_tree',
							$row,
						);
					}
				}

				// This unset is important, otherwise the dbw->update for
				// revision_verification accidentally includes witness.
				unset( $verificationInfo["witness"] );
			}
			// End of witness-specific

			$dbw->update(
				$table,
				$verificationInfo,
				[ 'revision_verification_id' => $last_row->revision_verification_id ],
				__METHOD__
			);
		} else {
			$dbw->delete(
				$table,
				[ 'page_title' => $title ]
			);
		}
	}

	private function handleWitness(): ?array {
		if ( $this->getReader()->isEmptyElement ) {
			return null;
		}

		$witnessInfo = [];
		$normalFields = [
			"domain_id",
			"domain_manifest_title",
			"witness_hash",
			"witness_event_verification_hash",
			"witness_network",
			"smart_contract_address",
			"domain_manifest_genesis_hash",
			"merkle_root",
			"structured_merkle_proof",
			"witness_event_transaction_hash",
			"sender_account_address"
		];
		while ( $this->getReader()->read() ) {
			if ( $this->getReader()->nodeType == XMLReader::END_ELEMENT &&
				$this->getReader()->localName == 'witness' ) {
				break;
			}
			$tag = $this->getReader()->localName;
			if ( in_array( $tag, $normalFields ) ) {
				$witnessInfo[$tag] = $this->nodeContents();
			}
		}
		return $witnessInfo;
	}
}
