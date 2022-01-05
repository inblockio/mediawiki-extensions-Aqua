<?php
/**
 * Implements Special:Import
 *
 * Copyright © 2003,2005 Brion Vibber <brion@pobox.com>
 * https://www.mediawiki.org/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

namespace DataAccounting;

use DataAccounting\Transfer\Importer;
use DataAccounting\Transfer\TransferEntityFactory;
use Html;
use ImportStreamSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use Message;
use OOUI\ButtonInputWidget;
use OOUI\FieldLayout;
use OOUI\FormLayout;
use OOUI\HiddenInputWidget;
use OOUI\SelectFileInputWidget;
use PermissionsError;
use SpecialPage;
use Status;
use UnexpectedValueException;
use Exception;
use TitleFactory;

class SpecialVerifiedImport extends SpecialPage {
	/** @var PermissionManager */
	private $permManager;
	/** @var TransferEntityFactory */
	private $transferEntityFactory;
	/** @var Importer */
	private $importer;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var LinkRenderer */
	private $linker;

	/**
	 * @param PermissionManager $permissionManager
	 * @param TransferEntityFactory $transferEntityFactory
	 * @param Importer $importer
	 * @param TitleFactory $titleFactory
	 * @param LinkRenderer $linker
	 */
	public function __construct(
		PermissionManager $permissionManager,
		TransferEntityFactory $transferEntityFactory,
		Importer $importer,
		TitleFactory $titleFactory,
		LinkRenderer $linker
	) {
		parent::__construct( 'VerifiedImport', 'import' );
		$this->permManager = $permissionManager;
		$this->transferEntityFactory = $transferEntityFactory;
		$this->importer = $importer;
		$this->titleFactory = $titleFactory;
		$this->linker = $linker;
	}

	public function doesWrites(): bool {
		return true;
	}

	/**
	 * @param string|null $par
	 * @throws PermissionsError
	 * @throws \ReadOnlyError
	 */
	public function execute( $par ) {
		parent::execute( $par );
		$this->useTransactionalTimeLimit();
		$this->checkReadOnly();

		if ( $this->getRequest()->wasPosted() && $this->getRequest()->getRawVal( 'action' ) == 'submit' ) {
			$this->doImport();
		}
		$this->showForm();

	}

	/**
	 * Do the actual import
	 */
	private function doImport() {
		if ( $this->permManager->userHasRight( $this->getUser(), 'importupload' ) ) {
			$source = ImportStreamSource::newFromUpload( "jsonimport" );
		} else {
			throw new PermissionsError( 'importupload' );
		}
		if ( !$this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) ) ) {
			$source = Status::newFatal( 'import-token-mismatch' );
		}

		if ( !$source->isGood() ) {
			$this->getOutput()->wrapWikiTextAsInterface( 'error',
				$this->msg( 'importfailed', $source->getWikiText( false, false, $this->getLanguage() ) )
					->plain()
			);
		} else {
			/** @var \ImportSource $source */
			$source = $source->value;
			$content = '';
			$leave = false;

			while ( !$leave && !$source->atEnd() ) {
				$chunk = $source->readChunk();
				if ( !is_string( $chunk ) ) {
					$leave = true;
				}
				$content .= $chunk;
			}
			$arrayContent = json_decode( $content, 1 );
			if ( !$arrayContent ) {
				$this->getOutput()->wrapWikiTextAsInterface( 'error',
					$this->msg( 'importfailed', 'Read failed' )
						->plain()
				);
				return;
			}

			$stats = [];
			$siteInfo = $arrayContent['siteInfo'];
			foreach ( $arrayContent['pages'] as $page ) {
				$revisions = $page['revisions'];
				unset( $page['revisions'] );
				$page['site_info'] = $siteInfo;
				$context = $this->transferEntityFactory->newTransferContextFromData( $page );
				foreach ( $revisions as $hash => $revision ) {
					$entity = $this->transferEntityFactory->newRevisionEntityFromApiData( $revision );
					if ( !$entity instanceof \DataAccounting\Transfer\TransferRevisionEntity ) {
						continue;
					}
					$this->importer->importRevision( $entity, $context );
					if ( !isset( $stats[$page['title']] ) ) {
						$stats[$page['title']] = 0;
					}
					$stats[$page['title']]++;

				}
			}
			$output = Html::openElement( 'ul' );
			foreach( $stats as $pagename => $count ) {
				$title = $this->titleFactory->newFromText( $pagename );
				$output .= Html::rawElement(
					'li', [],
					Message::newFromKey( 'da-import-result-line' )
						->rawParams( $this->linker->makeLink( $title ), $count )->text()
				);
			}
			$output .= Html::closeElement( 'ul' );
			$this->getOutput()->addHTML( $output );
		}
	}

	private function showForm() {
		$this->getOutput()->enableOOUI();

		$form = new FormLayout( [
			'method' => 'POST',
			'enctype' => 'multipart/form-data',
			'items' => [
				new HiddenInputWidget( [ 'name' => 'action', 'value' => 'submit' ] ),
				new HiddenInputWidget( [ 'name' => 'wpEditToken', 'value' => $this->getUser()->getEditToken() ] ),
				new FieldLayout(
					new SelectFileInputWidget( [ 'name' => 'jsonimport', 'required' => true, 'droppable' => true ] ),
					[
						'label' => $this->getContext()->msg( 'da-import-field-file-label' )->text(),
						'align' => 'top',
					],
				),
				new ButtonInputWidget( [
					'name' => 'submit',
					'label' => $this->getContext()->msg( 'da-import-button-import-label' )->text(),
					'flags' => [ 'primary', 'progressive' ],
					'type' => 'submit'
				] )

			]
		] );

		$this->getOutput()->addHTML( $form->toString() );
	}

	protected function getGroupName(): string {
		return 'pagetools';
	}
}
