<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\FileHashContent;
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
		if ( $registry->isDefinedRole( FileHashContent::SLOT_ROLE_FILE_HASH ) ) {
			return;
		}
		$registry->defineRoleWithModel(
			FileHashContent::SLOT_ROLE_FILE_HASH,
			FileHashContent::CONTENT_MODEL_FILE_HASH,
			[ 'display' => 'section' ]
		);
	}
}
