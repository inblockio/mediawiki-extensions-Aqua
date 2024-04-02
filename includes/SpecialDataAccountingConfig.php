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
use ExtensionRegistry;
use Html;
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

		$out = Html::element( 'i', [], $this->msg( 'da-specialdaconfig-desc' ) );
		$out .= Html::element( 'hr' );
		$out .= Html::element(
			'h2',
			[],
			$this->msg( 'da-specialdaconfig-section-softwareinfo' )->plain()
		);
		$extension = ExtensionRegistry::getInstance()->getAllThings()['DataAccounting'];
		$out .= $this->msg( 'da-specialdaconfig-softwareinfo' )->params( [
			'https://github.com/inblockio',
			$extension['version'],
			ServerInfo::DA_API_VERSION,
			$this->verificationEngine->getDomainId(),
		] )->parse();

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
		$out .= $form->toString();

		$this->getOutput()->addHTML( $out );
	}

	private function makeFrom( array $errors ): array {
		return [
			new Element( [
				'content' => new HtmlSnippet( Html::element(
					'h2',
					[],
					$this->msg( 'da-specialdaconfig-section-signature' )->plain()
				) )
			] ),
			new Element( [
				'content' => new HtmlSnippet( Html::element(
					'i',
					[],
					$this->msg( 'da-specialdaconfig-signature-desc' )->plain()
				) )
			] ),
			new Element( [
				'content' => new HtmlSnippet( Html::element(
					'i',
					[],
					$this->msg( 'da-specialdaconfig-signature-help' )->plain()
				) )
			] ),
			new FieldLayout(
				new CheckboxInputWidget( [
					'name' => 'InjectSignature',
					'selected' => $this->getConfig()->get( 'InjectSignature' ),
					'indeterminate' => false
				] ),
				[
					'label' => $this->msg( 'da-specialdaconfig-signature-checkbox-label' )->plain(),
					'align' => 'left',
				]
			),
			new Element( [
				'content' => new HtmlSnippet( Html::element(
					'h2',
					[],
					$this->msg( 'da-specialdaconfig-section-witnessconfiguration' )->plain()
				) )
			] ),
			new Element( [
				'content' => new HtmlSnippet( Html::rawElement(
					'i',
					[],
					$this->msg( 'da-specialdaconfig-witnessconfiguration-desc' )->parse()
				) )
			] ),
			new Element( [
				'content' => new HtmlSnippet( Html::rawElement(
					'i',
					[],
					$this->msg( 'da-specialdaconfig-witnessconfiguration-help' )->parse()
				) )
			] ),
			new FieldLayout(
				new TextInputWidget( [
					'name' => 'SmartContractAddress',
					'value' => $this->getConfig()->get( 'SmartContractAddress' ),
				] ),
				[
					'label' => $this->msg( 'da-specialdaconfig-witnessconfiguration-smartcontractaddress-label' )->plain(),
					'align' => 'top',
				]
			),
			new FieldLayout(
				new DropdownInputWidget( [
					'name' => 'WitnessNetwork',
					'options' => [
						[
							'label' => $this->msg(
								'da-specialdaconfig-witnessconfiguration-witnessnetwork-option-mainnet'
							)->plain(),
							'data' => 'mainnet'
						], [
							'label' => $this->msg(
								'da-specialdaconfig-witnessconfiguration-witnessnetwork-option-holesky'
							)->plain(),
							'data' => 'holesky'
						], [
							'label' => $this->msg(
								'da-specialdaconfig-witnessconfiguration-witnessnetwork-option-sepolia'
							)->plain(),
							'data' => 'sepolia'
						],
					],
					'value' => $this->getConfig()->get( 'WitnessNetwork' ),
				] ),
				[
					'label' => $this->msg( 'da-specialdaconfig-witnessconfiguration-witnessnetwork-label' )->plain(),
					'align' => 'top',
				]
			),
			new ButtonInputWidget( [
				'label' => $this->msg( 'da-specialdaconfig-save-label' )->plain(),
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
