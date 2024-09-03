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
	/** @var bool */
	private $isDiverged = false;
	/** @var bool */
	private $mergeHash = false;

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
		$revisions = $this->verificationEngine->getLookup()->getAllRevisionIds( $this->getTitle(), false );
		$firstRevId = $revisions[0] ?? null;
		$firstEntity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $firstRevId );
		$startingFromLocal = $firstEntity && $this->isLocal( $firstEntity );

		$lines = [];
		foreach ( $revisions as $revId ) {
			$entity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $revId );
			if ( !$entity ) {
				continue;
			}
			$lines[] = $this->makeRevisionLine( $entity, !$startingFromLocal );
		}
		$html .= implode( '', array_reverse( $lines ) );

		$html .= Html::closeElement( 'div' );
		$this->getOutput()->addHTML( $html );
		$this->getOutput()->addModules( [ "ext.DataAccounting.revisionHistory" ] );
	}

	/**
	 * @param VerificationEntity $entity
	 * @param bool $isForked
	 * @return string
	 */
	private function makeRevisionLine(
		VerificationEntity $entity, bool $isForked
	): string {
		$parent = $this->verificationEngine->getLookup()->verificationEntityFromHash(
			$entity->getHash( VerificationEntity::PREVIOUS_VERIFICATION_HASH )
		);
		$this->checkDivergance( $entity );
		if ( $entity->getHash( VerificationEntity::MERGE_HASH ) ) {
			$this->isDiverged = false;
			$this->mergeHash = $entity->getHash( VerificationEntity::MERGE_HASH );
		}
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

		$checkbox = $this->getCheckbox( $entity, $isForked ? $isLocal : true );
		if ( !$isLocal ) {
			$source = 'other';
			$sourceClass = 'local';
		} else {
			$source = 'local';
			$sourceClass = 'remote';
		}
		if ( !$isForked ) {
			// Reverse columns of tree
			$source = $this->flip( $source );
			$sourceClass = $this->flip( $sourceClass );
		}
		$parentHash = [];
		if ( $parent ) {
			$parentHash[] = $parent->getHash();
		}
		if ( $this->mergeHash ) {
			$parentHash[] = $this->mergeHash;
		}
		$nodeAttrs = [
			'data-hash' => $entity->getHash(),
			'data-parent' => implode( ',', $parentHash ),
			'class' => 'da-compare-node-graph da-compare-node-graph-' . $sourceClass,
		];
		if ( $source === 'local' ) {
			$node = $this->getNodePlaceholder() . Html::element( 'span', $nodeAttrs );
		} else {
			$node = Html::element( 'span', $nodeAttrs ) . $this->getNodePlaceholder();

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
	 * @throws \Exception
	 */
	private function isLocal( VerificationEntity $entity ): bool {
		return $entity->getTitle()->getArticleID() === $this->getTitle()->getArticleID()
			&& $entity->getDomainId() === $this->verificationEngine->getDomainId();
	}

	/**
	 * @return string
	 */
	private function getNodePlaceholder(): string {
		return Html::element( 'span', [ 'class' => 'da-compare-node-graph da-compare-node-graph-placeholder' ] );
	}

	/**
	 * @param VerificationEntity $entity
	 * @param bool $shouldAdd
	 * @return string
	 */
	private function getCheckbox( VerificationEntity $entity, bool $shouldAdd ): string {
		$html = Html::openElement( 'div', [ 'class' => 'da-revision-checkbox' ] );
		if ( $shouldAdd ) {

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

	/**
	 * @param string $type
	 * @return string
	 */
	private function flip( string $type ): string {
		return $type === 'local' ? 'remote' : 'local';
	}

	/**
	 * @param VerificationEntity $entity
	 * @return void
	 */
	private function checkDivergance( VerificationEntity $entity ) {
		if ( !empty( $entity->getHash( VerificationEntity::FORK_HASH ) ) ) {
			$this->isDiverged = true;
			$this->mergeHash = false;
		}
	}
}
