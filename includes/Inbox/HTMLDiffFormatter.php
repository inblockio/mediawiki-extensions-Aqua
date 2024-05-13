<?php

namespace DataAccounting\Inbox;

use DiffOp;

class HTMLDiffFormatter extends \DiffFormatter {
	/** @var int[] */
	protected $stats = [ 'add' => 0, 'delete' => 0 ];
	/** @var array */
	protected $arrayData = [];
	/** @var int */
	protected $idCounter = 0;
	/** @var string */
	protected $html = '';

	/**
	 * Parses diff to HTML
	 *
	 * @param \Diff $diff
	 * @param bool $block If true, every line will be its own block
	 * @return string
	 */
	public function format( $diff, $block = true ) {
		$this->html = '';

		$this->html = \Html::openElement( 'div', [
			'class' => 'da-diff',
			'id' => 'da-diff'
		] );

		$this->idCounter = 0;

		foreach ( $diff->getEdits() as $edit ) {
			switch ( $edit->getType() ) {
				case 'add':
					if ( $block ) {
						$this->blockAdd( $edit );
					} else {
						$this->lineByLineAdd( $edit );
					}
					break;
				case 'delete':
					if ( $block ) {
						$this->blockDelete( $edit );
					} else {
						$this->lineByLineDelete( $edit );
					}
					break;
				case 'change':
					[ $orig, $closing ] = $this->conflateChange( $edit );
					if ( $block ) {
						$this->blockChange( $orig, $closing );
					} else {
						$this->lineByLineChange( $orig, $closing );
					}
					break;
				case 'copy':
					// Copy is always rendered as block
					$this->blockCopy( $edit );
			}
		}

		$this->html .= \Html::closeElement( 'div' );
		return $this->html;
	}

	/**
	 *
	 * @return array
	 */
	public function getArrayData() {
		return $this->arrayData;
	}

	/**
	 *
	 * @return array
	 */
	public function getChangeCount() {
		$changeCount = [ 'add' => 0, 'delete' => 0 ];
		foreach ( $this->arrayData as $changeId => $change ) {
			if ( $change[ 'type' ] === 'add' ) {
				$changeCount[ 'add' ]++;
			} elseif ( $change[ 'type' ] === 'delete' ) {
				$changeCount[ 'delete' ]++;
			} elseif ( $change[ 'type' ] === 'change' ) {
				$changeCount[ 'add' ]++;
				$changeCount[ 'delete' ]++;
			}
		}
		return $changeCount;
	}

	/**
	 *
	 * @param string $diff
	 * @param string $type
	 * @param bool|false $counter
	 * @return string
	 */
	protected function getDiffHTML( $diff, $type, $counter = true ) {
		$attrs = [
			'class' => "da-diff-$type",
			'data-diff' => $type
		];
		if ( $counter ) {
			$attrs['data-diff-id'] = $this->idCounter;
		}

		$html = \Html::openElement( 'p', $attrs );
		foreach ( explode( "\n", $diff ) as $ln ) {
			if ( empty( $ln ) ) {
				$html .= \Html::element( 'span', [ 'class' => 'empty-line' ], $ln );
			} else {
				$html .= \Html::element( 'span', [], $ln );
			}
		}
		$html .= \Html::closeElement( 'p' );
		return $html;
	}

	/**
	 *
	 * @param \DiffOp $edit
	 * @return array
	 */
	protected function conflateChange( \DiffOp $edit ) {
		$orig = $edit->getOrig();
		$closing = $edit->getClosing();

		if ( count( $orig ) > count( $closing ) ) {
			return array_reverse( $this->mergeUneven( $closing, $orig ) );
		} elseif ( count( $closing ) > count( $orig ) ) {
			return $this->mergeUneven( $orig, $closing );
		}
		return [ $orig, $closing ];
	}

