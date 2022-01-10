<?php

namespace DataAccounting\Transfer;

use Title;

class ExportSpecification {
	/** @var array */
	private $exportMap = [];

	/**
	 * @param array|null $data
	 */
	public function __construct( $data = [] ) {
		foreach ( $data as $dataItem ) {
			$title = array_shift( $dataItem );
			$revisionIds = !empty( $dataItem ) ? array_shift( $dataItem ) : [];
			$this->addTitle( $title, $revisionIds );
		}
	}

	/**
	 * @param Title $title
	 * @param array $revisionIds
	 */
	public function addTitle( Title $title, array $revisionIds = [] ) {
		$dbKey = $title->getPrefixedDBkey();
		if ( !isset( $this->exportMap[$dbKey] ) ) {
			$this->exportMap[$dbKey] = [
				'title' => $title,
				'revisionIds' => [],
			];
		}
		$this->exportMap[$dbKey]['revisionIds'] = array_merge(
			$this->exportMap[$dbKey]['revisionIds'], $revisionIds
		);
	}

	/**
	 * @return array
	 */
	public function getExportMap(): array {
		return $this->exportMap;
	}
}
