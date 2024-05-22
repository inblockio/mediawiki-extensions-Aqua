<?php

namespace DataAccounting\Action;

use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use HistoryAction as GenericHistoryAction;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use Page;

class HistoryAction extends GenericHistoryAction {

	/**
	 * @var RevisionLookup
	 */
	private $revisionLookup;

	/**
	 * @var VerificationEngine
	 */
	private $verificationEngine;

	/**
	 * @param Page $page
	 * @param IContextSource|null $context
	 */
	public function __construct( Page $page, IContextSource $context = null ) {
		parent::__construct( $page, $context );
		$this->revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$this->verificationEngine = MediaWikiServices::getInstance()->getService( 'DataAccountingVerificationEngine' );
	}

	/**
	 * @return void
	 */
	public function onView(): void {
		$testEntity = $this->verificationEngine->getLookup()->verificationEntityFromTitle( $this->getTitle() );
		if ( !$testEntity ) {
			// Not a verification page
			parent::onView();
			return;
		}
		$this->getOutput()->addModules( [ 'ext.DataAccounting.inbox.compare' ] );
		$revisions = $this->verificationEngine->getLookup()->getAllRevisionIds( $this->getTitle() );
		foreach ( $revisions as $revId ) {
			$entity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $revId );
			if ( !$entity ) {
				continue;
			}
			$this->getOutput()->addHTML( $this->makeRevisionLine( $entity ) );
		}
	}

	/**
	 * @param VerificationEntity $entity
	 * @return string
	 */
	private function makeRevisionLine( VerificationEntity $entity ): string {
		if ( $entity->getTitle()->getArticleID() !== $this->getTitle()->getArticleID() ) {
			$source = 'parent';
		} else {
			$source = 'local';
		}

		$url = $entity->getTitle()->getLocalURL( [ 'oldid' => $entity->getRevision()->getId() ] );
		if ( $source === 'local' && $entity->getRevision()->isCurrent() ) {
			$url = $this->getTitle()->getLocalURL();
		}
		$label = $this->getSkin()->getLanguage()->userTimeAndDate(
			$entity->getRevision()->getTimestamp(), $this->getUser()
		);
		$link = Html::element( 'a', [ 'href' => $url ], $label );

		$revisionUser = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity(
			$entity->getRevision()->getUser()
		);
		$userLink = Html::element(
			'a',
			[ 'href' => $revisionUser->getUserPage()->getLocalURL() ],
			$revisionUser->getName()
		);

		$nodeClass = 'da-compare-node-graph da-compare-node-graph-' . $source;
		if ( $source === 'local' ) {
			$node = $this->getNodePlaceholder() . Html::element( 'span', [ 'class' => $nodeClass ] );
		} else {
			$node = Html::element( 'span', [ 'class' => $nodeClass ] ) . $this->getNodePlaceholder();
		}

		return Html::rawElement( 'div', [
			'class' => 'da-compare-node',
		], $node . $link . $userLink );
	}

	/**
	 * @return string
	 */
	private function getNodePlaceholder(): string {
		return Html::element( 'span', [ 'class' => 'da-compare-node-graph da-compare-node-graph-placeholder' ] );
	}
}
