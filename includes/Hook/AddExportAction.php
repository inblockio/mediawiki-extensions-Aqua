<?php

namespace DataAccounting\Hook;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;

class AddExportAction implements SkinTemplateNavigation__UniversalHook {

	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( $sktemplate->getTitle()->isSpecialPage() ) {
			return;
		}
		$links['actions']['da_export'] = [
			'id' => 'ca-da-export',
			'href' => '#',
			'text' => 'Export 📦',
		];
		$sktemplate->getOutput()->addModules( 'ext.dataAccounting.exportSinglePage' );
	}
 }
