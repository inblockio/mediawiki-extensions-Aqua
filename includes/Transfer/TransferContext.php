<?php

namespace DataAccounting\Transfer;

use DataAccounting\Verification\Entity\VerificationEntity;
use Title;

class TransferContext implements \JsonSerializable {
	/** @var string */
	private $genesisHash;
	/** @var string */
	private $domainId;
	/** @var array */
	private $siteInfo;
	/** @var Title */
	private $title;
	/** @var int */
	private $chainHeight;

	/**
	 * @param string $genesisHash
	 * @param string $domainId
	 * @param array $siteInfo
	 * @param Title $title
	 * @param int $chainHeight
	 */
	public function __construct(
		$genesisHash, $domainId,
		array $siteInfo, Title $title, int $chainHeight
	) {
		$this->genesisHash = $genesisHash;
		$this->domainId = $domainId;
		$this->siteInfo = $siteInfo;
		$this->title = $title;
		$this->chainHeight = $chainHeight;
	}

	/**
	 * @return string
	 */
	public function getGenesisHash(): string {
		return $this->genesisHash;
	}

	/**
	 * @return string
	 */
	public function getDomainId(): string {
		return $this->domainId;
	}

	/**
	 * @return array
	 */
	public function getSiteInfo(): array {
		return $this->siteInfo;
	}

	/**
	 * @return Title
	 */
	public function getTitle(): Title {
		return $this->title;
	}

	/**
	 * @return int
	 */
	public function getChainHeight(): int {
		return $this->chainHeight;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			VerificationEntity::GENESIS_HASH => $this->genesisHash,
			VerificationEntity::DOMAIN_ID => $this->domainId,
			'site_info' => $this->siteInfo,
			'title' => $this->title->getDBkey(),
			'namespace' => $this->title->getNamespace(),
			'chain_height' => $this->chainHeight,
		];
	}
}
