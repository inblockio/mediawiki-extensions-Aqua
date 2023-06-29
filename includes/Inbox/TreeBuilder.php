<?php

namespace DataAccounting\Inbox;

use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use Exception;
use Title;

class TreeBuilder {

	/** @var VerificationEngine */
	private $verificationEngine;

	/**
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct( VerificationEngine $verificationEngine ) {
		$this->verificationEngine = $verificationEngine;
	}
	/**
	 * @param Title $source
	 * @param Title $target
	 *
	 * @return array
	 */
	public function buildPreImportTree( Title $remote, Title $local ): array {
		$this->assertSameGenesis( $remote, $local );
		$remoteEntities = $this->verificationEngine->getLookup()->allVerificationEntitiesFromQuery( [
			'page_id' => $remote->getArticleID()
		] );
		$remoteEntities = $this->verifyAndReduce( $remoteEntities );
		$localEntities = $this->verificationEngine->getLookup()->allVerificationEntitiesFromQuery( [
			'page_id' => $local->getArticleID()
		] );
		$localEntities = $this->verifyAndReduce( $localEntities );

		return $this->combine( $remoteEntities, $localEntities );
	}

	private function assertSameGenesis( Title $remote, Title $local ) {
		$remote = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $remote );
		$local = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $local );
		assert(
			$remote && $local &&
			( $remote->getHash( VerificationEntity::GENESIS_HASH ) ===
				$local->getHash( VerificationEntity::GENESIS_HASH )
			),
			new Exception( 'Source and target pages invalid or have different genesis hashes' )
		);
	}

	/**
	 * @param array $remote
	 * @param array $local
	 *
	 * @return array
	 */
	private function combine( array $remote, array $local ): array {
		$hasLocalChange = $hasRemoteChange = false;
		$combined = [];
		foreach ( $local as $hash => $data ) {
			$combined[$hash] = [
				'revisions' => [ $data['revision'] ],
				'diff' => true,
				'source' => 'local',
				'parent' => $data['parent'],
			];
			if ( isset( $remote[$hash] ) ) {
				$combined[$hash]['revisions'][] = $remote[$hash]['revision'];
				$combined[$hash]['diff'] = false;
			} else {
				$hasLocalChange = true;
			}
		}
		foreach ( $remote as $hash => $data ) {
			if ( !isset( $combined[$hash] ) ) {
				$combined[$hash] = [
					'revisions' => [ $data['revision'] ],
					'diff' => true,
					'source' => 'remote',
					'parent' => $data['parent'],
				];
				$hasRemoteChange = true;
			}
		}

		return [
			'tree' => $combined,
			'change-type' => ( $hasLocalChange && $hasRemoteChange ) ?
				'both' : ( $hasLocalChange ?
					'local' : ( $hasRemoteChange ? 'remote' : 'none' ) ),
		];
	}

	/**
	 * @param VerificationEntity[] $entities
	 *
	 * @return array [hash => revisionId]
	 * @throws Exception
	 */
	private function verifyAndReduce( array $entities ): array {
		$verified = [];
		$lastHash = '';
		usort( $entities, function( VerificationEntity $a, VerificationEntity $b ) {
			return $a->getRevision()->getId() <=> $b->getRevision()->getId();
		} );
		foreach ( $entities as $entity ) {
			//var_dump( [ $entity->getTitle()->getPrefixedText(), $lastHash, $entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ) ] );
			if ( $lastHash !== $entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH  ) ){
				throw new Exception( 'Entities are not in order' );
			}
			$lastHash = $entity->getHash();
			$verified[$lastHash] = [
				'page_id' => $entity->getTitle()->getArticleID(),
				'revision' => $entity->getRevision()->getId(),
				'parent' => $entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ),
				'domain' => $entity->getDomainId(),
			];
		}

		return $verified;
	}
}