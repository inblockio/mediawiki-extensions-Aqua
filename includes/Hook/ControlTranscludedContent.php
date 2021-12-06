<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\TransclusionHashes;
use DataAccounting\HashLookup;
use MediaWiki\Hook\BeforeParserFetchFileAndTitleHook;
use MediaWiki\Hook\BeforeParserFetchTemplateRevisionRecordHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use Parser;
use Title;

class ControlTranscludedContent implements BeforeParserFetchTemplateRevisionRecordHook, BeforeParserFetchFileAndTitleHook {
	/** @var HashLookup */
	private HashLookup $hashLookup;
	/** @var RevisionStore */
	private RevisionStore $revisionStore;

	/**
	 * @param HashLookup $hashLookup
	 */
	public function __construct( HashLookup $hashLookup, RevisionStore $revisionStore ) {
		$this->hashLookup = $hashLookup;
		$this->revisionStore = $revisionStore;
	}

	public function onBeforeParserFetchFileAndTitle( $parser, $nt, &$options, &$descQuery ) {
		// TODO: Implement onBeforeParserFetchFileAndTitle() method.
	}

	public function onBeforeParserFetchTemplateRevisionRecord(
		?LinkTarget $contextTitle, LinkTarget $title, bool &$skip, ?RevisionRecord &$revRecord
	) {
		if ( !$contextTitle ) {
			error_log( __LINE__ );
			return;
		}
		$revision = $this->getRevision( $contextTitle );
		if ( !$revision ) {
			error_log( __LINE__ );
			return;
		}
		$hashes = $this->getHashesForRevision( $revision );
		if ( empty( $hashes ) ) {
			error_log( __LINE__ );
			return;
		}
		$hash = $this->getHashForTitle( $hashes, $title );
		if ( !$hash === null ) {
			$skip = true;
			return;
		}
		$revRecord = $this->hashLookup->getRevisionForHash( $hash );
	}

	private function getRevision( LinkTarget $title ): ?RevisionRecord {
		$oldid = \RequestContext::getMain()->getRequest()->getInt( 'oldid', 0 );
		if ( !$oldid ) {
			return $this->revisionStore->getRevisionByTitle( $title );
		}
		return $this->revisionStore->getRevisionById( $oldid );
	}

	private function getHashesForRevision( RevisionRecord $revision ) {
		$content = $revision->getContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES );
		if ( !$content instanceof TransclusionHashes ) {
			return [];
		}
		return $content->getResourceHashes();
	}

	private function getHashForTitle( array $hashes, LinkTarget $title ) {
		foreach ( $hashes as $hashEntity ) {
			if ( $title->getNamespace() === $hashEntity->ns && $title->getDBkey() === $hashEntity->dbkey ) {
				return $hashEntity->hash;
			}
		}

		return nulL;
	}
}
