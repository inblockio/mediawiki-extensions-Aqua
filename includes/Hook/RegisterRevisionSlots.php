<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\FileVerificationContent;
use DataAccounting\Content\SignatureContent;
use DataAccounting\Content\TransclusionHashes;
use DataAccounting\SlotRoleHandler\SignatureSlotHandler;
use DataAccounting\SlotRoleHandler\TransclusionHashesSlotHandler;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Revision\SlotRoleHandler;
use MediaWiki\Revision\SlotRoleRegistry;

class RegisterRevisionSlots implements MediaWikiServicesHook {

	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			function( SlotRoleRegistry $registry ) {
				$this->registerSignatureRole( $registry );
				$this->registerTransclusionHashesRole( $registry );
<<<<<<< HEAD
				$this->registerFileVerificationRole( $registry );
=======
>>>>>>> 2e559fd... feat!: Keep hashmap of included resources
			}
		);
	}

	private function registerSignatureRole( SlotRoleRegistry $registry ) {
		if ( $registry->isDefinedRole( SignatureContent::SLOT_ROLE_SIGNATURE ) ) {
			return;
		}
		$registry->defineRole(
			SignatureContent::SLOT_ROLE_SIGNATURE,
			static function ( $role ) {
				return new SignatureSlotHandler( $role );
			}
		);
	}

	private function registerTransclusionHashesRole( SlotRoleRegistry $registry ) {
		if ( $registry->isDefinedRole( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES ) ) {
			return;
		}
		$registry->defineRole(
			TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES,
			static function ( $role ) {
				return new TransclusionHashesSlotHandler( $role );
			}
		);
	}
<<<<<<< HEAD

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
=======
>>>>>>> 2e559fd... feat!: Keep hashmap of included resources
}
