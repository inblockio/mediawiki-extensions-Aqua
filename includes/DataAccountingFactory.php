<?php

declare( strict_types = 1 );

namespace DataAccounting;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

use DataAccounting\Hasher\RevisionVerificationBuilder;
use DataAccounting\Hasher\RevisionVerificationRepo;
use DataAccounting\Hasher\HashingService;
use DataAccounting\Hasher\DbRevisionVerificationRepo;

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

	public function newRevisionVerificationBuilder(): RevisionVerificationBuilder {
		return new RevisionVerificationBuilder(
			$this->newRevisionVerificationRepo(),
			new HashingService( getDomainId() )
		);
	}

	private function newRevisionVerificationRepo(): RevisionVerificationRepo {
		return new DbRevisionVerificationRepo(
			MediaWikiServices::getInstance()->getDBLoadBalancer()
		);
	}

}
