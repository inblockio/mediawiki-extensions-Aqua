<?php

namespace DataAccounting\Hook;

use DataAccounting\Content\TransclusionHashes;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleRevisionViewCustomHook;
use MediaWiki\Revision\RevisionRecord;
use OutputPage;
use Title;

class ImmutableLinks implements ArticleRevisionViewCustomHook, HtmlPageLinkRendererBeginHook {
	/** @var VerificationEngine */
	private $verificationEngine;

	/** @var Title|null */
	private $contextTitle = null;
	/** @var RevisionRecord|null */
	private $contextRevision = null;
	/** @var TransclusionHashes|null */
	private $hashContent = null;

	/**
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct( VerificationEngine $verificationEngine ) {
		$this->verificationEngine = $verificationEngine;
	}

	/**
	 * @inheritDoc
	 */
	public function onArticleRevisionViewCustom( $revision, $title, $oldid, $output ) {
		$this->contextTitle = $title;
		$this->contextRevision = $revision;
		$hash = $output->getContext()->getRequest()->getText( 'version', null );
		if ( !$hash || $hash === 'latest' ) {
			return true;
		}

		$entity = $this->verificationEngine->getLookup()->verificationEntityFromHash( $hash );
		if ( !$entity ) {
			$this->addError( 'da-revision-replacer-error-no-entity', $output );
			return false;
		}
		if ( !$entity->getTitle()->equals( $title ) ) {
			$this->addError( 'da-revision-replacer-error-title-mismatch', $output );
			return false;
		}

		$revision = $entity->getRevision();
		if ( $revision->getId() === $oldid ) {
			// If oldid was already requested, just show that
			return true;
		}
		if ( $revision->getId() === $entity->getTitle()->getLatestRevID() ) {
			// If requested hash points to the latest revision, do not intervene
			return true;
		}

		$r = MediaWikiServices::getInstance()->getRevisionRenderer()->getRenderedRevision(
			$revision
		);

		$this->contextRevision = $revision;
		$output->addHTML(
			\Html::element( 'div', [
				'class' => 'alert alert-warning',
				'role' => 'alert',
				'title' => $hash
			], $output->getContext()->msg(
				'da-revision-replacer-notice',
				substr( $hash, 0, 10 ) . '...' . substr( $hash, -10 ),
				$revision->getId()
			)->text() )
		);
		$output->setRevisionId( $revision->getId() );
		$output->setRevisionTimestamp( $revision->getTimestamp() );
		$output->addParserOutput( $r->getRevisionParserOutput() );
		return false;
	}

	/**
	 * @param string $key
	 * @param OutputPage $output
	 */
	private function addError( string $key, OutputPage $output ) {
		$output->addWikiMsg( $key );
	}

	/**
	 * @inheritDoc
	 */
	public function onHtmlPageLinkRendererBegin(
		$linkRenderer, $target, &$text, &$customAttribs, &$query, &$ret
	) {
		if ( isset( $customAttribs['version'] ) ) {
			return true;
		}
		if ( isset( $customAttribs['action'] ) ) {
			return true;
		}
		if ( !$this->contextTitle || !$this->contextRevision ) {
			return true;
		}
		if (
			!$this->hashContent &&
			$this->contextRevision->hasSlot( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES )
		) {
			/** @var TransclusionHashes $hashContent */
			$this->hashContent = $this->contextRevision->getContent(
				TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES
			);
		}
		if ( !$this->hashContent ) {
			return true;
		}

		$details = $this->hashContent->getTransclusionDetails( $target );
		if ( !$details ) {
			return true;
		}
		if ( $details->{VerificationEntity::VERIFICATION_HASH} !== null ) {
			$query['version'] = $details->{VerificationEntity::VERIFICATION_HASH};
		} else {
			$ret = \HtmlArmor::getHtml( $text );
			return false;
		}

		return true;
	}
}
