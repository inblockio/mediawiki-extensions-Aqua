<?php

namespace DataAccounting\Inbox;

use DataAccounting\Verification\VerificationEngine;
use Html;
use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPageFactory;
use TablePager;
use TitleFactory;

class Pager extends TablePager {
	/** @var TitleFactory */
	private $titleFactory;

	/** @var VerificationEngine */
	private $verificationEngine;

	/** @var SpecialPageFactory */
	private $spf;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param TitleFactory $titleFactory
	 * @param VerificationEngine $verificationEngine
	 * @param SpecialPageFactory $spf
	 */
	public function __construct(
		IContextSource $context, LinkRenderer $linkRenderer,
		TitleFactory $titleFactory, VerificationEngine $verificationEngine, SpecialPageFactory $spf
	) {
		parent::__construct( $context, $linkRenderer );
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
		$this->spf = $spf;
	}

	/**
	 * @return int
	 */
	public function getCount(): int {
		$row = $this->getDatabase()->selectRow(
			'page',
			[ 'COUNT(*) AS count' ],
			[ 'page_namespace' => NS_INBOX ]
		);

		return $row ? (int)$row->count : 0;
	}

	public function getQueryInfo() {
		return [
			'tables' => [ 'p' => 'page',  'v' => 'revision_verification' ],
			// All fields but afh_deleted on abuse_filter_history
			'fields' => [
				'p.page_id',
				'p.page_title',
				'v.domain_id',
			],
			'conds' => [
				'p.page_namespace' => NS_INBOX,
				'revision_verification_id=' .
					'(SELECT MAX( revision_verification_id ) FROM revision_verification WHERE page_id=p.page_id )'
			],
			'join_conds' => [
				'revision_verification' =>
					[
						'INNER JOIN',
						'p.page_id=v.page_id',
					],
			],
			'options' => [
				'GROUP BY' => 'page_title'
			]
		];
	}

	protected function buildQueryInfo( $offset, $limit, $order ) {
		list( $tables, $fields, $conds, $fname, $options, $join_conds ) =
			parent::buildQueryInfo( $offset, $limit, $order );

		$options['ORDER BY'] = 'domain_id ASC, page_title ASC';
		return [ $tables, $fields, $conds, $fname, $options, $join_conds ];
	}

	protected function isFieldSortable( $field ) {
		return false;
	}

	public function formatValue( $name, $value ) {
		if ( $name === 'page_title' ) {
			$title = $this->titleFactory->makeTitle( NS_INBOX, $value );
			return $this->getLinkRenderer()->makeLink( $title, $title->getText() );
		}
		if ( $name === 'domain_id' ) {
			if ( $this->verificationEngine->getDomainId() === $value ) {
				return $value . ' (' . $this->msg( 'da-specialinbox-label-own-domain' ) . ')';
			}
		}
		return $value;
	}

	public function getDefaultSort() {
		return '';
	}

	public function formatRow( $row ) {
		$this->mCurrentRow = $row; // In case formatValue etc need to know
		$s = Html::openElement( 'tr', $this->getRowAttrs( $row ) ) . "\n";
		$fieldNames = $this->getFieldNames();

		foreach ( $fieldNames as $field => $name ) {
			$value = $row->$field ?? null;
			$formatted = strval( $this->formatValue( $field, $value ) );

			if ( $formatted == '' ) {
				$formatted = "\u{00A0}";
			}

			$s .= Html::rawElement( 'td', $this->getCellAttrs( $field, $value ), $formatted ) . "\n";
		}
		$title = $this->spf->getPage( 'Inbox' )->getPageTitle( $row->page_title );
		$link = $this->getLinkRenderer()->makeLink(
			$title, $this->msg( 'da-specialimport-action-compare-merge' )->text()
		);
		$s .= Html::rawElement( 'td', [], $link ) . "\n";

		$s .= Html::closeElement( 'tr' ) . "\n";

		return $s;
	}

	protected function getCellAttrs( $field, $value ) {
		if ( $field === 'domain_id' ) {
			if ( $this->verificationEngine->getDomainId() === $value ) {
				return [ 'width' => '200px', 'style' => 'background-color: #e6ffe6' ];
			}
			return [ 'width' => '200px' ];
		}
		return parent::getCellAttrs( $field, $value );
	}

	protected function getFieldNames() {
		return [
			'domain_id' => $this->msg( 'da-specialimport-header-domain' )->text(),
			'page_title' => $this->msg( 'da-specialimport-header-page-title' )->text(),
		];
	}
}
