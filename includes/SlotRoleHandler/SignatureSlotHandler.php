<?php

namespace DataAccounting\SlotRoleHandler;

use DataAccounting\Content\SignatureContent;
use MediaWiki\Revision\SlotRoleHandler;
use Message;
use RawMessage;

class SignatureSlotHandler extends SlotRoleHandler {
	/**
	 * @param string $role
	 */
	public function __construct( $role ) {
		parent::__construct( $role, SignatureContent::CONTENT_MODEL_SIGNATURE );
	}

	/**
	 * @return Message
	 */
	public function getNameMessageKey() {
		return new RawMessage( 'Signatures' );
	}
}
