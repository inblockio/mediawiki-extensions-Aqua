<?php

namespace DataAccounting\Hook;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use SkinTemplate;
use MediaWiki\Permissions\PermissionManager;

class AddDAActions implements SkinTemplateNavigation__UniversalHook {

	private PermissionManager $permissionManager;

	public function __construct(
		PermissionManager $permissionManager
	) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( isset( $links['actions']['watch'] ) ) {
			unset(  $links['actions']['watch'] );
		}
		if ( isset( $links['actions']['unwatch'] ) ) {
			unset(  $links['actions']['unwatch'] );
		}
		if ( isset( $links['actions']['protect'] ) ) {
			unset(  $links['actions']['protect'] );
		}

		if ( $sktemplate->getTitle()->isSpecialPage() ) {
			return;
		}

		// Sign
		if ( $this->permissionManager->userCan( 'edit', $sktemplate->getUser(), $sktemplate->getTitle() ) ) {
			$action = $sktemplate->getRequest()->getText( 'action' );
			$links['actions']['daact'] = [
				'class' => $action === 'daact' ? 'selected' : false,
				'text' => $sktemplate->msg( 'da-contentaction-daact' )->text(),
				'href' => $sktemplate->getTitle()->getLocalURL( 'action=daact' ),
			];
		}

		// Export
		$links['actions']['da_export'] = [
			'id' => 'ca-da-export',
			'href' => '#',
			'text' => 'Export 📦',
		];
		$sktemplate->getOutput()->addModules( 'ext.dataAccounting.exportSinglePage' );
	}
 }
