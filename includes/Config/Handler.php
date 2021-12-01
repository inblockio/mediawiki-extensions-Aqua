<?php

namespace DataAccounting\Config;

use Config;
use Exception;
use FormatJson;
use GlobalVarConfig;
use HashConfig;
use MediaWiki\MediaWikiServices;
use Status;
use Wikimedia\Rdbms\ILoadBalancer;

class Handler {
	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer = null;

	/**
	 * @var Config
	 */
	private $config = null;

	/**
	 * @var HashConfig
	 */
	private $databaseConfig = null;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @return Config
	 */
	public static function configFactoryCallback(): Config {
		return MediaWikiServices::getInstance()->get( 'DataAccountingConfigHandler' )
			->getConfig();
	}

	/**
	 * @return Config
	 */
	public function getConfig(): Config {
		if ( $this->config ) {
			return $this->config;
		}
		$this->config = $this->makeConfig();
		return $this->config;
	}

	/**
	 * @return Config
	 */
	private function makeConfig(): Config {
		if ( !$this->databaseConfig ) {
			$this->databaseConfig = $this->makeDatabaseConfig();
		}
		return new DataAccountingConfig( [
			&$this->databaseConfig,
			new GlobalVarConfig( 'da' ),
			new GlobalVarConfig( 'wg' )
		], $this );
	}

	/**
	 * @return HashConfig
	 */
	private function makeDatabaseConfig(): HashConfig {
		$conn = $this->loadBalancer->getConnection( DB_REPLICA );
		$hash = [];

		// workaround for the upgrade process. The new settings cannot be
		// accessed before teh table is created
		if ( !$conn->tableExists( 'da_settings', __METHOD__ ) ) {
			return new HashConfig( $hash );
		}

		$res = $conn->select( 'da_settings', '*', '', __METHOD__ );
		foreach ( $res as $row ) {
			$hash[ $row->name ] = FormatJson::decode( $row->value, true );
		}

		return new HashConfig( $hash );
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return Status
	 */
	public function set( $name, $value ): Status {
		$status = Status::newGood();
		$dsConfig = new GlobalVarConfig( 'da' );
		if ( !$dsConfig->has( $name ) ) {
			$status->fatal(
				"The config '$name' does not exist within the da config prefix"
			);
			return $status;
		}
		$status->merge( $this->setDatabaseConfig( $name, $value ) );
		if ( $status->isOK() ) {
			$this->databaseConfig = $this->makeDatabaseConfig();
		}
		return $status;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return Status
	 */
	private function setDatabaseConfig( $name, $value ): Status {
		$status = Status::newGood();
		$value = FormatJson::encode( $value );
		try {
			$exists = $this->loadBalancer->getConnection( DB_REPLICA )->selectRow(
				'da_settings',
				'name',
				[ 'name' => $name ],
				__METHOD__
			);
			$res = $exists ? $this->loadBalancer->getConnection( DB_PRIMARY )->update(
				'da_settings',
				[ 'value' => $value ],
				[ 'name' => $name ],
				__METHOD__
			) : $this->loadBalancer->getConnection( DB_PRIMARY )->insert(
				'da_settings',
				[ 'value' => $value, 'name' => $name ],
				__METHOD__
			);
			if ( !$res ) {
				$status->fatal( 'Unknown Database error' );
			}
		} catch ( Exception $e ) {
			$status->fatal( $e->getMessage() );
		}
		return $status;
	}

}
