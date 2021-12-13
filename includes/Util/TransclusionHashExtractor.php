<?php

namespace DataAccounting\Util;

use DataAccounting\Hasher\RevisionVerificationRepo;
use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationLookup;
use DataAccounting\Verification\VerificationEntity;
use MWException;
use ParserOutput;
use Title;
use TitleFactory;

class TransclusionHashExtractor {
	/** @var ParserOutput */
	private $parserOutput;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var RevisionVerificationRepo */
	private $verifcationEngine;
	/** @var array|null */
	private $hashMap = null;

	public function __construct(
		ParserOutput $po, TitleFactory $titleFactory, VerificationEngine $verificationEngine
	) {
		$this->parserOutput = $po;
		$this->titleFactory = $titleFactory;
		$this->verifcationEngine = $verificationEngine;
	}

	public function getHashmap(): array {
		if ( $this->hashMap === null ) {
			$this->parsePageResources();
		}
		return $this->hashMap;
	}

	private function parsePageResources() {
		$this->hashMap = [];

		$titles = [];
		$this->parseImages( $titles );
		$this->parseTemplates( $titles );
		// This is not necessary since it does not change content,
		// but we might need it in the future
		$this->parseLinks( $titles );

		$this->retrieveHashes( $titles );
	}

	private function parseImages( array &$titles ) {
		foreach ( $this->parserOutput->getImages() as $name => $const ) {
			$title = $this->titleFactory->makeTitle( NS_FILE, $name );
			$titles[$title->getPrefixedDBkey()] = $title;
		}
	}

	private function parseTemplates( array &$titles ) {
		$this->parseNested( $this->parserOutput->getTemplates(), $titles );
	}

	private function parseLinks( array &$titles ) {
		$this->parseNested( $this->parserOutput->getLinks(), $titles );
	}

	private function parseNested( array $data, array &$titles ) {
		foreach ( $data as $ns => $links ) {
			foreach ( $links as $name => $id ) {
				$title = $this->titleFactory->makeTitle( $ns, $name );
				$titles[$title->getPrefixedDBkey()] = $title;
			}
		}
	}

	private function retrieveHashes( array $titles ) {
		/**
		 * @var string $dbKey
		 * @var Title $title
		 */
		foreach ( $titles as $dbKey => $title ) {
			$transclusion = [
				'dbkey' => $title->getDBkey(),
				'ns' => $title->getNamespace(),
				'revid' => $title->getLatestRevID(),
				VerificationEntity::HASH_TYPE_GENESIS => null,
				VerificationEntity::HASH_TYPE_VERIFICATION => null,
				VerificationEntity::HASH_TYPE_CONTENT => null,
			];
			if ( $title->exists() ) {
				$entity = $this->verifcationEngine->getLookup()
					->getVerificationEntityFromRevId( $title->getLatestRevID() );
				if ( !$entity ) {
					// this is just for sanity, should never even happen
					throw new MWException( 'Failed to retrieve entity for revid ' . $title->getLatestRevID() );
				}
				$transclusion[VerificationEntity::HASH_TYPE_GENESIS] =
					$entity->getHash( VerificationEntity::HASH_TYPE_GENESIS );
				$transclusion[VerificationEntity::HASH_TYPE_VERIFICATION] =
					$entity->getHash( VerificationEntity::HASH_TYPE_VERIFICATION );
				$transclusion[VerificationEntity::HASH_TYPE_CONTENT] =
					$entity->getHash( VerificationEntity::HASH_TYPE_CONTENT );
			}

			$this->hashMap[] = $transclusion;
		}
	}
}
