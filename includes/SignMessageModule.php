<?php

namespace DataAccounting;

use ResourceLoaderContext;
use ResourceLoaderFileModule;
use Xml;

class SignMessageModule extends ResourceLoaderFileModule {

	/** @inheritDoc */
	public function getScript( ResourceLoaderContext $context ) {
		return parent::getScript( $context );
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
