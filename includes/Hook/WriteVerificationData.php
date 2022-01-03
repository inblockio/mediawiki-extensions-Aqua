<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace DataAccounting\Hook;

use DataAccounting\Content\SignatureContent;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\Hook\PageMoveCompletingHook;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use MediaWiki\Page\Hook\RevisionFromEditCompleteHook;
use MediaWiki\Revision\Hook\RevisionRecordInsertedHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use WikiPage;

class WriteVerificationData implements
	RevisionFromEditCompleteHook,
	RevisionRecordInsertedHook,
	ArticleDeleteCompleteHook,
	PageMoveCompletingHook
{
	/** @var VerificationEngine */
	private $verificationEngine;

	/**
	 * @param VerificationEngine $engine
	 */
	public function __construct( VerificationEngine $engine ) {
		$this->verificationEngine = $engine;
	}

	// This function updates the dataset wit the correct revision ID, especially important during import.
	// https://github.com/inblockio/DataAccounting/commit/324cd13fadb1daed281c2df454268a7b1ba30fcd
	public function onRevisionRecordInserted( $revisionRecord ) {
		$this->verificationEngine->getLookup()->insertShellData( $revisionRecord );
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param RevisionRecord $rev
	 * @param int|bool $originalRevId
	 * @param UserIdentity $user
	 * @param string[] &$tags
	 */
	public function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
		// This retrieves half-empty entity set in the previous step
		$entity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $rev->getId() );
		if ( !$entity ) {
			return;
		}

		$this->verificationEngine->buildAndUpdateVerificationData( $entity, $rev );
	}

	public function onArticleDeleteComplete( $wikiPage, $user, $reason, $id,
		$content, $logEntry, $archivedRevisionCount
	) {
		$this->verificationEngine->getLookup()->clearEntriesForPage( $wikiPage->getTitle() );
	}

	public function onPageMoveCompleting( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		// We use onPageMoveCompleting instead of onPageMoveComplete because
		// the latter caused problem during an XML import of a title that
		// already exists in the wiki. The existing page is then renamed by
		// appending the chain height and timestamp to its title, but
		// unfortunately, the steps to update the DA table happens after the
		// who import has finished, causing inconsistency.
		$revIds = $this->verificationEngine->getLookup()->getAllRevisionIds( $old );
		// If the page is moved with the redirect enabled, a new page is
		// created with a new genesis hash. And so, we need to update the newly
		// created page's genesis hash with the original one. This is not
		// needed when there is no redirect, but the genesis hash override
		// doesn't override the case without redirect anyway.
		$oldGenesisHash = null;
		foreach ( $revIds as $revId ) {
			$entity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $revId );
			if ( $oldGenesisHash === null ) {
				$oldGenesisHash = $entity->getHash( VerificationEntity::GENESIS_HASH );
			}
			if ( $entity instanceof VerificationEntity ) {
				$this->verificationEngine->getLookup()->updateEntity( $entity, [
					// TODO: Should be getPrefixedDbKey()
					'page_title' => $new->getPrefixedText(),
					'page_id' => $pageid,
					'genesis_hash' => $oldGenesisHash,
				] );
			}
		}
	}
}