	/**
	 *
	 * @param array $ar1
	 * @param array $ar2
	 * @return array
	 */
	protected function mergeUneven( $ar1, $ar2 ) {
		$i = 0;
		$res = [];
		while ( $i < count( $ar1 ) - 1 ) {
			$i++;
			$res[] = $ar2[$i];
		}
		$res[] = implode( "\n", array_diff( $ar2, $res ) );
		return [ $ar1, $res ];
	}

	/**
	 *
	 * @param DiffOp $edit
	 */
	protected function blockAdd( $edit ) {
		$closingAll = implode( "\n", $edit->getClosing() );
		$this->idCounter++;
		$this->html .= $this->getDiffHTML( $closingAll, 'add' );
		$this->arrayData[ $this->idCounter ] = [
			'type' => 'add',
			'new' => $closingAll
		];
	}

	/**
	 *
	 * @param DiffOp $edit
	 */
	protected function lineByLineAdd( $edit ) {
		foreach ( $edit->getClosing() as $line ) {
			$this->idCounter++;
			$this->html .= $this->getDiffHTML( $line, 'add' );
			$this->arrayData[ $this->idCounter ] = [
				'type' => 'add',
				'new' => $line
			];
		}
	}

	/**
	 *
	 * @param DiffOp $edit
	 */
	protected function blockDelete( $edit ) {
		$origAll = implode( "\n", $edit->getOrig() );
		$this->idCounter++;
		$this->html .= $this->getDiffHTML( $origAll, 'delete' );
		$this->arrayData[ $this->idCounter ] = [
			'type' => 'delete',
			'old' => $origAll
		];
	}

	/**
	 *
	 * @param DiffOp $edit
	 */
	protected function lineByLineDelete( $edit ) {
		foreach ( $edit->getOrig() as $line ) {
			$this->idCounter++;
			$this->html .= $this->getDiffHTML( $line, 'delete' );
			$this->arrayData[ $this->idCounter ] = [
				'type' => 'delete',
				'old' => $line
			];
		}
	}

	/**
	 *
	 * @param array $orig
	 * @param array $closing
	 */
	protected function blockChange( $orig, $closing ) {
		$origAll = implode( "\n", $orig );
		$closingAll = implode( "\n", $closing );
		$this->idCounter++;
		$this->html .= \Html::openElement( 'div', [
			'class' => 'da-diff-change',
			'data-diff' => 'change',
			'data-diff-id' => $this->idCounter
		] );
		$this->html .= $this->getDiffHTML( $origAll, 'delete', false );
		$this->html .= $this->getDiffHTML( $closingAll, 'add', false );
		$this->html .= \Html::closeElement( 'div' );
		$this->arrayData[ $this->idCounter ] = [
			'type' => 'change',
			'old' => $origAll,
			'new' => $closingAll
		];
	}

	/**
	 *
	 * @param array $orig
	 * @param array $closing
	 */
	protected function lineByLineChange( $orig, $closing ) {
		foreach ( $orig as $key => $line ) {
			$this->idCounter++;
			$this->html .= \Html::openElement( 'div', [
				'class' => 'da-diff-change',
				'data-diff' => 'change',
				'data-diff-id' => $this->idCounter
			] );
			$this->html .= $this->getDiffHTML( $line, 'delete', false );
			$this->html .= $this->getDiffHTML( $closing[$key], 'add', false );
			$this->html .= \Html::closeElement( 'div' );
			$this->arrayData[ $this->idCounter ] = [
				'type' => 'change',
				'old' => $line,
				'new' => $closing[$key]
			];
		}
	}

	/**
	 *
	 * @param DiffOp $edit
	 */
	protected function blockCopy( $edit ) {
		$text = implode( "\n", $edit->getOrig() );
		$this->idCounter++;
		$this->html .= $this->getDiffHTML( $text, 'copy' );
		$this->arrayData[ $this->idCounter ] = [
			'type' => 'copy',
			'old' => $text
		];
	}
}
