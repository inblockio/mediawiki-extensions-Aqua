<?php

namespace DataAccounting\Verification;

use DateTime;
use JsonSerializable;
use MediaWiki\Storage\RevisionRecord;
use Title;

class VerificationEntity implements JsonSerializable {
	public const HASH_TYPE_VERIFICATION = 'verification_hash';
	public const HASH_TYPE_CONTENT = 'content_hash';
	public const HASH_TYPE_GENESIS = 'genesis_hash';
	public const HASH_TYPE_METADATA = 'hash_metadata';
	public const HASH_TYPE_SIGNATURE = 'signature_hash';

	/** @var Title */
	private $title;
	/** @var RevisionRecord */
	private $revision;
	/** @var array */
	private $hashes;
	/** @var DateTime */
	private $time;
	/** @var array */
	private $verficationContext;
	/** @var string */
	private $signature;
	/** @var string */
	private $publicKey;
	/** @var string */
	private $walletAddress;
	/** @var string */
	private $witnessEventId;

	/**
	 * @param Title $title
	 * @param RevisionRecord $revision
	 * @param array $hashes
	 * @param DateTime $time
	 * @param string $signature
	 * @param string $publicKey
	 * @param string $walletAddress
	 * @param string $witnessEventId
	 */
	public function __construct(
		Title $title, RevisionRecord $revision, array $hashes, DateTime $time, array $verificationContext,
		string $signature, string $publicKey, string $walletAddress, string $witnessEventId
	) {
		$this->title = $title;
		$this->revision = $revision;
		$this->hashes = $hashes;
		$this->time = $time;
		$this->verficationContext = $verificationContext;
		$this->signature = $signature;
		$this->publicKey = $publicKey;
		$this->walletAddress = $walletAddress;
		$this->witnessEventId = $witnessEventId;
	}

	/**
	 * @return Title
	 */
	public function getTitle(): Title {
		return $this->title;
	}

	/**
	 * @return RevisionRecord
	 */
	public function getRevision(): RevisionRecord {
		return $this->revision;
	}

	/**
	 * @return array
	 */
	public function getHashes(): array {
		return $this->hashes;
	}

	/**
	 * @param string $type
	 * @param mixed|null $default
	 * @return string|null
	 */
	public function getHash(
		$type = self::HASH_TYPE_VERIFICATION, $default = null
	): ?string {
		if ( isset( $this->hashes[$type] ) && !empty( $this->hashes[$type] ) ) {
			return $this->hashes[$type];
		}

		return $default;
	}

	/**
	 * @return DateTime
	 */
	public function getTime(): DateTime {
		return $this->time;
	}

	/**
	 * @return array
	 */
	public function getVerificationContext(): array {
		return $this->verficationContext;
	}

	/**
	 * @return string
	 */
	public function getSignature(): string {
		return $this->signature;
	}

	/**
	 * @return string
	 */
	public function getPublicKey(): string {
		return $this->publicKey;
	}

	/**
	 * @return string
	 */
	public function getWalletAddress(): string {
		return $this->walletAddress;
	}

	/**
	 * @return string
	 */
	public function getWitnessEventId(): string {
		return $this->walletAddress;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [];
	}
}
