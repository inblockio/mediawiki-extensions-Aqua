<?php

namespace DataAccounting\Transfer;

use DataAccounting\Verification\Entity\VerificationEntity;
use DataAccounting\Verification\VerificationEngine;
use Title;

class Exporter {
	private TransferEntityFactory $transferEntityFactory;
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
		$exportData['siteInfo'] = $this->transferEntityFactory->getSiteInfo();

		foreach ( $map as $dbKey => $data ) {
			$context = $this->transferEntityFactory->newTransferContextFromTitle( $data['title'] );
			$pageData = $context->jsonSerialize();
			// TODO: Remove site info from context
			unset( $pageData['site_info'] );
			if ( empty( $data['revisionIds'] ) ) {
				$allRevisions = $this->verificationEngine->getLookup()->getAllRevisionIds( $data['title'] );
				$pageData['revisions'] = $this->getRevisionEntities( $data['title'], $allRevisions );
			} else {
				$pageData['revisions'] = $this->getRevisionEntities( $data['title'], $data['revisionIds'] );
			}

			$exportData['pages'][] = $pageData;
		}

		return $exportData;
	}

	/**
	 * @param Title $title
	 * @param array $revisionIds
	 * @return array
	 */
	private function getRevisionEntities( Title $title, array $revisionIds ): array {
		$data = [];
		foreach ( $revisionIds as $revId ) {
			$revisionEntity = $this->getRevisionEntity( $title, $revId );
			if ( $revisionEntity instanceof TransferRevisionEntity ) {
				$verificationHash = $revisionEntity->getMetadata()["verification_hash"];
				$data[$verificationHash] = $revisionEntity;
			}
		}

		return $data;
	}

	private function getRevisionEntity( Title $title, $revId ): ?TransferRevisionEntity {
		$verificationEntity = $this->verificationEngine->getLookup()->verificationEntityFromRevId( $revId );
		if (
			!( $verificationEntity instanceof VerificationEntity ) ||
			!$verificationEntity->getTitle()->equals( $title )
		) {
			// Does not exist, or not belonging to this title
			return null;
		}

		return $this->transferEntityFactory->newRevisionEntityFromVerificationEntity( $verificationEntity );
	}

}
