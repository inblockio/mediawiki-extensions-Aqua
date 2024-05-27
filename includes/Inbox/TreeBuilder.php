<?php

namespace DataAccounting\Inbox;

use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use Exception;
use Language;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\UserIdentity;
use Title;

class TreeBuilder {

	/** @var VerificationEngine */
	private $verificationEngine;

	/** @var RevisionLookup */
	private $revisionLookup;

	/**
	 * @param VerificationEngine $verificationEngine
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct( VerificationEngine $verificationEngine, RevisionLookup $revisionLookup ) {
		$this->verificationEngine = $verificationEngine;
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * @param Title $remote
	 * @param Title $local
	 * @param Language $language
	 * @param UserIdentity $user
	 *
	 * @return array
	 * @throws Exception
	 */
	public function buildPreImportTree( Title $remote, Title $local, Language $language, UserIdentity $user ): array {
		$this->assertSameGenesis( $remote, $local );
		$remoteEntities = $this->verificationEngine->getLookup()->allVerificationEntitiesFromQuery( [
			'page_id' => $remote->getArticleID()
		] );
		$remoteEntities = $this->verifyAndReduce( $remoteEntities );
		$localEntities = $this->verificationEngine->getLookup()->allVerificationEntitiesFromQuery( [
			'page_id' => $local->getArticleID()
		] );
		$localEntities = $this->verifyAndReduce( $localEntities );

		$combined = $this->combine( $remoteEntities, $localEntities, $language, $user );
		$combined['remote'] = $remote;
		$combined['local'] = $local;
		return $combined;
	}

	/**
	 * @param Title $remote
	 * @param Title $local
	 *
	 * @return void
	 * @throws Exception
	 */
	private function assertSameGenesis( Title $remote, Title $local ) {
		$remote = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $remote );
		$local = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $local );
		$good = $remote && $local &&
			$remote->getHash( VerificationEntity::GENESIS_HASH ) ===
			$local->getHash( VerificationEntity::GENESIS_HASH );
		if ( !$good ) {
			throw new Exception( 'Source and target pages invalid or have different genesis hashes' );
		}
	}

	/**
	 * @param array $remote
	 * @param array $local
	 * @param Language $language
	 * @param UserIdentity $user
	 *
	 * @return array
	 */
	private function combine( array $remote, array $local, Language $language, UserIdentity $user ): array {
		$hasLocalChange = $hasRemoteChange = false;
		$combined = [];
		foreach ( $local as $hash => $data ) {
			$combined[$hash] = [
				'revision' => $data['revision'],
				'diff' => true,
				'source' => 'local',
				'parent' => $data['parent'],
				'domain' => $data['domain']
			];
			if ( isset( $remote[$hash] ) ) {
				$combined[$hash]['diff'] = false;
			} else {
				$hasLocalChange = true;
			}
		}
		foreach ( $remote as $hash => $data ) {
			if ( !isset( $combined[$hash] ) ) {
				$combined[$hash] = [
					'revision' => $data['revision'],
					'diff' => true,
					'source' => 'remote',
					'parent' => $data['parent'],
					'domain' => $data['domain']
				];
				$hasRemoteChange = true;
			}
		}

		$this->decorateWithRevisionData( $combined, $language, $user );
		uasort( $combined, static function ( array $a, array $b ) {
			// Sort by revision timestamp descending
			return $b['revisionData']['timestamp_raw'] <=> $a['revisionData']['timestamp_raw'];
		} );

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
			if ( $lastHash !== $entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ) ) {
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

	private function decorateWithRevisionData( array &$combined, Language $language, UserIdentity $user ) {
		foreach ( $combined as &$entry ) {
			$revision = $this->revisionLookup->getRevisionById( $entry['revision'] );
			if ( !$revision ) {
				continue;
			}
			$title = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );
			$entry['revisionData'] = [
				'timestamp_raw' => $revision->getTimestamp(),
				'timestamp' => $language->userTimeAndDate( $revision->getTimestamp(), $user ),
				'url' => $title->getFullURL( [ 'oldid' => $revision->getId() ] ),
			];
		}
	}
}
