<?php

namespace DataAccounting\Transfer;

use DataAccounting\Verification\VerificationEngine;

class Exporter {
	/** @var TransferEntityFactory */
	private TransferEntityFactory $transferEntityFactory;
	/** @var VerificationEngine */
	private VerificationEngine $verificationEngine;

	/**
	 * @param TransferEntityFactory $transferEntityFactory
	 * @param VerificationEngine $verificationEngine
	 */
	public function __construct(
		TransferEntityFactory $transferEntityFactory, VerificationEngine $verificationEngine
	) {
		$this->transferEntityFactory = $transferEntityFactory;
		$this->verificationEngine = $verificationEngine;
	}

	/**
	 * @param ExportSpecification $spec
	 * @return array
	 */
	public function getExportContents( ExportSpecification $spec ): array {
		$map = $spec->getExportMap();
		if ( empty( $map ) ) {
			return [];
		}

		$exportData = [
			'pages' => [],
		];
		$exportData['site_info'] = $this->transferEntityFactory->getSiteInfo();

		foreach ( $map as $dbKey => $data ) {
			$context = $this->transferEntityFactory->newTransferContextFromTitle( $data['title'] );
			if ( !$context ) {
				continue;
			}
			$pageData = $context->jsonSerialize();
			// TODO: Remove site info from context
			unset( $pageData['site_info'] );
			if ( empty( $data['revisionIds'] ) ) {
				$allRevisions = $this->verificationEngine->getLookup()->getAllRevisionIds( $data['title'] );
				$pageData['revisions'] = $this->getRevisionEntities( $allRevisions );
			} else {
				$pageData['revisions'] = $this->getRevisionEntities( $data['revisionIds'] );
			}

			$exportData['pages'][] = $pageData;
		}

		return $exportData;
	}

	/**
	 * @param array $revisionIds
	 * @return array
	 */
	private function getRevisionEntities( array $revisionIds ): array {
		$data = [];
		foreach ( $revisionIds as $revId ) {
			$revisionEntity = $this->getRevisionEntity( $revId );
			if ( $revisionEntity instanceof TransferRevisionEntity ) {
				$verificationHash = $revisionEntity->getMetadata()["verification_hash"];
				$data[$verificationHash] = $revisionEntity;
			}
		}

		return $data;
	}

	/**
	 * @param int $revId
	 * @return TransferRevisionEntity|null
	 */
	private function getRevisionEntity( $revId ): ?TransferRevisionEntity {
		$verificationEntity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $revId );
		if ( $verificationEntity === null ) {
			// Does not exist
			return null;
		}
		return $this->transferEntityFactory->newRevisionEntityFromVerificationEntity( $verificationEntity );
	}

}
