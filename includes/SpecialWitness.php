<?php
/**
 * HelloWorld Special page.
 *
 * @file
 */

namespace MediaWiki\Extension\Example;

use MediaWiki\MediaWikiServices;
use HTMLForm;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

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
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$res = $dbr->select(
			'page_verification',
			[ 'MAX(rev_id) as rev_id', 'page_title', 'hash_verification' ],
			'',
			__METHOD__,
			[ 'GROUP BY' => 'page_title']
		);

        function getHashSum($inputStr) {
            return hash("sha3-512", $inputStr);
        }

        $int = 0;
		$output = '';
        $multiarray = [];
		foreach( $res as $row ) {
			//$output .= 'Page Title: ' . $row->page_title . ' Revision_ID: ' . $row->rev_id . ' hash: ' . $row->hash_verification . 'counter: ' .  $int . 'array: ' . $myarray[$int] . "<br>";

			$multiarray[$int] = 'Index: ' . $int . '<br> Page Title: ' . $row->page_title . '<br> Revision_ID: ' . $row->rev_id . '<br> Verification_Hash: ' . $row->hash_verification . "<br><br>";
            $titlearray[$int] =  $row->page_title;
            $verificationarray[$int] =  $row->hash_verification;

            $output .= 'Index: ' . $int . '<br> Title: ' . $titlearray[$int] . '<br> Verification_Hash: ' . $verificationarray[$int] . '<br><br>';
            $int == $int++;

		}
		$out = $this->getOutput();
		$out->addHTML($output . '<br> Verification_Hash 1: ' . $verificationarray[0] . '<br> Verification_Hash 2:' . $verificationarray[1] . 'Verification_Hash 1 + 2: ' . getHashSum($verificationarray[0].$verificationarray[1]));
		//$out->addHTML($output . '<br> Verification_Hash 1: ' . $verificationarray[0] . '<br> Verification_Hash 2:' . $verificationarray[1] . 'Verification_Hash 1 + 2: ' . getHashSum($verificationarray[0].$verificationarray[1]));
        return true;

        /**
         * {| class="wikitable"
         |+ Caption text
         |-
         * ! Domain ID !! Page (File) Name !! Page ID !! Revision ID !! Timestamp !! Verification Hash !! Hash Round N !! Root Hash
         |-
         |44b63df223||Agreement||34||341||20210606083342||5946a7dddf823aacbecfe3835f461aa3dc642663ca9bca2fa4dfcd30a87c339b77804d37063a8648580663634eabeb9b1c942b98c8c22eb7e94cc0d8da1a9cc2||rowspan="2"|0x04fbcf89b661e09a19a6bd58cf8ea8b46ff52d1fe019bf6649016be4ffcbe173932b6033c37bdd803e19f2782974251708e0ca26fa8844f19bcaa7b1578f791768||rowspan="4"|b144fdbb863e84eaa72bc05b4fc775ef1459ea2b4d481f334aadd0a7f46c198f205008b2a15e7802dd46f259ffe7de14aeb7a65c763f8b930d73e023947e0934
         |-
         |44b63df223||Signed_Document||64|| 536 ||20210606173256||0x817e980a57988ae9e1195296f662fbaee9ad2a8d84cb1e4e9c1a9ac1a193e0700d4807d4ebc6913b1ed55da2b50c915ca0cfcfde8cc620660416abe03fe70ea21b
         |-
         |44b63df223||Drivers_License||97|| 573 ||20210611081749||bd2e74a5f47cdd4593b958b93461905f8661c221a722ff84992676de9c98bf8bfa6f8988f9579e6e1d7dee2e5056758047cc5d402dd68ce9bd65cbc0b5622bff||rowspan="2"|5a2a051c20059f4032eb8ada6734510a4de949300d6041c11de9333a3bef1bbd6c78c09ab20648b4f23de4e95637e3b4692552387624d25f26610e6929bcf7b8
         |-
         |e77338b35c||Working_Contract||73||584||20210611081343||2fc0aa4693e2bf9469f7a874bd9380fc75f0f811a23b12b474d3b774a71ad9ca2951492a90e2dc838dbc1ee3070c1c28dea316e27c92ee4b1bfab32349d6ea61
         |}*/
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'other';
	}
}
