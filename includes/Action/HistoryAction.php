<?php

namespace DataAccounting\Action;

use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use HistoryAction as GenericHistoryAction;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use OOUI\ButtonWidget;
use Page;

class HistoryAction extends GenericHistoryAction {

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
		$this->getOutput()->enableOOUI();

		$html = Html::openElement( 'div', [ 'id' => 'da-revision-history' ] );
		$html .= $this->getCompareButton();
		$revisions = $this->verificationEngine->getLookup()->getAllRevisionIds( $this->getTitle() );
		$firstRevId = array_key_last( $revisions );
		$firstEntity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $firstRevId );
		$startingFromLocal = $firstEntity && $this->isLocal( $firstEntity );
		// Show newest first
		$revisions = array_reverse( $revisions );
		$parent = null;

		foreach ( $revisions as $revId ) {
			$entity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $revId );
			if ( !$entity ) {
				continue;
			}
			$html .= $this->makeRevisionLine( $entity, $parent, !$startingFromLocal );
			$parent = $entity;
		}
		$html .= Html::closeElement( 'div' );
		$this->getOutput()->addHTML( $html );
		$this->getOutput()->addModules( [ "ext.DataAccounting.revisionHistory" ] );
	}

	/**
	 * @param VerificationEntity $entity
	 * @param VerificationEntity|null $parent
	 * @param bool $isForked
	 * @return string
	 */
	private function makeRevisionLine(
		VerificationEntity $entity, ?VerificationEntity $parent, bool $isForked
	): string {
		$isLocal = $this->isLocal( $entity );

		$url = $entity->getTitle()->getLocalURL( [ 'oldid' => $entity->getRevision()->getId() ] );
		if ( $isLocal && $entity->getRevision()->isCurrent() ) {
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
			[ 'href' => $revisionUser->getUserPage()->getLocalURL(), 'class' => 'da-revision-user' ],
			$revisionUser->getName()
		);
		$userHtml = Html::rawElement( 'span', [ 'class' => 'da-revision-user' ], 'Created by: ' . $userLink );
		$revComment = $entity->getRevision()->getComment();

		$commentHtml = '';
		if ( $revComment && $revComment->text ) {
			$commentHtml = Html::element(
				'span', [ 'class' => 'da-revision-comment' ],
				'(' . $revComment->text . ')'
			);
		}

		$node = '';
		$checkbox = $this->getCheckbox( $entity, $isLocal );
		if ( $isForked ) {
			if ( !$isLocal ) {
				$source = 'parent';
			} else {
				$source = 'local';
			}
			// In order for the graph to work, branches must be named differently
			$sourceClass = $source === 'parent' ? 'local' : 'remote';
			$nodeAttrs = [
				'data-hash' => $entity->getHash(),
				'data-parent' => $parent ? $parent->getHash() : '',
				'class' => 'da-compare-node-graph da-compare-node-graph-' . $sourceClass,
			];
			if ( $source === 'local' ) {
				$node = $this->getNodePlaceholder() . Html::element( 'span', $nodeAttrs );
			} else {
				$node = Html::element( 'span', $nodeAttrs ) . $this->getNodePlaceholder();
			}
		}

		$textHtml = Html::rawElement(
			'div', [ 'class' => 'da-revision-text' ],
			$link . $userHtml . $commentHtml
		);
		return Html::rawElement( 'div', [
			'class' => 'da-compare-node',
		],  $node . $checkbox . $textHtml );
	}

	/**
	 * @param VerificationEntity $entity
	 * @return bool
	 */
	private function isLocal( VerificationEntity $entity ): bool {
		return $entity->getTitle()->getArticleID() === $this->getTitle()->getArticleID();
	}

	/**
	 * @return string
	 */
	private function getNodePlaceholder(): string {
		return Html::element( 'span', [ 'class' => 'da-compare-node-graph da-compare-node-graph-placeholder' ] );
	}

	/**
	 * @param VerificationEntity $entity
	 * @param bool $isLocal
	 * @return string
	 */
	private function getCheckbox( VerificationEntity $entity, bool $isLocal ): string {
		$html = Html::openElement( 'div', [ 'class' => 'da-revision-checkbox' ] );
		if ( $isLocal ) {
			$html .= Html::check(
				'da-revision-checkbox',
				false,
				[ 'data-hash' => $entity->getHash(), 'data-rev-id' => $entity->getRevision()->getId() ]
			);
		}
		$html .= Html::closeElement( 'div' );
		return $html;
	}

	/**
	 * @return string
	 */
	private function getCompareButton(): string {
		$btn = new ButtonWidget( [
			'id' => 'da-compare-button',
			'label' => 'Compare selected revisions',
			'disabled' => true,
			'flags' => [ 'primary', 'progressive' ],
			'classes' => [ 'da-compare-button' ],
			'infusable' => true,
		] );

		return Html::rawElement( 'div', [ 'class' => 'da-header-buttons' ], $btn->toString() );
	}

}
