<?php
/**
 * Behaviral description of SpecialPage:WitnessPublisher
 * The SpecialPage prints out the database witness_events in decending order (starting with the latest witness event). If a page manifest was not yet published, the page should hold a publish button instead of the empty field for 'page_witness_transaction_hash'. In this list it is easy to see how many Witness Events have taken place and which receipts in the form of the page_manifests have been generated and whats their page link (they should be clickable to be redireted to the respective page manifest).
 */

namespace MediaWiki\Extension\Example;

use MediaWiki\MediaWikiServices;
use HTMLForm;
use WikiPage;
use Title;
use TextContent;

class SpecialWitnessPublisher extends \SpecialPage {

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		// A special page should at least have a name.
		// We do this by calling the parent class (the SpecialPage class)
		// constructor method with the name as first and only parameter.
		parent::__construct( 'WitnessPublisher' );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub The subpage string argument (if any).
	 *  [[Special:HelloWorld/subpage]].
	 */
	public function execute( $sub ) {
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

        $output = 'Page Manifest / Witness Event ID ' . $witness_event_id . "<br><br>";

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
            $output .= 'Index: ' . $row4->id . ' | Page Title: ' . $row->page_title . ' | Revision: ' . $row->rev_id . ' | Verification Hash: ' . $row3->hash_verification . '<br>';
        }

		$out = $this->getOutput();
		$out->addHTML($output);

	}



	/** @inheritDoc */
	protected function getGroupName() {
		return 'other';
	}
}

/**
class SpecialWitnessPublisher {
    function __construct($name ='Page Manifest Publisher', $restriction ='editinterface', $listed = true ){
    $par = 'blaa';
    }

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		# Get request data from, e.g.
		$param = $request->getText( 'param' );

		# Do stuff
		# ...
		$wikitext = 'Hello world!';
		$output->addWikiTextAsInterface( $wikitext );
	}
} */
