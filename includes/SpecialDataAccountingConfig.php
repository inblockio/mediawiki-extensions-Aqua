<?php

/**
 * Behavior description of Special Page Data Accounting Config
 * All configuration settings for all modules are set here. Following settings can be adujusted:
 * Module 1: Page Verification
 *          No Settings
 * Module 2: Verify Page History 
 *          No Settings - Is now the external verifier. Settings for the external verifier have been moved into the chrome extension.
 * Module 3: Export / Import of Verified Page History
 *          No Settings
 * Module 4: Witnessing
 *          Set Witness Smart Contract (For SpecialPage:WitnessPublisher)
 *          Set Witness Network (For SpecialPage:WitnessPublisher)
 */
namespace MediaWiki\Extension\Example;

use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;

use HTMLForm;
use PermissionsError;

require_once('Util.php');
require_once('ApiUtil.php');

class SpecialDataAccountingConfig extends \SpecialPage {

	/**
	 * @var PermissionManager
	 */
	private $permManager;

	public function __construct() {
		parent::__construct( 'DataAccountingConfig' );
		$this->permManager = MediaWikiServices::getInstance()->getPermissionManager();
	}

	/**
	 * Show the page
	 * @param string|null $par
	 * @throws PermissionsError
	 */
	public function execute( $par = null ) {
		$this->setHeaders();

		$user = $this->getUser();
		if ( !$this->permManager->userHasRight( $user, 'import' ) ) {
			throw new PermissionsError( 'import' );
		}

		$out = "<i>This page gives you all configuration options for the Media Wikia - Data Accounting Extension Version 1.1</i><hr>";

		$out .= "<br>Project Page with GitHub and Roadmap: https://aqua.inblock.io/index.php/Main_Page<br>";
		$out .= "<br>Your Domain ID is: <b>" . getDomainId() . "</b><br><h1> Module 4: Witness Configuration </h1>";
		$out .= "<i>Configure witness network for witness events to be published and against which witness network (which Blockchain) your historic witness events are checked. </i><hr>";

		$this->getOutput()->addWikiTextAsInterface( $out );
		$this->getOutput()->setPageTitle( 'Data Accounting Configuration' );

		$data_accounting_config = getDataAccountingConfig();
		$formDescriptor = [
			'smartcontractaddress' => [
				'label' => 'Smart Contract Address:', // Label of the field
				'class' => 'HTMLTextField', // Input type
				'default' => $data_accounting_config['smartcontractaddress'],
			],
			'witnessnetwork' => [
				'label' => 'Witness Network:',
				'class' => 'HTMLTextField', // Input type
				'default' => $data_accounting_config['witnessnetwork'],
			],
		];

        $htmlForm = new HTMLForm( $formDescriptor, $this->getContext(), 'daForm' );
        $htmlForm->setSubmitText( 'Save' );
		$htmlForm->setSubmitCallback( [ $this, 'saveWitnessConfig' ] );
		$htmlForm->show();
	}

	public static function saveWitnessConfig( $formData ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );
		setDataAccountingConfig($formData);
	}
}
