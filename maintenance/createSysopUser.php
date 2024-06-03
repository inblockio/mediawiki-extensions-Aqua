<?php

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class CreateSysopUser extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Create user with admin rights' );
		$this->addArg( 'username', 'Username of sysop user' );
	}

	public function execute() {
		$username = $this->getArg();

		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getService( 'UserFactory' );
		$userGroupManager = $services->getUserGroupManager();

		$user = $userFactory->newFromName( $username );
		if ( !$user->isRegistered() ) {
			$this->output( "User $username does not exist, adding...\n" );
			$user->addToDatabase();
		} else {
			$this->output( "User $username already exists, promoting... \n" );
		}
		$userGroupManager->addUserToGroup( $user, 'sysop' );

		$this->output( "Done\n" );
	}
}

$maintClass = CreateSysopUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
