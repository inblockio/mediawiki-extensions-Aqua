<?php

declare( strict_types = 1 );

namespace DataAccounting;

/**
 * Top level factory for services construction.
 */
class DataAccountingFactory {

	protected static ?self $instance;

	public static function getInstance(): self {
		self::$instance ??= new self();
		return self::$instance;
	}

	final protected function __construct() {
	}

	public function newPageVerificationBuilder(): PageVerificationBuilder {
		return new PageVerificationBuilder();
	}

}
