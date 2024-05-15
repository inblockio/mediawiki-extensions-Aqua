<?php

namespace DataAccounting\API;

use DataAccounting\RevisionManipulator;
use DataAccounting\Verification\VerificationEngine;
use Exception;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use RequestContext;
use Title;
use TitleFactory;
use User;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class ForkPageHandler extends SimpleHandler {

	/** @var PermissionManager */
	private $permissionManager;

	/** @var RevisionManipulator */
	private $revisionManipulator;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var User */
	private $user;

	/**
	 * @param PermissionManager $permissionManager
	 * @param RevisionManipulator $revisionManipulator
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		PermissionManager $permissionManager,
		RevisionManipulator $revisionManipulator,
		TitleFactory $titleFactory,
		RevisionLookup $revisionLookup
	) {
		$this->permissionManager = $permissionManager;
		$this->revisionManipulator = $revisionManipulator;
		$this->user = RequestContext::getMain()->getUser();
		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * @return Response
	 * @throws HttpException
	 * @throws LocalizedHttpException
	 */
	public function run() {
		$revisionId = $this->getValidatedBody()['revision'];
		$targetPageName = $this->getValidatedBody()['target'];
		$sourcePageName = $this->getValidatedBody()['page'];

		$target = $this->titleFactory->newFromText( $targetPageName );
		$this->verifyTarget( $target );
		if ( !$this->permissionManager->userCan( 'edit', $this->user, $target ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'rest-permission-denied-revision' )->plaintextParams(
					'You are not allowed to fork pages'
				),
				403
			);
		}
		$source = $this->titleFactory->newFromText( $sourcePageName );
		$this->verifySource( $source );
		$revision = $this->revisionLookup->getRevisionById( $revisionId );
		$this->verifyRevision( $revision, $source );

		try {
			$this->revisionManipulator->forkPage( $source, $target, $revision, $this->user );
		} catch ( \Exception $e ) {
			throw new HttpException( $e->getMessage(), 500 );
		}
		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyValidator( $contentType ) {
		if ( $contentType !== 'application/json' ) {
			throw new HttpException( "Unsupported Content-Type",
				415,
				[ 'content_type' => $contentType ]
			);
		}

		return new JsonBodyValidator( [
			'revision' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'page' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'target' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		] );
	}

	/**
	 * @param Title|null $title
	 * @return void
	 * @throws HttpException
	 */
	private function verifyTarget( ?Title $title ) {
		if ( !( $title instanceof Title ) ) {
			throw new HttpException( 'Invalid target page', 400 );
		}
		if ( $title->exists() ) {
			throw new HttpException( 'Target page already exists', 400 );
		}
	}

	/**
	 * @param Title|null $title
	 * @return void
	 * @throws HttpException
	 */
	private function verifySource( ?Title $title ) {
		if ( !( $title instanceof Title ) ) {
			throw new HttpException( 'Invalid source page', 400 );
		}
		if ( !$title->exists() ) {
			throw new HttpException( 'Source page does not exist', 400 );
		}
		if ( !$title->getLatestRevID() ) {
			throw new HttpException( 'Source page has no revisions', 400 );
		}
	}

	/**
	 * @param RevisionRecord|null $revision
	 * @param Title|null $source
	 * @return void
	 * @throws HttpException
	 */
	private function verifyRevision( ?RevisionRecord $revision, ?Title $source ) {
		if ( !( $revision instanceof RevisionRecord ) ) {
			throw new HttpException( 'Invalid revision', 400 );
		}
		if ( $revision->getPage()->getId() !== $source->getArticleID() ) {
			throw new HttpException( 'Revision does not belong to source page', 400 );
		}
	}

}
