<?php

namespace DataAccounting;

use ResourceLoaderContext;
use ResourceLoaderFileModule;
use Xml;

class SignMessageModule extends ResourceLoaderFileModule {

	/** @inheritDoc */
	public function getScript( ResourceLoaderContext $context ) {
		$conf = $this->getConfig();
		return Xml::encodeJsCall( 'mw.config.set', [
				[
					'wgExampleWelcomeColorDefault' => $conf->get( 'ExampleWelcomeColorDefault' ),
				] ] )
			. parent::getScript( $context );
	}

	/** @return bool */
	public function enableModuleContentVersion() {
		return true;
	}

	/** @return bool */
	public function supportsURLLoading() {
		return false;
	}
}
