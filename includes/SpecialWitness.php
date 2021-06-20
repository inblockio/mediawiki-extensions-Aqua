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

		$output = '';
		foreach( $res as $row ) {
			$output .= 'Page Title: ' . $row->page_title . ' Revision_ID: ' . $row->rev_id . ' hash: ' . $row->hash_verification . "<br>";
		}
		$out = $this->getOutput();
		$out->addHTML($output);
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'other';
	}
}
