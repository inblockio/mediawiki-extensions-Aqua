<?php
/**
 * Hooks for Example extension.
 *
 * @file
 */

namespace DataAccounting;

use DatabaseUpdater;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use MediaWiki\Page\Hook\RevisionFromEditCompleteHook;
use MediaWiki\Revision\Hook\RevisionRecordInsertedHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use WikiPage;

require_once 'Util.php';
require_once 'ApiUtil.php';

class HashWriterHooks implements
	RevisionFromEditCompleteHook,
	RevisionRecordInsertedHook,
	ArticleDeleteCompleteHook,
	PageMoveCompleteHook
{

	// This function updates the dataset wit the correct revision ID, especially important during import.
	// https://github.com/inblockio/DataAccounting/commit/324cd13fadb1daed281c2df454268a7b1ba30fcd
	public function onRevisionRecordInserted( $revisionRecord ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );
		$table_name = 'revision_verification';
		$data = [
			'page_title' => $revisionRecord->getPageAsLinkTarget(),
			'page_id' => $revisionRecord->getPageId(),
			'rev_id' => $revisionRecord->getID(),
			'time_stamp' => $revisionRecord->getTimestamp(),
		];

		$dbw->insert( $table_name, $data, __METHOD__ );
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
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$dbw->update(
			'revision_verification',
			DataAccountingFactory::getInstance()->newRevisionVerificationBuilder()->buildVerificationData( $rev ),
			[ 'rev_id' => $rev->getID() ],
			__METHOD__
		);
	}

	public function onArticleDeleteComplete( $wikiPage, $user, $reason, $id,
		$content, $logEntry, $archivedRevisionCount
	) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );
		$dbw->delete( 'revision_verification', [ 'page_id' => $id ], __METHOD__ );
	}

	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		// We use getPrefixedText() so that it uses spaces for the namespace,
		// not underscores.
		$old_title = $old->getPrefixedText();
		$new_title = $new->getPrefixedText();
		echo json_encode( [ $old_title, $new_title ] );
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_MASTER );
		$dbw->update(
			'revision_verification',
			[ 'page_title' => $new_title ],
			[
				'page_title' => $old_title,
				'page_id' => $pageid ],
			__METHOD__
		);
	}
}
