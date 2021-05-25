<?php

namespace MediaWiki\Extension\Example;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Example class to echo a path parameter
 */
class RestApiStandard extends SimpleHandler {

	private const VALID_ACTIONS = [ 'reverse', 'shuffle', 'md5' ];

	/** @inheritDoc */
	public function run( $action, $var1, $var2 ) {
		switch ( $action ) {
			case 'reverse':
				return [ 'echo' => strrev( $var1 ) . $var2 ];

			case 'shuffle':
				return [ 'echo' => str_shuffle( $var1 ) ];

			case 'md5':
				return [ 'echo' => md5( $var1 ) ];

			default:
				return [ ' echo' => $valueToEcho ];
		}
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
                        'action' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => self::VALID_ACTIONS,
				ParamValidator::PARAM_REQUIRED => true,
			],
                        'var1' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
                        'var2' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}

