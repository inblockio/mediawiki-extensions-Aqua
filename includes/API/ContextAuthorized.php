<?php

namespace DataAccounting\API;

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use LogicException;
use RequestContext;

abstract class ContextAuthorized extends SimpleHandler {

	/**
	 * @var PermissionManager 
	 */
	protected $permissionManager;

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( PermissionManager $permissionManager ) {
		$this->permissionManager = $permissionManager;
	}

	public function execute() {
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

		$this->checkPermission( ...$params );

		// @phan-suppress-next-line PhanUndeclaredMethod
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

	protected function checkPermission() {
		// @phan-suppress-next-line PhanUndeclaredMethod
		// @phpstan-ignore-next-line
		$title = $this->provideTitle( ...func_get_args() );
		if ( !$title ) {
			throw new HttpException( "Not found", 404 );
		}
		$user = RequestContext::getMain()->getUser();
		foreach ( $this->getPermissions() as $permission ) {
			if ( $this->permissionManager->userCan( $permission, $user, $title ) ) {
				continue;
			}
			throw new HttpException( "Not found", 404 );
		}
	}
}
