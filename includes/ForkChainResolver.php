<?php

namespace DataAccounting;

use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationLookup;
use MediaWiki\Revision\RevisionRecord;

class ForkChainResolver {

	/** @var VerificationLookup */
	private $lookup;

	/**
	 * @param VerificationLookup $lookup
	 */
	public function __construct( VerificationLookup $lookup ) {
		$this->lookup = $lookup;
	}

	/**
	 * @param VerificationEntity $entity
	 * @return array|null
	 */
	public function resolveFromEntity( VerificationEntity $entity ): ?array {
		$foreignParent = $this->getForeignParent( $entity );
		if ( !$foreignParent ) {
			return null;
		}
		$parents = [];
		$parent = $foreignParent;
		while ( $parent ) {
			$parents[] = $parent;
			if ( !$parent->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ) ) {
				break;
			}
			$parent = $this->lookup->verificationEntityFromHash(
				$parent->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH )
			);
		}

		// Sort by rev_id
		return array_reverse( $parents );
	}

	/**
	 * @param RevisionRecord $revision
	 * @return array|null
	 */
	public function resolveFromRevision( RevisionRecord $revision ): ?array {
		return $this->resolveFromEntity(
			$this->lookup->verificationEntityFromRevId( $revision->getId() )
		);
	}

	/**
	 * @param int $revId
	 * @return array|null
	 */
	public function resolveFromRevisionId( int $revId ): ?array {
		return $this->resolveFromEntity( $this->lookup->verificationEntityFromRevId( $revId ) );
	}

	/**
	 * Get parent VerificationEntity if its not the same page
	 * @param VerificationEntity $entity
	 * @return VerificationEntity|null
	 */
	private function getForeignParent( VerificationEntity $entity ): ?VerificationEntity {
		if ( !$entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH ) ) {
			// First rev - no parent
			return null;
		}
		$parent = $this->lookup->verificationEntityFromHash(
			$entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH )
		);
		if ( !$parent || $parent->getTitle()->getArticleID() === $entity->getTitle()->getArticleID() ) {
			return null;
		}

		return $parent;
	}
}
