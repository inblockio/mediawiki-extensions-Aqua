<?php
/**
 * This Special Page is used to Generate Domain Manifests for Witness events
 * Input is the list of all verified pages with the latest revision id and verification hashes which are stored in the table page_list which are printed in section 1 of the Domain Manifest. This is used as input for generating and populating the table witness_merkle_tree.
 * The witness_merkle_tree is printed out on section 2 of the Domain Manifest.
 * Output is a Domain Manifest # as well as a redirect link to the SpecialPage:WitnessPublisher
 *
 * @file
 */

namespace MediaWiki\Extension\Example;

use MediaWiki\MediaWikiServices;
use HTMLForm;
use WikiPage;
use Title;
use WikitextContent;

use Rht\Merkle\FixedSizeTree;

require 'vendor/autoload.php';

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once('Util.php');
require_once('ApiUtil.php');

//class HtmlContent extends TextContent {
//	protected function getHtml() {
//		return $this->getText();
//	}
//}

function shortenHash($hash) {
	return substr($hash, 0, 6) . "..." . substr($hash, -6, 6);
}

function hrefifyHashW($hash) {
	return "<a href='" . $hash . "'>" . shortenHash($hash) . "</a>";
}

function wikilinkifyHash($hash) {
	$shortened = shortenHash($hash);
	return "[http://$hash $shortened]";
}

function tree_pprint($do_wikitext, $layers, $out = "", $prefix = "└─ ", $level = 0, $is_last = true) {
    # The default prefix is for level 0
    $length = count($layers);
    $idx = 1;
    foreach ($layers as $key => $value) {
        $is_last = $idx == $length;
		if ($level == 0) {
			$out .= "Merkle root: " . $key . "\n";
		} else {
			$formatted_key = $do_wikitext ? wikilinkifyHash($key) : hrefifyHashW($key);
			$glyph = $is_last ? "  └─ ": "  ├─ ";
			$out .= " " . $prefix . $glyph . $formatted_key . "\n";
		}
        if (!is_null($value)) {
            if ($level == 0) {
                $new_prefix = "";
            } else {
				$new_prefix = $prefix . ($is_last ? "   ": "  │");
            }
            $out .= tree_pprint($do_wikitext, $value, "", $new_prefix, $level + 1, $is_last);
        }
        $idx += 1;
    }
    return $out;
}

function storeMerkleTree($dbw, $witness_event_id, $treeLayers, $depth = 0) {
	foreach ($treeLayers as $parent => $children) {
		if (is_null($children)) {
			continue;
		}
		$children_keys = array_keys($children);
		$dbw->insert(
			"witness_merkle_tree",
			[
				"witness_event_id" => $witness_event_id,
				"depth" => $depth,
				"left_leaf" => $children_keys[0],
				"right_leaf" => (count($children_keys) > 1) ? $children_keys[1]: null,
				"successor" => $parent,
			]
		);
		storeMerkleTree($dbw, $witness_event_id, $children, $depth + 1);
	}
}

class SpecialWitness extends \SpecialPage {

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		// A special page should at least have a name.
		// We do this by calling the parent class (the SpecialPage class)
		// constructor method with the name as first and only parameter.
		parent::__construct( 'Witness' );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub The subpage string argument (if any).
	 *  [[Special:SignMessage/subpage]].
	 */
	public function execute( $sub ) {
		$this->setHeaders();

		$htmlForm = new HTMLForm( [], $this->getContext(), 'daDomainManifest' );
		$htmlForm->setSubmitText( 'Generate Domain Manifest' );
		$htmlForm->setSubmitCallback( [ $this, 'generateDomainManifest' ] );
		$htmlForm->show();

		$out = $this->getOutput();
		$out->setPageTitle( 'Domain Manifest Generator' );
	}

	public function generateDomainManifest( $formData ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );

        $res = $dbw->select(
			'page_verification',
			[ 'page_title', 'max(rev_id) as rev_id' ],
			"page_title NOT LIKE 'Data Accounting:%'",
			__METHOD__,
			[ 'GROUP BY' => 'page_title']
		);


		$old_max_witness_event_id = getMaxWitnessEventId($dbw);
		// Set to 0 if null.
		$old_max_witness_event_id = is_null($old_max_witness_event_id) ? 0 : $old_max_witness_event_id;
        $witness_event_id = $old_max_witness_event_id + 1;

