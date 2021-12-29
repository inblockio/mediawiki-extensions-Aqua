<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\FileVerificationContent;
use DataAccounting\Content\SignatureContent;
use DataAccounting\Content\TransclusionHashes;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Revision\SlotRoleRegistry;

class RegisterRevisionSlots implements MediaWikiServicesHook {

	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			function( SlotRoleRegistry $registry ) {
				$this->registerTransclusionHashesRole( $registry );
				$this->registerSignatureRole( $registry );
				$this->registerFileVerificationRole( $registry );
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

	private function registerFileVerificationRole( SlotRoleRegistry $registry ) {
		if ( $registry->isDefinedRole( FileVerificationContent::SLOT_ROLE_FILE_VERIFICATION ) ) {
			return;
		}
		$registry->defineRoleWithModel(
			FileVerificationContent::SLOT_ROLE_FILE_VERIFICATION,
			FileVerificationContent::CONTENT_MODEL_FILE_VERIFICATION,
			[ 'display' => 'section' ]
		);
	}
}
