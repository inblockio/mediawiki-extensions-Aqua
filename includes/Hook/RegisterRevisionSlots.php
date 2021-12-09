<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\SignatureContent;
use DataAccounting\Content\TransclusionHashes;
use DataAccounting\SlotRoleHandler\SignatureSlotHandler;
use DataAccounting\SlotRoleHandler\TransclusionHashesSlotHandler;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Revision\SlotRoleRegistry;

class RegisterRevisionSlots implements MediaWikiServicesHook {

	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			function( SlotRoleRegistry $registry ) {
				$this->registerSignatureRole( $registry );
				$this->registerTransclusionHashesRole( $registry );
			}
		);
	}

	private function registerSignatureRole( SlotRoleRegistry $registry ) {
		if ( $registry->isDefinedRole( SignatureContent::SLOT_ROLE_SIGNATURE ) ) {
			return;
		}
		$registry->defineRoleWithModel(
			SignatureContent::SLOT_ROLE_SIGNATURE,
			SignatureContent::CONTENT_MODEL_SIGNATURE,
			[ 'display' => 'section' ]
		);
	}

	private function registerTransclusionHashesRole( SlotRoleRegistry $registry ) {
		if ( $registry->isDefinedRole( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES ) ) {
			return;
		}
		$registry->defineRoleWithModel(
			TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES,
			TransclusionHashes::CONTENT_MODEL_TRANSCLUSION_HASHES,
			[ 'display' => 'section' ]
		);
	}
}
