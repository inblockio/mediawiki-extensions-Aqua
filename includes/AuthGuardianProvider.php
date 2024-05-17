<?php

namespace DataAccounting;

use Config;
use ConfigFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\GrantsInfo;
use MediaWiki\Session\SessionBackend;
use MediaWiki\Session\SessionInfo;
use MediaWiki\Session\SessionManager;
use MediaWiki\Session\SessionProvider;
use MediaWiki\Session\UserInfo;
use MediaWiki\User\UserFactory;
use WebRequest;

class AuthGuardianProvider extends SessionProvider {

	/** @var UserFactory */
	private UserFactory $userFactory;

	/** @var Config */
	private Config $daConfig;

	/** @var GrantsInfo */
	private GrantsInfo $grantsInfo;

	public function __construct( UserFactory $userFactory, ConfigFactory $configFactory, GrantsInfo $grantsInfo ) {
		parent::__construct();
		$this->userFactory = $userFactory;
		$this->daConfig = $configFactory->makeConfig( 'da' );
		$this->grantsInfo = $grantsInfo;
	}

	/**
	 * @inheritDoc
	 * @throws \Exception
	 */
	public function provideSessionInfo( WebRequest $request ) {
		// Do nothing if not in the REST API
		if ( !defined( 'MW_REST_API' ) ) {
			return null;
		}

		// Do nothing if no authorization header token is present
		$authToken = $this->getBearerToken( $request );
		if ( $authToken === null ) {
			return null;
		}

		// Do nothing if there is a valid session
		$prefix = $this->getConfig()->get( MainConfigNames::CookiePrefix );
		$sessionId = $request->getCookie( '_session', $prefix );

		// Do nothing on valid sessions
		if ( SessionManager::validateSessionId( $sessionId ) ) {
			return null;
		}

		// Compare tokens
		$guardianToken = $this->daConfig->get( 'GuardianToken' );
		if ( $authToken !== $guardianToken ) {
			return null;
		}

		$guardianUsername = $this->daConfig->get( 'GuardianUsername' );
		$guardianUser = $this->userFactory->newFromName( $guardianUsername );
		$sessionId = bin2hex( random_bytes( 32 ) );

		return new SessionInfo( SessionInfo::MAX_PRIORITY, [
			'provider' => $this,
			'id' => $sessionId,
			'userInfo' => UserInfo::newFromUser( $guardianUser, true ),
			'persisted' => $sessionId !== null,
			'forceUse' => true,
			'metadata' => [
				'rights' => $this->grantsInfo->getGrantRights( $this->getGrants() )
			],
		] );
	}

	/**
	 * @return string|null
	 */
	private function getBearerToken( WebRequest $request ): ?string {
		$header = $request->getHeader( 'authorization' );

		if ( empty( $header ) ) {
			return null;
		}

		if ( preg_match( '/Bearer\s(\S+)/', $header, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Grants all the rights
	 *
	 * @return string[]
	 */
	private function getGrants(): array {
		return [
			'basic',
			'highvolume',
			'import',
			'editpage',
			'editprotected',
			'editmycssjs',
			'editmyoptions',
			'editinterface',
			'editsiteconfig',
			'createeditmovepage',
			'uploadfile',
			'uploadeditmovefile',
			'patrol',
			'rollback',
			'blockusers',
			'viewdeleted',
			'viewrestrictedlogs',
			'delete',
			'oversight',
			'protect',
			'viewmywatchlist',
			'editmywatchlist',
			'sendemail',
			'createaccount',
			'privateinfo',
			'mergehistory',
			'oath',
		];
	}

	public function persistsSessionId() {
		return false;
	}

	public function canChangeUser() {
		return false;
	}

	public function persistSession( SessionBackend $session, WebRequest $request ) {
		// TODO: Implement persistSession() method.
	}

	public function unpersistSession( WebRequest $request ) {
		// TODO: Implement unpersistSession() method.
	}
}
