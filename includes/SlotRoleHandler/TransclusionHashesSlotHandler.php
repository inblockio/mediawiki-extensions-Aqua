<?php

namespace DataAccounting\SlotRoleHandler;

use DataAccounting\Content\TransclusionHashes;
use MediaWiki\Revision\SlotRoleHandler;
use Message;
use RawMessage;

class TransclusionHashesSlotHandler extends SlotRoleHandler {
	/**
	 * @param string $role
	 */
	public function __construct( $role ) {
		parent::__construct( $role, TransclusionHashes::CONTENT_MODEL_TRANSCLUSION_HASHES );
	}

	/**
	 * @return Message
	 */
	public function getNameMessageKey() {
		return new RawMessage( 'Included resources' );
	}
}
