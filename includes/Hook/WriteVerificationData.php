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
use DatabaseUpdater;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
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
	PageMoveCompleteHook,
	LoadExtensionSchemaUpdatesHook
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
		if ( $revisionRecord->hasSlot( SignatureContent::SLOT_ROLE_SIGNATURE ) ) {
			// Do not insert shell data on signature updates
			return;
		}

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
		/**
		 * We check if a comment 'revision imported' or 'revisions imported' is
		 * present which indicates that we are importing a page, if we import a
		 * page we do not run the hook, we would prefer a upgraded version of
		 * the revision object which allows us to capture the context to make
		 * this hack unnecessary
		 */
		// This retrieved half-empty entity set in the previous step
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

	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$revIds = $this->verificationEngine->getLookup()->getAllRevisionIds( $old );
		foreach ( $revIds as $revId ) {
			$entity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $revId );
			if ( $entity instanceof VerificationEntity ) {
				$this->verificationEngine->getLookup()->updateEntity( $entity, [
					// TODO: Should be getPrefixedDbKey()
					'page_title' => $new->getPrefixedText(),
					'page_id' => $pageid
				] );
			}
		}
	}

	/**
	 * Register our database schema.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = dirname( __DIR__ );

		$updater->addExtensionTable(
			'revision_verification',  "$base/sql/data_accounting.sql"
		);
		$updater->addExtensionTable(
			'witness_events',  "$base/sql/data_accounting.sql"
		);
		$updater->addExtensionTable(
			'witness_page',  "$base/sql/data_accounting.sql"
		);
		$updater->addExtensionTable(
			'witness_merkle_tree',  "$base/sql/data_accounting.sql"
		);
		$updater->addExtensionTable(
			'da_settings',  "$base/sql/data_accounting.sql"
		);

		// TODO: Register patches
	}
}
