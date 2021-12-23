<?php

namespace DataAccounting\API;

use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\Entity\VerificationEntity;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use LogicException;
use RequestContext;
use Title;

abstract class AuthorizedEntityHandler extends SimpleHandler {
	/** @var PermissionManager  */
	protected $permissionManager;
	/** @var VerificationEngine */
	protected $verificationEngine;
	/** @var VerificationEntity|null */
	protected $verificationEntity = null;

	/**
	 * @param PermissionManager $permissionManager
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct(
		PermissionManager $permissionManager, VerificationEngine $verificationEngine
	) {
		$this->permissionManager = $permissionManager;
		$this->verificationEngine = $verificationEngine;
	}

	public function execute() {
		// test ci
		$paramSettings = $this->getParamSettings();
		$validatedParams = $this->getValidatedParams();
		$unvalidatedParams = [];
		$params = [];
		foreach ( $this->getRequest()->getPathParams() as $name => $value ) {
			$source = $paramSettings[$name][self::PARAM_SOURCE] ?? 'unknown';
			if ( $source !== 'path' ) {
				$unvalidatedParams[] = $name;
				$params[] = $value;
			} else {
				$params[] = $validatedParams[$name];
			}
		}

		if ( $unvalidatedParams ) {
			throw new LogicException(
				'Path parameters were not validated: ' . implode( ', ', $unvalidatedParams )
			);
		}

		$this->assertEntitySet( ...$params );
		$this->checkPermission();

		// @phpstan-ignore-next-line
		return $this->run( ...$params );
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function needsReadAccess() {
		return false;
	}

	/**
	 * @return string[]
	 */
	protected function getPermissions(): array {
		return [ 'read' ];
	}

	/**
	 * @return Title|null
	 */
	protected function provideTitle(): ?Title {
		return $this->verificationEntity->getTitle();
	}

	protected function checkPermission() {
		$title = $this->provideTitle();
		if ( !$title ) {
			throw new HttpException( "Not found", 404 );
		}
		$user = RequestContext::getMain()->getUser();
		foreach ( $this->getPermissions() as $permission ) {
			if ( $this->permissionManager->userCan( $permission, $user, $title ) ) {
				continue;
			}
			throw new HttpException( "Permission denied", 401 );
		}
	}

	private function assertEntitySet( /* args */ ) {
		// @phan-suppress-next-line PhanUndeclaredMethod
		// @phpstan-ignore-next-line
		$this->verificationEntity = $this->getEntity( ...func_get_args() );
		if ( $this->verificationEntity === null ) {
			throw new HttpException( "Not found", 404 );
		}
	}
}
