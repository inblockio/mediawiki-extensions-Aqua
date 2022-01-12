<?php

namespace DataAccounting;

use DataAccounting\Content\TransclusionHashes;
use DataAccounting\Transfer\Exporter;
use DataAccounting\Transfer\ExportSpecification;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\Revision\RevisionStore;
use Message;
use OOUI\ButtonInputWidget;
use OOUI\CheckboxInputWidget;
use OOUI\FieldLayout;
use OOUI\FormLayout;
use OOUI\MultilineTextInputWidget;
use OOUI\NumberInputWidget;
use SpecialPage;
use Title;

class SpecialVerifiedExport extends SpecialPage {
	/** @var \TitleFactory */
	private $titleFactory;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var Exporter */
	private $exporter;

	public function __construct(
		\TitleFactory $titleFactory, RevisionStore $store,
		VerificationEngine $verificationEngine, Exporter $exporter
	) {
		parent::__construct( 'VerifiedExport', 'read' );
		$this->titleFactory = $titleFactory;
		$this->revisionStore = $store;
		$this->verificationEngine = $verificationEngine;
		$this->exporter = $exporter;
	}

	/**
	 * @param string|null $par
	 */
	public function execute( $par ) {
		parent::execute( $par );

		if ( $par === 'export' ) {
			$this->makeExportFile();
		} else {
			$this->outputForm();
		}
	}

	protected function getGroupName(): string {
		return 'pagetools';
	}

	private function makeExportFile() {
		$params = $this->getRequest()->wasPosted() ?
			$this->getRequest()->getPostValues() :
			$this->getRequest()->getQueryValues();

		$titles = [];
		if ( $this->getRequest()->wasPosted() && !empty( $params['titles'] ) ) {
			$titles = explode( "\n", trim( $params['titles'] ?? '' ) );
		} elseif ( !empty( $params['titles'] ) ) {
			$titles = explode( '|', trim( $params['titles'] ?? '' ) );
		}

		if ( empty( $titles ) ) {
			$this->error( $this->getContext()->msg( 'da-export-no-title' ) );
			return;
		}
		$tf = $this->titleFactory;
		$titles = array_map( static function( $title ) use ( $tf ) {
			$title = trim( $title );
			$object = $tf->newFromText( $title );
			if ( !( $object instanceof \Title ) ) {
				throw new \MWException( 'Invalid title: ' . $title );
			}
			return $object;
		}, $titles );
		$includeTrans = isset( $params['transclusions'] ) ? (bool)$params['transclusions'] : false;
		$onlyLatest = isset( $params['latest'] ) ? (bool)$params['latest'] : false;
		$depth = isset( $params['depth'] ) ? (int)$params['depth'] : 1;

		$exportSpec = new ExportSpecification();
		foreach ( $titles as $title ) {
			if ( $onlyLatest ) {
				$exportSpec->addTitle( $title, [ $title->getLatestRevID() ] );
			} else {
				$exportSpec->addTitle( $title );
			}
			if ( $includeTrans ) {
				$this->addTranscluded( $title, $exportSpec, $onlyLatest, $depth );
			}
		}
		$fileName = "{$this->verificationEngine->getDomainId()}_{$titles[0]->getPrefixedDBkey()}.json";

		$this->getContext()->getOutput()->disable();
		$response = $this->getContext()->getRequest()->response();

		$response->header( 'Pragma: public' );
		$response->header( 'Expires: 0' );
		$response->header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		$response->header( 'Cache-Control: public' );
		$response->header( 'Content-Description: File Transfer' );
		$response->header( 'Content-Type: application/json' );
		$response->header(
			"Content-Disposition: attachment; filename=\"{$fileName}\""
		);
		$response->header( 'Content-Transfer-Encoding: binary' );
		$response->header( 'X-Robots-Tag: noindex' );

		echo json_encode( $this->exporter->getExportContents( $exportSpec ), JSON_PRETTY_PRINT );
	}

	private function outputForm() {
		$this->getOutput()->enableOOUI();

		$form = new FormLayout( [
			'method' => 'POST',
			'action' => $this->getPageTitle( 'export' )->getLocalURL(),
			'items' => [
				new FieldLayout(
					new MultilineTextInputWidget( [ 'name' => 'titles', 'rows' => 20 ] ),
					[
						'label' => $this->getContext()->msg( 'da-export-field-titles-label' )->text(),
						'align' => 'top',
					],
				),
				new FieldLayout(
					new CheckboxInputWidget( [ 'name' => 'transclusions' ] ),
					[ 'label' => $this->getContext()->msg( 'da-export-field-transclusions-label' )->text() ],
				),
				new FieldLayout(
					new NumberInputWidget( [
						'name' => 'depth', 'min' => 1, 'max' => 10, 'value' => 1
					] ),
					[ 'label' => $this->getContext()->msg( 'da-export-field-depth-label' )->text() ],
				),
				new FieldLayout(
					new CheckboxInputWidget( [ 'name' => 'latest' ] ),
					[ 'label' => $this->getContext()->msg( 'da-export-field-latest-label' )->text() ],
				),
				new ButtonInputWidget( [
					'name' => 'submit',
					'label' => $this->getContext()->msg( 'da-export-button-export-label' )->text(),
					'flags' => [ 'primary', 'progressive' ],
					'type' => 'submit'
				] )

			]
		] );

		$this->getOutput()->addHTML( $form->toString() );
	}

	private function error( Message $msg ) {
		$this->getOutput()->addHTML( $msg->parseAsBlock() );
	}

	private function addTranscluded(
		Title $title, ExportSpecification &$exportSpec, $latest, $depth, $currentLevel = 1
	) {
		$revision = $this->revisionStore->getRevisionByTitle( $title );

		if ( !$revision || !$revision->hasSlot( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES ) ) {
			return;
		}

		$transclusionContent = $revision->getContent( TransclusionHashes::SLOT_ROLE_TRANSCLUSION_HASHES );

		$transclusions = $transclusionContent->getData();
		if ( !$transclusions->isOK() ) {
			return;
		}
		foreach ( $transclusions->getValue() as $transclusion ) {
			$transcludedTitle = $this->titleFactory->makeTitle( $transclusion->ns, $transclusion->dbkey );
			if ( !$transcludedTitle->exists() ) {
				continue;
			}
			$revs = [];
			if ( $latest ) {
				if ( $transclusion->{VerificationEntity::VERIFICATION_HASH} === null ) {
					continue;
				}
				$entity = $this->verificationEngine->getLookup()->verificationEntityFromHash(
					$transclusion->{VerificationEntity::VERIFICATION_HASH}
				);
				if ( $entity instanceof VerificationEntity ) {
					$revs[] = $entity->getRevision()->getId();
				}
			}
			$exportSpec->addTitle( $transcludedTitle, $revs );
			if ( $depth > $currentLevel ) {
				$this->addTranscluded( $transcludedTitle, $exportSpec, $latest, $depth, $currentLevel + 1 );
			}
		}
	}
}
