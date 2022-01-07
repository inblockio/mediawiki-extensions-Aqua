<?php

namespace DataAccounting\API;

use ApiBase;
use ApiMain;
use DataAccounting\Content\TransclusionHashes;
use DataAccounting\Transfer\Exporter;
use DataAccounting\Transfer\ExportSpecification;
use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use MediaWiki\Revision\RevisionStore;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @ingroup API
 */
class ApiExportPage extends ApiBase {
	/** @var \TitleFactory */
	private $titleFactory;
	/** @var RevisionStore */
	private $revisionStore;
	/** @var VerificationEngine */
	private $verificationEngine;
	/** @var Exporter */
	private $exporter;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param \TitleFactory $titleFactory
	 * @param RevisionStore $store
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct(
		ApiMain $mainModule, $moduleName, \TitleFactory $titleFactory,
		RevisionStore $store, VerificationEngine $verificationEngine,
		Exporter $exporter
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->titleFactory = $titleFactory;
		$this->revisionStore = $store;
		$this->verificationEngine = $verificationEngine;
		$this->exporter = $exporter;
	}

	public function execute() {
		$this->checkUserRightsAny( 'read' );

		$title = $this->titleFactory->newFromText( $this->getParameter( 'page' ) );
		if ( !( $title instanceof Title ) ) {
			$this->dieWithError( "Page {$this->getParameter( 'page' )} is not a valid title" );
		}

		$exportSpec = new ExportSpecification();
		$onlyLatest = (bool)$this->getParameter( 'only-latest' );
		if ( $onlyLatest ) {
			$exportSpec->addTitle( $title, [ $title->getLatestRevID() ] );
		} else {
			$exportSpec->addTitle( $title );
		}
		if ( (bool)$this->getParameter( 'include-transclusions' ) ) {
			$this->addTranscluded( $title, $exportSpec, $onlyLatest );
		}

		$this->getResult()->addValue(
			null, $this->getModuleName(),
			json_encode( $this->exporter->getExportContents( $exportSpec ), JSON_PRETTY_PRINT )
		);
	}

	private function addTranscluded( Title $title, ExportSpecification &$exportSpec, $latest = false ) {
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
			if ( $latest ) {
				$entity = $this->verificationEngine->getLookup()->verificationEntityFromHash(
					$transclusion->{VerificationEntity::VERIFICATION_HASH}
				);
				if ( $entity instanceof VerificationEntity ) {
					$exportSpec->addTitle( $transcludedTitle, [ $entity->getRevision()->getId() ] );
				}
			} else {
				$exportSpec->addTitle( $transcludedTitle );
			}
		}
	}

	public function mustBePosted() {
		return false;
	}

	public function isWriteMode() {
		return false;
	}

	public function getAllowedParams() {
		return [
			'page' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'only-latest' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 0,
			],
			'include-transclusions' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 0,
			]
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=da-export-page&page=Test'
			=> 'apihelp-da-export-page-example-page'
		];
	}
}

