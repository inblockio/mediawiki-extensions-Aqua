<?php
/**
 * This Special Page is used to Generate Page Manifests for Witness events
 * Input is the list of all verified pages with the latest revision id and verification hashes which are stored in the table page_list which are printed in section 1 of the Page Manifest. This is used as input for generating and populating the table witness_merkle_tree.
 * The witness_merkle_tree is printed out on section 2 of the Page Manifest.
 * Output is a Page Manifest #N_ID as well as a redirect link to the SpecialPage:WitnessPublisher
 *
 * @file
 */

namespace MediaWiki\Extension\Example;

use MediaWiki\MediaWikiServices;
use HTMLForm;
use WikiPage;
use Title;
use TextContent;

use Rht\Merkle\FixedSizeTree;

require 'vendor/autoload.php';

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);


class HtmlContent extends TextContent {
	protected function getHtml() {
		return $this->getText();
	}
}

function tree_pprint($layers, $out = "", $prefix = "└─ ", $level = 0, $is_last = true) {
    # The default prefix is for level 0
    $length = count($layers);
    $idx = 1;
    if ($level == 0) {
        $child_padding = "";
    } else {
        $child_padding = " ";
    }
    foreach ($layers as $key => $value) {
        $is_last = $idx == $length;
        if ($level == 0) {
            $last_element = "";
        } elseif ($is_last) {
            $last_element = "  └─ ";
        } else {
            $last_element = "  ├─ ";
        }

		if ($level == 0) {
			$out .= "Merkle root: " . $key . "\n";
		} else {
			$formatted_key = "<a href='" . $key. "'>" . substr($key, 0, 6) . "..." . substr($key, -6, 6) . "</a>";
			$out .= $child_padding . $prefix . $last_element . $formatted_key . "\n";
		}
        if (!is_null($value)) {
            if ($level == 0) {
                $new_prefix = "";
            } else {
                if ($is_last) {
                    $new_prefix = $prefix . "   ";
                } else {
                    $new_prefix = $prefix . "  │";
                }
            }
            $out .= tree_pprint($value, "", $new_prefix, $level + 1, $is_last);
        }
        $idx += 1;
    }
    return $out;
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

		$formDescriptor_scw = [
			'smartcontractaddress' => [
				'label' => 'Witness Smart Contract Address', // Label of the field
				'class' => 'HTMLTextField', // Input type
			]
		];
        
		$formDescriptor_network = [
			'smartcontractaddress' => [
				'label' => 'Witness Network', // Label of the field
				'class' => 'HTMLTextField', // Input type
			]
		];

		$formDescriptor = [
			'pagetitle' => [
				'label' => 'Page Title', // Label of the field
				'class' => 'HTMLTextField', // Input type
			]
		];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext(), 'generatePageManifest' );
		$htmlForm->setSubmitText( 'Generate Page Manifest' );
		$htmlForm->setSubmitCallback( [ $this, 'generatePageManifest' ] );
		$htmlForm->show();

		$out = $this->getOutput();
		$out->setPageTitle( 'Witness' );
	}

	public static function generatePageManifest( $formData ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );

        $res = $dbw->select(
			'page_verification',
			[ 'page_title', 'max(rev_id) as rev_id' ],
			'',
			__METHOD__,
			[ 'GROUP BY' => 'page_title']
		);

        $row2 = $dbw->selectRow(
            'witness_page',
            [ 'max(witness_event_id) as witness_event_id' ],
            '',
            __METHOD__,
        );

        $witness_event_id = $row2->witness_event_id + 1;

        foreach ( $res as $row ) {
            $row3 = $dbw->selectRow(
                'page_verification',
                ['hash_verification', 'domain_id' ],
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

            // echo "Witness Event ID is " . $witness_event_id . "Table successfully populated. <br>";
            // echo "INSERTED page title " . $row->page_title . " with rev_id " . $row->rev_id . " hash_verification " . $row2->hash_verification . "<br>"; 
        }


        return;

/**
        $int = 0;
		$output = '';
        $rev_id = [];
		$verification_hashes = [];
		foreach( $res as $row ) {
            $titlearray[$int] =  $row->page_title;
            $verification_hashes[$int] =  $row->hash_verification;
            $rev_id[$int] =  $row->rev_id;

            $output .= 'Index: ' . $int . '<br> Title: ' . $titlearray[$int] . '<br> rev_id: ' . $rev_id[$int] . '<br> Verification_Hash: ' . $verification_hashes[$int] . '<br><br>';
            $int == $int++;

		}
 */
		$hasher = function ($data) {
			return hash('sha3-512', $data, false);
		};

		$tree = new FixedSizeTree(count($verification_hashes), $hasher, NULL, true);
        for ($i = 0; $i < count($verification_hashes); $i++) {
			$tree->set($i, $verification_hashes[$i]);
		}

		$out = $this->getOutput();
		$out->addHTML($output);

		$title = Title::newFromText( "Page Manifest" );
		$page = new WikiPage( $title );
		$merkleTreeText = '<br><pre>' . tree_pprint($tree->getLayersAsObject()) . '</pre>';
		$pageContent = new HtmlContent($merkleTreeText);
		$page->doEditContent( $pageContent,
			"Page created automatically by [[Special:Witness]]" );

		$out->addHTML($merkleTreeText);
		$out->addWikiTextAsContent("<br> Visit [[Page Manifest]] to see the Merkle proof.");
        return true;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'other';
	}
}
