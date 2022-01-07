<?php

namespace DataAccounting;

use DataAccounting\Transfer\Importer;
use DataAccounting\Transfer\TransferEntityFactory;
use Html;
use ImportStreamSource;
use MediaWiki\Linker\LinkRenderer;
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

			$stats = $collisions = [];
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
					$status = $this->importer->importRevision( $entity, $context, $this->getUser() );
					if ( !$status->isOK() ) {
						$this->errorFromStatus( $status );
						return;
					}
					$value = $status->getValue();
					error_log( var_export( $value
					, 1));
					if ( isset( $value['collision_move'] ) ) {
						$collisions[$value['collision_move']['old']->getPrefixedDBkey()] = $value['collision_move']['new'];
					}
					if ( !isset( $stats[$context->getTitle()->getPrefixedDBkey()] ) ) {
						$stats[$context->getTitle()->getPrefixedDBkey()] = 0;
					}
					$stats[$context->getTitle()->getPrefixedDBkey()]++;

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
				if ( isset( $collisions[$pagename] ) ) {
					$output .= Html::rawElement(
						'ul', [],
						Html::rawElement(
							'li', [],
							Message::newFromKey( 'da-import-result-collision' )
								->rawParams(
									$this->linker->makeLink( $collisions[$pagename] )
								)->text()
						)
					);
				}
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

	/**
	 * @param Status $status
	 */
	private function errorFromStatus( Status $status ) {
		$this->getOutput()->addWikiMsg( 'da-import-fail-title' );
		$this->getOutput()->addWikiTextAsContent( $status->getWikiText( ));
	}
}
