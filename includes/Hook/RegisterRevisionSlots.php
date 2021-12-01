<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\SignatureContent;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Revision\SlotRoleRegistry;

class RegisterRevisionSlots implements MediaWikiServicesHook {

	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			function( SlotRoleRegistry $registry ) {
				if ( $registry->isDefinedRole( SignatureContent::SLOT_ROLE_SIGNATURE ) ) {
					return;
				}
				$registry->defineRoleWithModel(
					SignatureContent::SLOT_ROLE_SIGNATURE,
					SignatureContent::CONTENT_MODEL_SIGNATURE,
					[ 'display' => 'section' ]
				);
			}
		);
	}
}
