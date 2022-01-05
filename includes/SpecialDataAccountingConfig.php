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
use Html;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MutableConfig;
use OOUI\TextInputWidget;
use OOUI\CheckboxInputWidget;
use OOUI\DropdownInputWidget;
use OOUI\ButtonInputWidget;
use OOUI\Element;
use OOUI\HtmlSnippet;
use OOUI\FieldsetLayout;
use OOUI\FormLayout;
use OOUI\FieldLayout;
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
		$errors = [];
		if ( $this->getRequest()->wasPosted() ) {
			$values = $this->getRequest()->getPostValues();
			$errors = $this->save( $values );
		}
		// todo: show possible errors here $errors

		$out = "<i>Configuration for the MediaWiki - Data Accounting Extension</i><hr>";

		$out .= "<h2>Software Info</h2>";
		$out .= "Project GitHub: https://github.com/inblockio";
		$out .= "<br>Data Accounting Version: 2.0.0-alpha";
		$out .= "<br>API Version: " .  ServerInfo::DA_API_VERSION;
		$out .= "<br>Your Domain ID is: <b>" . $this->verificationEngine->getDomainId() . "</b>";
		$this->getOutput()->addWikiTextAsInterface( $out );

		$this->getOutput()->enableOOUI();
		$form = new FormLayout( [
            'action' => $this->getFullTitle()->getFullURL(),
            'method' => 'post',
			'align' => 'left',
            'items' => [
                new FieldsetLayout( [
					'align' => 'left',
                    'items' => $this->makeFrom( $errors )
                ] ),
            ]
        ] );

		$this->getOutput()->addHTML( $form->toString() );
		$this->getOutput()->setPageTitle( 'Data Accounting Configuration' );
	}

	private function makeFrom( array $errors ): array{
		return [
			new Element( [
				'content' => new HtmlSnippet(
					Html::element( 'h2', [], 'Signature' )
				)
			] ),
			new Element( [
				'content' => new HtmlSnippet(
					Html::element( 'i', [], 'Configure behavior of Signature' )
				)
			] ),
			new Element( [
				'content' => new HtmlSnippet(
					Html::element( 'i', [], 'Content Signature adds a visible signature into the page content and write a new revision when signing a page with your wallet.' )
				)
			] ),
			new FieldLayout(
				new CheckboxInputWidget( [
					'name' => 'InjectSignature',
					'selected' => $this->getConfig()->get( 'InjectSignature' ),
					'indeterminate' => false
				] ),
				[
					'label' => 'Configure behavior of Signature:',
					'align' => 'top',
				]
			),
			new Element( [
				'content' => new HtmlSnippet(
					Html::element( 'h2', [], 'Witness Configuration' )
				)
			] ),
			new Element( [
				'content' => new HtmlSnippet(
					Html::element( 'i', [], 'Configure Witness Network and Smart-Contract-Address for [[Special:WitnessPublisher| Domain Snapshot Publisher]]' )
				)
			] ),
			new Element( [
				'content' => new HtmlSnippet(
					Html::element( 'i', [], 'Ensure you\'re generating a [[Special:Witness| Domain Snapshot]] before publishing.' )
				)
			] ),
			new FieldLayout(
				new TextInputWidget( [
					'name' => 'SmartContractAddress',
					'value' => $this->getConfig()->get( 'SmartContractAddress' ),
				] ),
				[
					'label' => 'Smart Contract Address:',
					'align' => 'top',
				]
			),
			new FieldLayout(
				new DropdownInputWidget( [
					'name' => 'WitnessNetwork',
					'options' => [
						[
							'label' => 'Mainnet',
							'data' => 'mainnet'
						], [
							'label' => 'Ropsten',
							'data' => 'ropsten'
						], [
							'label' => 'Kovan',
							'data' => 'kovan'
						], [
							'label' => 'Rinkeby',
							'data' => 'rinkeby'
						], [
							'label' => 'Goerli',
							'data' => 'goerli'
						],
					],
					'value' => $this->getConfig()->get( 'WitnessNetwork' ),
				] ),
				[
					'label' => 'Witness Network:',
					'align' => 'top',
				]
			),
			new ButtonInputWidget( [
				'label' => 'Save',
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
			] ),
		];
	}

	public function getConfig(): MutableConfig {
		return MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'da' );
	}

	private function save( array $values ): array {
		// because checkbox input widgets are not very smart
		$values[ 'InjectSignature' ] = isset( $values[ 'InjectSignature' ] ) ? true : false;

		$errors = [];
		// TODO: validate user input!
		foreach ( $values as $name => $value ) {
			try {
				$this->getConfig()->set( $name, $value );
			} catch ( Exception $e ) {
				// TODO: display errors "saving '$name' has error: {$e->getMessage()}"
				$errors[] = $e->getMessage();
				continue;
			}
		}
		return $errors;
	}
}
