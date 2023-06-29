<?php

namespace DataAccounting\Inbox;


use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Status;
use Title;

class InboxImporter {

	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var TreeBuilder|null */
	private $treeBuilder = null;

	/**
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct( VerificationEngine $verificationEngine ) {
		$this->verificationEngine = $verificationEngine;
	}

	public function importDirect( VerificationEntity $source, Title $target, UserIdentity $actor ): Status {
		$movePage = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage(
			$source->getTitle(),
			$target
		);
		return $movePage->move( $actor, 'DataAccounting: Importing from inbox', false );
	}

	/**
	 * @return TreeBuilder
	 */
	public function getTreeBuilder(): TreeBuilder {
		if ( $this->treeBuilder === null ) {
			$this->treeBuilder = new TreeBuilder( $this->verificationEngine );
		}
		return $this->treeBuilder;
	}
}