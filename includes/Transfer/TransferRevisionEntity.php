<?php

namespace DataAccounting\Transfer;

use DataAccounting\Verification\Entity\VerificationEntity;

class TransferRevisionEntity implements \JsonSerializable {
	// /** @var array */
	// private $verificationContext;
	/** @var array */
	private $content;
	/** @var array */
	private $metadata;
	/** @var array|null */
	private $signature;
	/** @var array|null */
	private $witness;

	/**
	//  * @param array $verificationContext
	 * @param array $content
	 * @param array $metadata
	 * @param array|null $signature
	 * @param array|null $witness
	 */
	public function __construct(
		// array $verificationContext,
		array $content, array $metadata, $signature, $witness
	) {
		// $this->verificationContext = $verificationContext;
		$this->content = $content;
		$this->metadata = $metadata;
		$this->signature = $signature;
		$this->witness = $witness;
	}

	// /**
	//  * @return array
	//  */
	// public function getVerificationContext(): array {
	// 	return $this->verificationContext;
	// }

	/**
	 * @return array
	 */
	public function getContent(): array {
		return $this->content;
	}

	/**
	 * @return array
	 */
	public function getMetadata(): array {
		return $this->metadata;
	}

	/**
	 * @return array|null
	 */
	public function getSignature(): ?array {
		return $this->signature;
	}

	/**
	 * Filter witness_event_id from witness array
	 *
	 * @return array|null
	 */
	public function getWitness(): ?array {
		if ( $this->witness === null ) {
			return null;
		}

		return array_filter( $this->witness, function ( $key ) {
			return $key !== 'witness_event_id';
		}, ARRAY_FILTER_USE_KEY );
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			// 'verification_context' => $this->getVerificationContext(),
			'content' => $this->getContent(),
			'metadata' => $this->getMetadata(),
			'signature' => $this->getSignature(),
			'witness' => $this->getWitness(),
		];
	}
}
