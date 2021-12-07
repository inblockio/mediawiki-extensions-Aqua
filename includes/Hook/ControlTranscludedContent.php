<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\TransclusionHashes;
use DataAccounting\HashLookup;
use MediaWiki\Hook\BeforeParserFetchFileAndTitleHook;
use MediaWiki\Hook\BeforeParserFetchTemplateRevisionRecordHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use Parser;
use RepoGroup;
use Title;

class ControlTranscludedContent implements BeforeParserFetchTemplateRevisionRecordHook, BeforeParserFetchFileAndTitleHook {
	/** @var HashLookup */
	private HashLookup $hashLookup;
	/** @var RevisionStore */
	private RevisionStore $revisionStore;
	/** @var RepoGroup */
	private RepoGroup $repoGroup;

	/**
	 * @param HashLookup $hashLookup
	 * @param RevisionStore $revisionStore
	 * @param RepoGroup $repoGroup
	 */
	public function __construct( HashLookup $hashLookup, RevisionStore $revisionStore, RepoGroup $repoGroup ) {
		$this->hashLookup = $hashLookup;
		$this->revisionStore = $revisionStore;
		$this->repoGroup = $repoGroup;
	}

	/**
	 * @param Parser $parser
	 * @param Title $nt
	 * @param array $options
	 * @param string $descQuery
	 * @return bool
	 */
	public function onBeforeParserFetchFileAndTitle( $parser, $nt, &$options, &$descQuery ) {
		if ( !( $parser instanceof Parser ) ) {
			return true;
		}
		$page = $parser->getPage();
		if ( $page === null ) {
			return true;
		}
		if ( $nt->getNamespace() !== NS_FILE ) {
			return true;
		}

		$file = $this->repoGroup->findFile( $nt );
		if ( $file && !$file->isLocal() ) {
			// Foreign file => cannot handle
			return true;
		}

		$revision = $this->getRevision( $page );
		if ( !$revision ) {
			return true;
		}

		$hashes = $this->getHashesForRevision( $revision );
		$hash = $this->getHashForTitle( $hashes, $nt );
		if ( $hash === null ) {
			// Image did not exist at the time of hashing, show broken link
			$options['broken'] = true;
			return true;
		}

		$oldFile = $this->hashLookup->getFileForHash( $hash, $file );
		if ( $oldFile->getSha1() === $file->getSha1() && $oldFile->getTimestamp() === $file->getTimestamp() ) {
			// Showing latest
			return true;
		}
		if ( !$oldFile ) {
			// Cannot find revision for this hash
			$options['broken'] = true;
			return true;
		}
		$options['time'] = $oldFile->getTimestamp();
		$options['sha1'] = $oldFile->getSha1();

		return true;
	}

	public function onBeforeParserFetchTemplateRevisionRecord(
		?LinkTarget $contextTitle, LinkTarget $title, bool &$skip, ?RevisionRecord &$revRecord
	) {
		if ( !$contextTitle ) {
			return;
		}
		$revision = $this->getRevision( $contextTitle );
		if ( !$revision ) {
			return;
		}
		$hashes = $this->getHashesForRevision( $revision );
		if ( empty( $hashes ) ) {
			return;
		}
		$hash = $this->getHashForTitle( $hashes, $title );
		if ( $hash === null ) {
			$skip = true;
			return;
		}
		$revRecord = $this->hashLookup->getRevisionForHash( $hash );
	}

	/**
	 * @param LinkTarget|PageReference $title
	 * @return RevisionRecord|null
	 */
	private function getRevision( $title ): ?RevisionRecord {
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

	/**
	 * @param array $hashes
	 * @param LinkTarget|PageReference $title
	 * @return string|null
	 */
	private function getHashForTitle( array $hashes, $title ): ?string {
		foreach ( $hashes as $hashEntity ) {
			if ( $title->getNamespace() === $hashEntity->ns && $title->getDBkey() === $hashEntity->dbkey ) {
				return $hashEntity->hash;
			}
		}

		return null;
	}
}