        $output = 'Domain Manifest ' . $witness_event_id . ' is a summary of all verified pages within your domain and is used to generate a merkle tree to witness and timestamp them simultanously. Use the [[Special:WitnessPublisher | Domain Manifest Publisher]] to publish your generated Domain Manifest to your preffered witness network.' . '<br><br>';

		// For the table
		$output .= <<<EOD

			{| class="wikitable"
			|-
			! Index
			! Page Title
			! Revision
			! Verification Hash

		EOD;

        $verification_hashes = [];
        foreach ( $res as $row ) {
            $row3 = $dbw->selectRow(
                'page_verification',
                [ 'hash_verification', 'domain_id' ],
                ['rev_id' => $row->rev_id],
                __METHOD__,
            );

            $dbw->insert( 'witness_page', 
                [
                    'witness_event_id' => $witness_event_id,
                    'domain_id' => $row3->domain_id,
                    'page_title' => $row->page_title, 
                    'rev_id' => $row->rev_id,
                    'page_verification_hash' => $row3->hash_verification,
                ], 
                "");

            //TODO Rht:Optimize this!
            $row4 = $dbw->selectRow(
                'witness_page',
                [ 'id'],
                ['page_title' => $row->page_title, 'witness_event_id' => $witness_event_id],
                __METHOD__,
            );

            array_push($verification_hashes, $row3->hash_verification);

			$output .= "|-\n|" . $row4->id . "\n| [[" . $row->page_title . "]]\n|" . $row->rev_id . "\n|" . wikilinkifyHash($row3->hash_verification) . "\n";
        }
	    $output .= "|}\n";

		$hasher = function ($data) {
			return hash('sha3-512', $data, false);
		};

		if (empty($verification_hashes)) {
			$out = $this->getOutput();
			$out->addHTML('No verified page revisions available. Create a new page revision first.');
			return true;
		}
		$tree = new FixedSizeTree(count($verification_hashes), $hasher, NULL, true);
        for ($i = 0; $i < count($verification_hashes); $i++) {
			$tree->set($i, $verification_hashes[$i]);
		}
		$treeLayers = $tree->getLayersAsObject();

		$out = $this->getOutput();
		$out->addWikiTextAsContent($output);

		// Store the Merkle tree in the DB
		storeMerkleTree($dbw, $witness_event_id, $treeLayers);

        //Generate the Domain Manifest as a new page
        $construct_title =  'Domain Manifest ' . $witness_event_id;
		//6942 is custom namespace. See namespace definition in extension.json.
        $title = Title::newFromText( $construct_title, 6942 );
		$page = new WikiPage( $title );
		$merkleTreeHtmlText = '<br><pre>' . tree_pprint(false, $treeLayers) . '</pre>';
		$merkleTreeWikitext = tree_pprint(true, $treeLayers);
		$pageContent = new WikitextContent($output . '<br>' . $merkleTreeWikitext);
		$page->doEditContent( $pageContent,
			"Page created automatically by [[Special:Witness]]" );

        //Get the Domain Manifest verification hash
        $domain_manifest_verification_hash = $dbw->selectRow(
                'page_verification',
                [ 'hash_verification'],
                ['page_title' => $title],
                __METHOD__,
        );

        //Write results into the witness_events DB
        $merkle_root = array_keys($treeLayers)[0];

		// Check if $witness_event_id is already present in the witness_events table
		$row = $dbw->selectRow(
			'witness_events',
			[ 'witness_event_id' ],
			[ 'witness_event_id' => $witness_event_id ]
		);
		if (!$row) {
			// If witness_events table doesn't have it, then insert.
			$data_accounting_config = getDataAccountingConfig();
			$dbw->insert( 'witness_events',
				[
					'witness_event_id' => $witness_event_id,
					'domain_id' => getDomainId(),
					'domain_manifest_title' => $title,
					'domain_manifest_verification_hash' => $domain_manifest_verification_hash->hash_verification,
					'merkle_root' => $merkle_root,
					'witness_event_verification_hash' => getHashSum($domain_manifest_verification_hash->hash_verification . $merkle_root),
					'smart_contract_address' => $data_accounting_config['smartcontractaddress'],
					'witness_network' => $data_accounting_config['witnessnetwork'],
				],
				"");
		}

		$out->addHTML($merkleTreeHtmlText);
		$out->addWikiTextAsContent("<br> Visit [[$title]] to see the Merkle proof.");
        return true;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'other';
	}
}
