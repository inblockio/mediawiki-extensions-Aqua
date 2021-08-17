<?php
/**
 * Behaviral description of SpecialPage:WitnessPublisher
 * The SpecialPage prints out the database witness_events in decending order (starting with the latest witness event). If a Domain Manifest was not yet published, the page should hold a publish button instead of the empty field for 'page_witness_transaction_hash'. In this list it is easy to see how many Witness Events have taken place and which receipts in the form of the domain_manifests have been generated and whats their page link (they should be clickable to be redireted to the respective Domain Manifest).
 */

namespace MediaWiki\Extension\Example;

use MediaWiki\MediaWikiServices;
use HTMLForm;
use WikiPage;
use Title;
use TextContent;

require_once('Util.php');

// TODO this function is duplicated in SpecialWitness
function hrefifyHash($hash, $prefix = "") {
	return "<a href='" . $prefix . $hash. "'>" . substr($hash, 0, 6) . "..." . substr($hash, -6, 6) . "</a>";
}

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
		$this->setHeaders();
		$this->getOutput()->setPageTitle( 'Domain Manifest Publisher' );

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbw = $lb->getConnectionRef( DB_MASTER );

        /**
         *witness_event_id, domain_id, domain_manifest_title, domain_manifest_verification_hash, merkle_root, witness_event_verification_hash, witness_network, smart_contract_address, witness_event_transaction_hash, sender_account_address     
         **/

        $res = $dbw->select(
            'witness_events',
            [ 'witness_event_id, domain_id, domain_manifest_title, domain_manifest_verification_hash, merkle_root, witness_event_verification_hash, witness_network, smart_contract_address, witness_event_transaction_hash, sender_account_address'],
            '',
            __METHOD__,
            [ 'ORDER BY' => ' witness_event_id DESC' ]
        );

        $output = 'The Domain Manifest Publisher shows you a list of all Domain Manifests. You can publish a Domain Manifest from here to the Ethereum Network to timestamp all page verification hashes and the related page revisions included in the manifest. After publishing the manifest, this will be written into the page_verification data and included into the page history.<br><br>';
            $out = $this->getOutput();
            $out->addHTML($output);
 
        $output = '<table>';
        $output .= <<<EOD
            <tr>
                <th>Witness Event</th>
                <th>Domain Manifest Title</th>
                <th>Domain ID</th>
                <th>Verification Hash of DM</th>
                <th>Merkle Root</th>
                <th>Verification Hash of WE</th>
                <th>Publishing status</th>
            </tr>
        EOD;
        foreach ($res as $row) {
            $hrefMVH = hrefifyHash($row->domain_manifest_verification_hash);
            $hrefMerkleRoot = hrefifyHash($row->merkle_root);
            $hrefWEVH = hrefifyHash($row->witness_event_verification_hash);
            // Color taken from https://www.schemecolor.com/warm-autumn-2.php
            // #B33030 is Chinese Orange
            // #B1C97F is Sage
            
            $my_domain_id = getDomainId();
            if ($my_domain_id != $row->domain_id) {
                $publishingStatus = '<th style="background-color:#DDDDDD">Imported</th>';
            } else {
                if ($row->witness_event_transaction_hash == 'PUBLISH WITNESS HASH TO BLOCKCHAIN TO POPULATE') {
                    $publishingStatus = '<th style="background-color:#F27049"><button type="button" class="publish-domain-manifest" id="' . $row->witness_event_id . '">Publish!</button></th>';
                } else {
                    $publishingStatus = '<th style="background-color:#B1C97F">' . hrefifyHash($row->witness_event_transaction_hash, "https://goerli.etherscan.io/tx/") . '</th>';
                }
            };

            $linkedDomainManifest = '<a href=\'/index.php/' . $row->domain_manifest_title . '\'>' . $row->domain_manifest_title . '</a>';

            $output .= <<<EOD
                <tr>
                    <th>{$row->witness_event_id}</th>
                    <th>{$linkedDomainManifest}</th>
                    <th>{$row->domain_id}</th>
                    <th>$hrefMVH</th>
                    <th>$hrefMerkleRoot</th>
                    <th>$hrefWEVH</th>
                    $publishingStatus
                </tr>
            EOD;
            #$output .= '<br>========== Witness Event Publishing Data =========<br> Witness Network: ' . $row->witness_network . '<br> Smart Contract Address: ' . $row->smart_contract_address . '<br> Sender Account Address: ' . $row->sender_account_address . '<br>=========== END OF Witness Event ===========<br><br>';

            #$out = $this->getOutput();
        }
        $output .= '</table>';
        $out->addHTML($output);
    }
    /** @inheritDoc */
    protected function getGroupName() {
        return 'other';
    }
}
