<?php

namespace DataAccounting\Verification;

use DateTime;
use JsonSerializable;
use MediaWiki\Storage\RevisionRecord;
use Title;

class VerificationEntity implements JsonSerializable {
	public const VERIFICATION_HASH = 'verification_hash';
	public const CONTENT_HASH = 'content_hash';
	public const GENESIS_HASH = 'genesis_hash';
	public const HASH_TYPE_METADATA = 'hash_metadata';
	public const HASH_TYPE_SIGNATURE = 'signature_hash';

	/** @var Title */
	private $title;
	/** @var RevisionRecord */
	private $revision;
	/** @var string */
	private $domainId;
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
	 * @param string $domainId
	 * @param array $hashes
	 * @param DateTime $time
	 * @param array $verificationContext
	 * @param string $signature
	 * @param string $publicKey
	 * @param string $walletAddress
	 * @param string $witnessEventId
	 */
	public function __construct(
		Title $title, RevisionRecord $revision, string $domainId, array $hashes, DateTime $time,
		array $verificationContext, string $signature, string $publicKey, string $walletAddress, string $witnessEventId
	) {
		$this->title = $title;
		$this->revision = $revision;
		$this->domainId = $domainId;
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

	public function getDomainId(): string {
		return $this->domainId;
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
	 * @return string
	 */
	public function getHash(
		$type = self::VERIFICATION_HASH, $default = ''
	): string {
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
