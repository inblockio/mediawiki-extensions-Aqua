<?php

use MediaWiki\MediaWikiServices;

require_once dirname( __DIR__, 4 ) . '/maintenance/Maintenance.php';

class CreateSysopUser extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Create user with admin rights' );
		$this->addArg( 'username', 'Username of sysop user', true );
	}

	public function execute() {
		$username = $this->getArg();

		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getService( 'UserFactory' );
		$userGroupManager = $services->getUserGroupManager();

		$user = $userFactory->newFromName( $username );
		$user->addToDatabase();
		$userGroupManager->addUserToGroup( $user, 'sysop' );

		$this->output( "Created user $user\n" );
	}
}

$maintClass = CreateSysopUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
