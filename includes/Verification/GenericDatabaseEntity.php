<?php

namespace DataAccounting\Verification;

use JsonSerializable;

class GenericDatabaseEntity implements JsonSerializable {
	/** @var array */
	private $data;

	/**
	 * @param \stdClass $row
	 */
	public function __construct( \stdClass $row ) {
		// Convert to array
		$this->data = json_decode( json_encode( $row ), 1 );
	}

	/**
	 * Get key from entity
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return $this->data[$key] ?? $default;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return $this->data;
	}
}
