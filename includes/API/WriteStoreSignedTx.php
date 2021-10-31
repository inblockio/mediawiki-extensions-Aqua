<?php

namespace DataAccounting\API;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\MediaWikiServices;

use Title;
use WikitextContent;
use WikiPage;

use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Permissions\PermissionManager;
use RequestContext;
use Wikimedia\Message\MessageValue;


# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once(__DIR__ . "/../ApiUtil.php");
require_once(__DIR__ . "/../Util.php");


function injectSignatureToPage($titleString, $walletString) {
    //Get the article object with $title
    $title = Title::newFromText( $titleString, 0 );
    $page = new WikiPage( $title );
    $pageText = $page->getContent()->getText();

    //Early exit if signature injection is disabled.
    global $wgDAInjectSignature;
    if ( !$wgDAInjectSignature ) {
        return;
    }

    $anchorString = "<div style=\"font-weight:bold;line-height:1.6;\">Data Accounting Signatures</div><div class=\"mw-collapsible-content\">";
    $anchorLocation = strpos($pageText, $anchorString);
    if ( $anchorLocation === false ) {
        $text = $pageText . "<hr>";
        $text .= "<div class=\"toccolours mw-collapsible mw-collapsed\">";
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
            $anchorLocation + strlen($anchorString),
            0
        );
    }
    // We create a new content using the old content, and append $text to it.
    $newContent = new WikitextContent($text);
    $signatureComment = "Page signed by wallet: " . $walletString;
    $page->doEditContent( $newContent,
        $signatureComment );
}

class WriteStoreSignedTx extends SimpleHandler {
	/** @var PermissionManager */
	private $permissionManager;

	/** @var User */
	private $user;

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		PermissionManager $permissionManager
	) {
		$this->permissionManager = $permissionManager;

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
		$signature_hash = getHashSum($signature . $public_key);

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );

		// Get title of the page via the revision_id
		$row = $dbw->selectRow(
			'page_verification',
			[ 'page_title' ],
			[ 'rev_id' => $rev_id ],
			__METHOD__
		);
		if (!$row) {
            throw new HttpException("rev_id not found in the database", 404);
		}

		$title = $row->page_title;

		// Insert signature detail to the page revision
		$dbw->update(
			'page_verification',
			[
				'signature' => $signature,
				'public_key' => $public_key,
				'wallet_address' => $wallet_address,
				'signature_hash' => $signature_hash,
			],
			[ "rev_id" => $rev_id ],
		);

		# Inject signature to the wiki page.
		# See https://github.com/FantasticoFox/DataAccounting/issues/84
		injectSignatureToPage($title, $wallet_address);

		return true;
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
