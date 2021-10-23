<?php

namespace MediaWiki\Extension\Example;

use \Exception as Exception;

use MediaWiki\Rest\SimpleHandler;
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

require_once("ApiUtil.php");
require_once("Util.php");

function selectToArray($db, $table, $col, $conds) {
    $out = array();
    $res = $db->select(
        $table,
        [$col],
        $conds,
    );
    foreach ($res as $row) {
        array_push($out, $row->{$col});
    }
    return $out;
}

function injectSignature($titleString, $walletString) {
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

// TODO move to Util.php
function addReceiptToDomainManifest($user, $witness_event_id, $db) {
    $row = $db->selectRow(
        'witness_events',
        [
            "domain_id",
            "domain_manifest_title",
            "domain_manifest_verification_hash",
            "merkle_root",
            "witness_event_verification_hash",
            "witness_network",
            "smart_contract_address",
            "witness_event_transaction_hash",
            "sender_account_address",
        ],
        [ 'witness_event_id' => $witness_event_id ]
    );
    if (!$row) {
        throw new Exception("Witness event data is missing.");
    }

    $dm = "DomainManifest $witness_event_id";
    if ( "Data Accounting:$dm" !== $row->domain_manifest_title) {
        throw new Exception("Domain manifest title is inconsistent.");
    }

    //6942 is custom namespace. See namespace definition in extension.json.
    $tentativeTitle = Title::newFromText( $dm, 6942 );
    $page = new WikiPage( $tentativeTitle );
    $text = "\n<h1> Witness Event Publishing Data </h1>\n";
    $text .= "<p> This means, that the Witness Event Verification Hash has been written to a Witness Network and has been Timestamped.\n";

    $text .= "* Witness Event: " . $witness_event_id . "\n";
    $text .= "* Domain ID: " . $row->domain_id . "\n";
    // We don't include witness hash.
    $text .= "* Page Domain Manifest verification Hash: " . $row->domain_manifest_verification_hash . "\n";
    $text .= "* Merkle Root: " . $row->merkle_root . "\n";
    $text .= "* Witness Event Verification Hash: " . $row->witness_event_verification_hash . "\n";
    $text .= "* Witness Network: " . $row->witness_network . "\n";
    $text .= "* Smart Contract Address: " . $row->smart_contract_address . "\n";
    $text .= "* Transaction Hash: " . $row->witness_event_transaction_hash . "\n";
    $text .= "* Sender Account Address: " . $row->sender_account_address . "\n";
    // We don't include source.

    $pageText = $page->getContent()->getText();
    // We create a new content using the old content, and append $text to it.
    $newContent = new WikitextContent($pageText . $text);
    $page->doEditContent( $newContent,
        "Domain Manifest witnessed" );

    // Rename from tentative title to final title.
    $domainManifestVH = $row->domain_manifest_verification_hash;
    $finalTitle = Title::newFromText( "DomainManifest:$domainManifestVH", 6942 );
    $mp = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage( $tentativeTitle, $finalTitle );
    $reason = "Changed from tentative title to final title";
    $createRedirect = false;
    $mp->move( $user, $reason, $createRedirect );
    $db->update(
        'witness_events',
        ['domain_manifest_title' => $finalTitle->getPrefixedText() ],
        [
            'domain_manifest_title' => $tentativeTitle->getPrefixedText(),
            'witness_event_id' => $witness_event_id,
        ],
    );
}

/**
 * Extension:DataAccounting Standard Rest API
 */
class APIWrite extends SimpleHandler {

	/** @var PermissionManager */
	private $permissionManager;

	/** @var User */
	private $user;

    private const VALID_ACTIONS = [ 
        'store_signed_tx', 
        'store_witness_tx', 
    ];

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
    public function run( $action ) {
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

        $params = $this->getValidatedParams();
        $var1 = $params['var1'];
        $var2 = $params['var2'] ?? null;
        $var3 = $params['var3'] ?? null;
        $var4 = $params['var4'] ?? null;
        switch ( $action ) {
   
            #Expects Revision_ID [Required] Signature[Required], Public Key[Required] and Wallet Address[Required] as inputs; Returns a status for success or failure
        case 'store_signed_tx':
            /** include functionality to write to database. 
             * See https://www.mediawiki.org/wiki/Manual:Database_access */
            if ($var2 === null) {
                return "var2 is not specified";
            }
            if ($var3 === null) {
                return "var3 is not specified";
            }
            if ($var4 === null) {
                return "var4 is not specified";
            }
            $rev_id = $var1;
            $signature = $var2;
            $public_key = $var3;
            $wallet_address = $var4;

            //Generate signature_hash
            $signature_hash = getHashSum($signature . $public_key);

            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbw = $lb->getConnectionRef( DB_MASTER );
            $table = 'page_verification';

            $dbw->update( $table, 
                [
                    'signature' => $signature, 
                    'public_key' => $public_key, 
                    'wallet_address' => $wallet_address,
                    'signature_hash' => $signature_hash,
                ], 
                ["rev_id" => $rev_id]);

            #Get title of the page via the revision_id 
            #$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
 
            //TODO Check if line row exists to catch error
            $dbr = $lb->getConnectionRef( DB_REPLICA );
            $row = $dbr->selectRow(
                'page_verification',
                [ 'rev_id','page_title','page_id' ],
                'rev_id = '.$var1,
                __METHOD__
            );
            
            $title = $row->page_title;  

            #Add functionality required for #84 visual signatures in page content
            injectSignature($title,$wallet_address);
 
            //TODO return proper API status code
            return ( "Successfully stored Data for Revision[{$var1}] in Database! Data: Signature[{$var2}], Public_Key[{$var3}] and Wallet_Address[{$var4}] "  );

            #Expects all required input for the page_witness database: Transaction Hash, Sender Address, List of Pages with witnessed revision
        case 'store_witness_tx':
            /** include functionality to write to database. 
             * See https://www.mediawiki.org/wiki/Manual:Database_access */
            if ($var1 == null) {
                return "var1 (/witness_event_id) is not specified but expected";                
            }
            if ($var2 === null) {
                return "var2 (account_address) is not specified but expected";
            }
            if ($var3 === null) {
                return "var3 (transaction_hash) is not specified but expected";
            }

            //Redeclaration
            $witness_event_id = $var1;
            $account_address = $var2;
            $transaction_hash = $var3;

            $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
            $dbw = $lb->getConnectionRef( DB_MASTER );

            $table = 'witness_events';

            $verification_hashes = selectToArray(
                $dbw,
                'witness_page',
                'page_verification_hash',
                [ 'witness_event_id' => $witness_event_id ]
            );

            // If witness ID exists, don't write witness_id, if it does not
            // exist update with witness id as oldest witness event has biggest
            // value (proof of existence)
            foreach ($verification_hashes as $vh) {
                $row = $dbw->selectRow(
                    'page_verification',
                    [ 'witness_event_id' ],
                    [ 'verification_hash' => $vh ]
                );
                if (is_null($row->witness_event_id)) {
                    $dbw->update(
                        'page_verification',
                        [ 'witness_event_id' => $witness_event_id ],
                        [ 'verification_hash' => $vh ]
                    );
                }
            }

            // Generate the witness_hash
            $row = $dbw->selectRow(
                'witness_events',
                [ 'domain_manifest_verification_hash', 'merkle_root', 'witness_network' ],
                [ 'witness_event_id' => $witness_event_id ]
            );
            $witness_hash = getHashSum(
                $row->domain_manifest_verification_hash .
                $row->merkle_root .
                $row->witness_network .
                $transaction_hash
            );
            /** write data to database */
            // Write the witness_hash into the witness_events table
            $dbw->update( $table,
                [
                    'sender_account_address' => $account_address,
                    'witness_event_transaction_hash' => $transaction_hash,
                    'source' => 'default',
                    'witness_hash' => $witness_hash,
                ],
                "witness_event_id = $witness_event_id");

            // Patch witness data into domain manifest page.
            $dbw->update(
                'page_verification',
                [
                    'witness_event_id' => $witness_event_id,
                ],
                ["verification_hash" => $row->domain_manifest_verification_hash]
            );

            // Add receipt to the domain manifest
            addReceiptToDomainManifest($this->user, $witness_event_id, $dbw);

            return ( "Successfully stored data for witness_event_id[{$witness_event_id}] in Database[$table]! Data: account_address[{$account_address}], witness_event_transaction_hash[{$transaction_hash}]"  );

        default:
            //TODO Return correct error code https://www.mediawiki.org/wiki/API:REST_API/Reference#PHP_3
            return 'ERROR: Invalid action';
        }
    }

    /** @inheritDoc */
    public function needsWriteAccess() {
        return false;
    }

    /** @inheritDoc */
    public function getParamSettings() {
        return [
            'action' => [
                self::PARAM_SOURCE => 'path',
                ParamValidator::PARAM_TYPE => self::VALID_ACTIONS,
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'var1' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'var2' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'var3' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
            'var4' => [
                self::PARAM_SOURCE => 'query',
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false,
            ],
        ];
    }
}

