<?php

namespace DataAccounting\API;

use HttpException;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use LogicException;
use PermissionsError;
use Title;
use TitleFactory;
use RequestContext;

abstract class ContextAuthorized extends SimpleHandler {

	/**
	 * @var PermissionManager 
	 */
	protected $permissionManager;

	/**
	 * @var TitleFactory
	 */
	protected $titleFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		PermissionManager $permissionManager,
		TitleFactory $titleFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->titleFactory = $titleFactory;
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
	 * @params mixed ...$params - undisclosed number of mixed params defined by
	 * the rest routs
	 * @return Title|null
	 */
	abstract protected function provideTitle(): ?Title;

	protected function checkPermission() {
		$title = $this->provideTitle( ...func_get_args() );
		if ( !$title ) {
			throw new HttpException( "No title provided for permission check", 404 );
		}
		$user = RequestContext::getMain()->getUser();
		foreach ( $this->getPermissions() as $permission ) {
			if ( $this->permissionManager->userCan( $permission, $user, $title ) ) {
				continue;
			}
			throw new PermissionsError( $permission );
		}
	}
}
