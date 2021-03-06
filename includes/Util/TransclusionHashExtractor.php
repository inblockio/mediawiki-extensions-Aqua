<?php

namespace DataAccounting\Util;

use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\Entity\VerificationEntity;
use MediaWiki\Linker\LinkTarget;
use MWException;
use ParserFactory;
use ParserOutput;
use Title;
use TitleFactory;

class TransclusionHashExtractor {
	/** @var string */
	private $rawText;
	/** @var LinkTarget */
	private $subject;
	/** @var ParserOutput */
	private $parserOutput;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var VerificationEngine */
	private $verifcationEngine;
	/** @var ParserFactory */
	private $parserFactory;
	/** @var array|null */
	private $hashMap = null;

	/**
	 * @param string $rawText
	 * @param LinkTarget $subject
	 * @param ParserOutput $po
	 * @param TitleFactory $titleFactory
	 * @param VerificationEngine $verificationEngine
	 * @param ParserFactory $parserFactory
	 */
	public function __construct(
		string $rawText, LinkTarget $subject, ParserOutput $po, TitleFactory $titleFactory,
		VerificationEngine $verificationEngine, ParserFactory $parserFactory
	) {
		$this->rawText = $rawText;
		$this->subject = $subject;
		$this->parserOutput = $po;
		$this->titleFactory = $titleFactory;
		$this->verifcationEngine = $verificationEngine;
		$this->parserFactory = $parserFactory;
	}

	/**
	 * @return array
	 */
	public function getHashmap(): array {
		if ( $this->hashMap === null ) {
			$this->parsePageResources();
		}
		return $this->hashMap;
	}

	/**
	 * @throws MWException
	 */
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

	/**
	 * @param array $titles
	 */
	private function parseImages( array &$titles ) {
		foreach ( $this->parserOutput->getImages() as $name => $const ) {
			$title = $this->titleFactory->makeTitle( NS_FILE, $name );
			if ( $title->equals( $this->subject ) ) {
				continue;
			}
			$titles[$title->getPrefixedDBkey()] = $title;
		}
	}

	/**
	 * @param array $titles
	 */
	private function parseTemplates( array &$titles ) {
		$this->parseNested( $this->parserOutput->getTemplates(), $titles );
	}

	/**
	 * @param array $data
	 * @param array $titles
	 */
	private function parseNested( array $data, array &$titles ) {
		foreach ( $data as $ns => $links ) {
			foreach ( $links as $name => $id ) {
				$title = $this->titleFactory->makeTitle( $ns, $name );
				if ( $title->equals( $this->subject ) ) {
					continue;
				}
				$titles[$title->getPrefixedDBkey()] = $title;
			}
		}
	}

	/**
	 * @param array $titles
	 */
	private function parseLinks( array &$titles ) {
		foreach ( $this->parserOutput->getLinks() as $ns => $links ) {
			foreach ( $links as $name => $id ) {
				$title = $this->titleFactory->makeTitle( $ns, $name );
				if ( $title->equals( $this->subject ) ) {
					continue;
				}
				if ( $title->isSpecialPage() ) {
					return;
				}

				$titles[$title->getPrefixedDBkey()] = $title;
			}
		}
	}

	/**
	 * TODO: Move this to TransclusionManager
	 * @param array $titles
	 * @throws MWException
	 */
	private function retrieveHashes( array $titles ) {
		/**
		 * @var string $dbKey
		 * @var Title $title
		 */
		foreach ( $titles as $dbKey => $title ) {
			$transclusion = [
				'dbkey' => $title->getDBkey(),
				'ns' => $title->getNamespace(),
				VerificationEntity::VERIFICATION_HASH => null,
			];
			if ( $title->exists() ) {
				$entity = $this->verifcationEngine->getLookup()
					->verificationEntityFromRevId( $title->getLatestRevID() );
				if ( $entity ) {
					$transclusion[VerificationEntity::VERIFICATION_HASH] =
						$entity->getHash( VerificationEntity::VERIFICATION_HASH );
				}
			}

			$this->hashMap[] = $transclusion;
		}
	}
}
