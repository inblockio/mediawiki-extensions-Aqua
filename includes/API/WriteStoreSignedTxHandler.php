<?php

namespace DataAccounting\API;

use CommentStore;
use DataAccounting\Content\SignatureContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Storage\PageUpdaterFactory;
use MediaWiki\Storage\RevisionStore;
use MWException;
use RequestContext;
use Title;
use User;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\LoadBalancer;

require_once __DIR__ . "/../ApiUtil.php";
require_once __DIR__ . "/../Util.php";

/*function injectSignatureToPage( $titleString, $walletString, $user ) {
	//Get the article object with $title
	$title = Title::newFromText( $titleString, 0 );
	$page = new WikiPage( $title );
	$pageText = $page->getContent()->getText();

	//Early exit if signature injection is disabled.
	$doInject = MediaWikiServices::getInstance()->getMainConfig()->get( 'DAInjectSignature' );
	if ( !$doInject ) {
		return;
	}

	$anchorString = "";
	$anchorLocation = strpos( $pageText, $anchorString );
	if ( $anchorLocation === false ) {
		$text = $pageText . "<br><br><hr>";
		$text .= "<div >";
		$text .= $anchorString;
		//Adding visual signature
		$text .= "~~~~ <br>";
		$text .= "</div>";
	} else {
		//insert only signature
		$newEntry = "~~~~ <br>";
		$text = substr_replace(
			$pageText,
			$newEntry,
			$anchorLocation + strlen( $anchorString ),
			0
		);
	}
	// We create a new content using the old content, and append $text to it.
	$comment = "Page signed by wallet: " . $walletString;
	editPageContent( $page, $text, $comment, $user );
}*/

class WriteStoreSignedTxHandler extends SimpleHandler {

	/** @var PermissionManager */
	private $permissionManager;
	/** @var LoadBalancer */
	private $lb;
	/** @var WikiPageFactory */
	private $wikiPageFactory;
	/** @var PageUpdaterFactory */
	private $pageUpdaterFactory;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var CommentStore */
	private $commentStore;

	/** @var User */
	private $user;
	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		PermissionManager $permissionManager, LoadBalancer $loadBalancer,
		WikiPageFactory $wikiPageFactory, PageUpdaterFactory $pageUpdaterFactory,
		RevisionStore $revisionStore, CommentStore $commentStore
	) {
		$this->permissionManager = $permissionManager;
		$this->lb = $loadBalancer;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->revisionStore = $revisionStore;
		$this->commentStore = $commentStore;

		// @todo Inject this, when there is a good way to do that
		$this->user = RequestContext::getMain()->getUser();
	}

	/** @inheritDoc */
	public function run() {
		// Expects Revision_ID [Required] Signature[Required], Public
		// Key[Required] and Wallet Address[Required] as inputs; Returns a
		// status for success or failure

		// Only user and sysop have the 'move' right. We choose this so that
		// the DataAccounting extension works as expected even when not run via
		// micro-PKC Docker. As in, it shouldn't depend on the configuration of
		// an external, separate repo.
		if ( !$this->permissionManager->userHasRight( $this->user, 'move' ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'rest-permission-denied-revision' )->plaintextParams(
					'You are not allowed to use the REST API'
				),
				403
			);
		}

		$body = $this->getValidatedBody();
		$rev_id = $body['rev_id'];
		$signature = $body['signature'];
		$public_key = $body['public_key'];
		$wallet_address = $body['wallet_address'];

		// Generate signature_hash
		$signature_hash = getHashSum( $signature . $public_key );

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );

		// Get title of the page via the revision_id
		$row = $dbw->selectRow(
			'revision_verification',
			[ 'page_title' ],
			[ 'rev_id' => $rev_id ],
			__METHOD__
		);
		if ( !$row ) {
			throw new HttpException( "rev_id not found in the database", 404 );
		}

		$title = $row->page_title;

		// Insert signature detail to the page revision
		$dbw->update(
			'revision_verification',
			[
				'signature' => $signature,
				'public_key' => $public_key,
				'wallet_address' => $wallet_address,
				'signature_hash' => $signature_hash,
			],
			[ "rev_id" => $rev_id ],
		);

		$this->storeSignature( $title, $wallet_address );

		return true;
	}

	private function storeSignature( Title $title, $walletAddress ) {
		$wikipage = $this->wikiPageFactory->newFromTitle( $title );
		$updater = $this->pageUpdaterFactory->newPageUpdater( $wikipage, $this->user );
		$lastRevision = $this->revisionStore->getRevisionByTitle( $title );
		$data = [];
		if ( $lastRevision->hasSlot( SignatureContent::SLOT_ROLE_SIGNATURE ) ) {
			$content = $lastRevision->getContent( SignatureContent::SLOT_ROLE_SIGNATURE );
			if ( $content->isValid() ) {
				$data = json_decode( $content->getText(), 1 );
			}
		}
		$data[] = [
			'user' => $walletAddress,
			'timestamp' => \MWTimestamp::now( TS_MW ),
		];
		$content = new SignatureContent( json_encode( $data ) );
		$updater->setContent( SignatureContent::SLOT_ROLE_SIGNATURE, $content );
		$newRevision = $updater->saveRevision(
			$this->commentStore->createComment(
				$this->lb->getConnection( DB_PRIMARY ), "Page signed by wallet: " . $walletAddress
			),
			EDIT_SUPPRESS_RC
		);

		if ( !$newRevision ) {
			throw new MWException( 'Could not store signature data to page' );
		}
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType !== 'application/json' ) {
			throw new HttpException( "Unsupported Content-Type",
				415,
				[ 'content_type' => $contentType ]
			);
		}

		return new JsonBodyValidator( [
			'rev_id' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'signature' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'public_key' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'wallet_address' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		] );
	}
}
