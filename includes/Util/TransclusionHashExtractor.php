<?php

namespace DataAccounting\Util;

use DataAccounting\Hasher\RevisionVerificationRepo;
use ParserOutput;
use Title;
use TitleFactory;

class TransclusionHashExtractor {
	/** @var ParserOutput */
	private $parserOutput;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var RevisionVerificationRepo */
	private $revisionVerficationRepo;
	/** @var array|null */
	private $hashMap = null;

	public function __construct(
		ParserOutput $po, TitleFactory $titleFactory, RevisionVerificationRepo $verificationRepo
	) {
		$this->parserOutput = $po;
		$this->titleFactory = $titleFactory;
		$this->revisionVerficationRepo = $verificationRepo;
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
			$entity = [
				'dbkey' => $title->getDBkey(),
				'ns' => $title->getNamespace(),
				'revid' => $title->getLatestRevID(),
				'verification_hash' => null,
				'hash_content' => null,
			];
			if ( $title->exists() ) {
				$verificationData = $this->revisionVerficationRepo
					->getRevisionVerificationData( $title->getLatestRevID() );
				if ( !empty( $verificationData['verification_hash'] ) ) {
					$entity['verification_hash'] = $verificationData['verification_hash'];
				}
				if ( !empty( $verificationData['hash_content'] ) ) {
					$entity['hash_content'] = $verificationData['hash_content'];
				}
			}

			$this->hashMap[] = $entity;
		}
	}
}
