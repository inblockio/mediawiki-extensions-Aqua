<?php
/**
 * HelloWorld Special page.
 *
 * @file
 */

namespace MediaWiki\Extension\Example;

use HTMLForm;

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

		$formDescriptor = [];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext(), 'generatePageManifest' );
		$htmlForm->setSubmitText( 'Generate Page Manifest' );
		$htmlForm->setSubmitCallback( [ $this, 'generatePageManifest' ] );
		$htmlForm->setMethod( 'get' );
		$htmlForm->show();

		$out = $this->getOutput();
		$out->setPageTitle( 'Witness' );
	}

	public static function generatePageManifest( $formData ) {
		return false;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'other';
	}
}
