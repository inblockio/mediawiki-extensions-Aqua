<?php

namespace DataAccounting\Verification\Entity;

use DateTime;
use JsonSerializable;
use MediaWiki\Revision\RevisionRecord;
use Title;

class VerificationEntity implements JsonSerializable {
	public const VERIFICATION_HASH = 'verification_hash';
	public const CONTENT_HASH = 'content_hash';
	public const GENESIS_HASH = 'genesis_hash';
	public const METADATA_HASH = 'metadata_hash';
	public const SIGNATURE_HASH = 'signature_hash';
	public const PREVIOUS_VERIFICATION_HASH = 'previous_verification_hash';
	public const FORK_HASH = 'fork_hash';
	public const MERGE_HASH = 'merge_hash';

	public const DOMAIN_ID = 'domain_id';

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
	private $verificationContext;
	/** @var string */
	private $signature;
	/** @var string */
	private $publicKey;
	/** @var string */
	private $walletAddress;
	/** @var string */
	private $witnessEventId;
	/** @var string */
	private $source;

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
	 * @param string $source
	 */
	public function __construct(
		Title $title, RevisionRecord $revision, string $domainId, array $hashes, DateTime $time,
		array $verificationContext, string $signature, string $publicKey, string $walletAddress,
		int $witnessEventId, string $source
	) {
		if ( isset( $hashes[self::MERGE_HASH] ) ) {
			error_log( "MERGEHASH: " . $hashes[self::MERGE_HASH] . '#' . $revision->getId() );
		}
		$this->title = $title;
		$this->revision = $revision;
		$this->domainId = $domainId;
		$this->hashes = $hashes;
		$this->time = $time;
		$this->verificationContext = $verificationContext;
		$this->signature = $signature;
		$this->publicKey = $publicKey;
		$this->walletAddress = $walletAddress;
		$this->witnessEventId = $witnessEventId;
		$this->source = $source;
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
		return $this->verificationContext;
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
	 * @return int
	 */
	public function getWitnessEventId(): int {
		return $this->witnessEventId;
	}

	/**
	 * @return string
	 */
	public function getSource(): string {
		return $this->source;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return array_merge( [
			'page_title' => $this->title->getPrefixedDBkey(),
			'page_id' => $this->title->getId(),
			'rev_id' => $this->revision->getId(),
			'domain_id' => $this->domainId,
			'time_stamp' => $this->time->format( 'YmdHis' ),
			'verification_context' => $this->verificationContext,
			'signature' => $this->signature,
			'public_key' => $this->publicKey,
			'wallet_address' => $this->walletAddress,
			'witness_event_id' => empty( $this->witnessEventId ) ? null : $this->witnessEventId,
			'source' => $this->source
		], $this->hashes );
	}
}
