<?php

namespace DataAccounting\Override\Revision;

use CommentStoreComment;
use DataAccounting\Content\FileVerificationContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDatabase;

class DARevisionStore extends RevisionStore {
	public function newNullRevision(
		IDatabase $dbw, PageIdentity $page, CommentStoreComment $comment, $minor, UserIdentity $user
	) {
		$revision = parent::newNullRevision( $dbw, $page, $comment, $minor, $user );
		if ( $page->getNamespace() !== NS_FILE ) {
			return $revision;
		}

		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
		$file = $repoGroup->findFile( $page );
		if ( !$file || !$file->isLocal() ) {
			return $revision;
		}

		$content = new FileVerificationContent( '' );
		$result = $content->setHashFromFile( $file );
		if ( !$result ) {
			return $revision;
		}
		$revision->setContent( FileVerificationContent::SLOT_ROLE_FILE_VERIFICATION, $content );
		return $revision;
	}
}
