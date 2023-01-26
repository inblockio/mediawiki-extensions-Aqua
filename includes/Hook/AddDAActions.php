<?php

namespace DataAccounting\Hook;

use DataAccounting\Verification\VerificationEngine;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use SkinTemplate;
use MediaWiki\Permissions\PermissionManager;

class AddDAActions implements SkinTemplateNavigation__UniversalHook {

	private PermissionManager $permissionManager;

	public function __construct(
		PermissionManager $permissionManager, VerificationEngine $verificationEngine
	) {
		$this->permissionManager = $permissionManager;
		$this->verificationEngine = $verificationEngine;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 * // phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( isset( $links['actions']['watch'] ) ) {
			unset( $links['actions']['watch'] );
		}
		if ( isset( $links['actions']['unwatch'] ) ) {
			unset( $links['actions']['unwatch'] );
		}
		if ( isset( $links['actions']['protect'] ) ) {
			unset( $links['actions']['protect'] );
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

		if ( $this->permissionManager->userCan( 'delete', $sktemplate->getUser(), $sktemplate->getTitle() ) ) {
			$entity = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $sktemplate->getTitle() );
			if ( !$entity ) {
				return;
			}
			if ( $entity->getDomainId() === $this->verificationEngine->getDomainId() ) {
				// Delete revisions, allowed only on local domain
				$links['actions']['da_delete_revisions'] = [
					'id' => 'ca-da-delete-revisions',
					'href' => '#',
					'text' => 'Delete revisions 🗑️',
				];
				// Squash revisions, allowed only on local domain
				$links['actions']['da_squash_revisions'] = [
					'id' => 'ca-da-squash-revisions',
					'href' => '#',
					'text' => 'Squash revisions 💥',
				];$sktemplate->getOutput()->addModules( 'ext.DataAccounting.revisionActions' );

			}
		}

	}
}
