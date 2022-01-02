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

namespace DataAccounting;

use DataAccounting\Verification\VerificationEngine;
use Exception;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MutableConfig;
use PermissionsError;
use SpecialPage;

class SpecialDataAccountingConfig extends SpecialPage {

	private PermissionManager $permManager;

	private VerificationEngine $verificationEngine;

	public function __construct( PermissionManager $permManager,
		VerificationEngine $verificationEngine ) {
		parent::__construct( 'DataAccountingConfig' );
		$this->permManager = $permManager;
		$this->verificationEngine = $verificationEngine;
	}

	/**
	 * Show the page
	 *
	 * @param string|null $par
	 *
	 * @throws PermissionsError
	 */
	public function execute( $par ) {
		$this->setHeaders();

		$user = $this->getUser();
		if ( !$this->permManager->userHasRight( $user, 'import' ) ) {
			throw new PermissionsError( 'import' );
		}

		$out = "<i>Configuration for the MediaWiki - Data Accounting Extension</i><hr>";

		$out .= "<h2>Software Info</h2>";
		$out .= "Project GitHub: https://github.com/inblockio";
		$out .= "<br>Data Accounting Version: 2.0.0-alpha";
		$out .= "<br>API Version: " .  ServerInfo::DA_API_VERSION;
		$out .= "<br>Your Domain ID is: <b>" . $this->verificationEngine->getDomainId() . "</b>";

		$out .= "<hr><h2>Signature</h2>";
		$out .= "<i>Configure behavior of Signature</i>";
		$out .= "<br><i>Content Signature adds a visible signature into the page content and write a new revision when signing a page with your wallet.</i>";
		$out .= "<br><b>Content Signature:</b> I'M a CHECKBOX";

		$out .=	"<hr><h2> Witness Configuration </h2>";
		$out .= "<i>Configure Witness Network and Smart-Contract-Address for [[Special:WitnessPublisher| Domain Manifest Publisher]]";
		$out .= "<br><i>Ensure you're generating a [[Special:Witness| Domain Manifest]] before publishing.";

		$this->getOutput()->addWikiTextAsInterface( $out );
		$this->getOutput()->setPageTitle( 'Data Accounting Configuration' );

		$witnessNetworks = [
			'mainnet' => 'mainnet',
			'ropsten' => 'ropsten',
			'kovan' => 'kovan',
			'rinkeby' => 'rinkeby',
			'goerli' => 'goerli',
		];

		$formDescriptor = [
			'SmartContractAddress' => [
				'label' => 'Smart Contract Address:', // Label of the field
				'type' => 'text', // Input type
				'default' => $this->getConfig()->get( 'SmartContractAddress' ),
			],
			'WitnessNetwork' => [
				'label' => 'Witness Network:',
				'type' => 'select', // Input type
				'default' => $this->getConfig()->get( 'WitnessNetwork' ),
				'options' => $witnessNetworks,
			],
		];

		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext(), 'daForm' );
		$htmlForm->setSubmitText( 'Save' );
		$htmlForm->setSubmitCallback( function( array $formData ) {
			$res = $this->save( $formData );
			if ( $res === true ) {
				// else the form would disappear
				return false;
			}
			return $res;
		} );
		$htmlForm->show();
	}

	public function getConfig(): MutableConfig {
		return MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'da' );
	}

	/**
	 * @see: HTMLForm::trySubmit
	 * @param array $formData
	 * @return bool|string|array|Status
	 *     - Bool true or a good Status object indicates success,
	 *     - Bool false indicates no submission was attempted,
	 *     - Anything else indicates failure. The value may be a fatal Status
	 *       object, an HTML string, or an array of arrays (message keys and
	 *       params) or strings (message keys)
	 */
	private function save( array $formData ) {
		$errors = [];
		// TODO: validate user input!
		foreach ( $formData as $name => $value ) {
			try {
				$this->getConfig()->set( $name, $value );
			} catch ( Exception $e ) {
				// TODO: display errors "saving '$name' has error: {$e->getMessage()}"
				$errors[] = $e->getMessage();
				continue;
			}
		}
		return empty( $errors ) ? true : $errors;
	}
}
