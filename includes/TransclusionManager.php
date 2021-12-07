<?php

namespace DataAccounting;

use DataAccounting\Content\TransclusionHashes;
use File;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use MediaWiki\Revision\RevisionRecord;
use TitleFactory;

class TransclusionManager {
	public const STATE_NEW_VERSION = 'new-version';
	public const STATE_HASH_CHANGED = 'hash-changed';
	public const STATE_UNCHANGED = 'unchanged';

	/** @var TitleFactory */
	private $titleFactory;
	/** @var HashLookup */
	private $hashLookup;

	public function __construct( TitleFactory $titleFactory, HashLookup $hashLookup ) {
		$this->titleFactory = $titleFactory;
		$this->hashLookup = $hashLookup;
	}

	public function getTransclusionState( RevisionRecord $revision ) {
		$states = [];
		$transclusions = $this->getTransclusionHashes( $revision );
		foreach ( $transclusions as $transclusion ) {
			$title = $this->titleFactory->makeTitle( $transclusion->ns, $transclusion->dbkey );
			$latestHash = $this->hashLookup->getLatestHashForTitle( $title );
			if ( $transclusion->hash !== null ) {
				$hashExists = $this->hashLookup->getRevisionForHash( $transclusion->hash ) !== null;
				if ( !$hashExists ) {
					$states[$title->getPrefixedDBkey()] = [
						'state' => static::STATE_HASH_CHANGED,
						'hash' => $transclusion->hash,
						'new_hash' => $this->hashLookup->getHashForRevision( $revision ),
					];
					continue;
				}
			}

			if ( $latestHash !== $transclusion->hash ) {
				$states[$title->getPrefixedDBkey()] = [
					'state' => static::STATE_NEW_VERSION,
					'hash' => $transclusion->hash,
					'new_hash' => $latestHash
				];
				continue;
			}
			$states[$title->getPrefixedDBkey()] = [
				'state' => static::STATE_UNCHANGED,
				'hash' => $transclusion->hash
			];
		}

		return $states;
	}

	/**
	 * @param RevisionRecord $revision
	 * @return array
	 */
	public function getTransclusionHashes( RevisionRecord $revision ): array {
		$content = $revision->getContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES );
		if ( !$content instanceof TransclusionHashes ) {
			return [];
		}
		return $content->getResourceHashes();
	}

	/**
	 * @param array $hashes
	 * @param LinkTarget|PageReference $title
	 * @return string|null
	 */
	public function getHashForTitle( array $hashes, $title ): ?string {
		foreach ( $hashes as $hashEntity ) {
			if ( $title->getNamespace() === $hashEntity->ns && $title->getDBkey() === $hashEntity->dbkey ) {
				return $hashEntity->hash;
			}
		}

		return null;
	}

	/**
	 * @param string $hash
	 * @param File $file
	 * @return File|null
	 */
	public function getFileForHash( string $hash, File $file ): ?File {
		$revision = $this->hashLookup->getRevisionForHash( $hash );
		if ( !$revision ) {
			return null;
		}
		if ( $revision->isCurrent() ) {
			return $file;
		}
		$oldFiles = $file->getHistory();
		foreach( $oldFiles as $oldFile ) {
			if ( $oldFile->getTimestamp() === $revision->getTimestamp() ) {
				return $oldFile;
			}
		}
	}

	/**
	 * @param string $hash
	 * @return RevisionRecord|null
	 */
	public function getRevisionForHash( string $hash ): ?RevisionRecord {
		return $this->hashLookup->getRevisionForHash( $hash );
	}
}
