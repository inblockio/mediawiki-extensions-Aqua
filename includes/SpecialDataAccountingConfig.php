<php?

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

        $htmlForm = new HTMLForm( $formDescriptor_scw, $this->getContext(), 'savewitnesssc' );
        $htmlForm->setSubmitText( 'Save Witness Smart Contract Address' );
		$htmlForm->setSubmitCallback( [ $this, 'SaveWitnessSmartContractAddress' ] );
		$htmlForm->show();

		$htmlForm = new HTMLForm( $formDescriptor_network, $this->getContext(), 'savewinessnetwork' );
		$htmlForm->setSubmitText( 'Save Witness Network Name' );
		$htmlForm->setSubmitCallback( [ $this, 'SaveWitnessNetworkName' ] );
		$htmlForm->show();



    	public static function SaveWitnessNetworkName( $formData ) {
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$res = $dbr->select(
			'page_verification',
			[ 'MAX(rev_id) as rev_id', 'page_title', 'hash_verification' ],
			'',
			__METHOD__,
			[ 'GROUP BY' => 'page_title']
		);
        }

    	public static function SaveWitnessSmartContractAddress( $formData ) {
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$res = $dbr->select(
			'page_verification',
			[ 'MAX(rev_id) as rev_id', 'page_title', 'hash_verification' ],
			'',
			__METHOD__,
			[ 'GROUP BY' => 'page_title']
		);
        }



?>
