<?php

namespace DataAccounting\Hook;

use DataAccounting\TransclusionManager;
use DataAccounting\Verification\VerificationEntity;
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
	/** @var TransclusionManager */
	private TransclusionManager $transclusionManager;
	/** @var RevisionStore */
	private RevisionStore $revisionStore;
	/** @var RepoGroup */
	private RepoGroup $repoGroup;

	/**
	 * @param TransclusionManager $transclusionManager
	 * @param RevisionStore $revisionStore
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		TransclusionManager $transclusionManager, RevisionStore $revisionStore, RepoGroup $repoGroup
	) {
		$this->transclusionManager = $transclusionManager;
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
		if ( !$file || !$file->isLocal() ) {
			// Foreign file => cannot handle
			return true;
		}

		$revision = $this->getRevision( $page );
		if ( !$revision ) {
			return true;
		}

		$hashContent = $this->transclusionManager->getTransclusionHashesContent( $revision );
		if ( !$hashContent ) {
			return true;
		}

		$transclusionInfo = $hashContent->getTransclusionDetails( $nt );
		if ( $transclusionInfo === false || $transclusionInfo->{VerificationEntity::CONTENT_HASH} === null ) {
			// Image did not exist at the time of hashing, or not listed => show broken link
			$options['broken'] = true;
			return true;
		}

		$oldFile = $this->transclusionManager->getFileForResource( $transclusionInfo, $file );
		if ( !$oldFile ) {
			return true;
		}
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

	/**
	 * @param LinkTarget|null $contextTitle
	 * @param LinkTarget $title
	 * @param bool $skip
	 * @param RevisionRecord|null $revRecord
	 * @return bool|void
	 */
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
		$content = $this->transclusionManager->getTransclusionHashesContent( $revision );
		if ( !$content ) {
			return;
		}
		$transclusionInfo = $content->getTransclusionDetails( $title );
		if ( !$transclusionInfo ) {
			$skip = true;
			return;
		}

		$revRecord = $this->transclusionManager->getRevisionForResource( $transclusionInfo );
		if ( $revRecord === null ) {
			$skip = true;
		}
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
}
