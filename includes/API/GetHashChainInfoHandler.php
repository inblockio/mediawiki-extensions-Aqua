<?php

namespace DataAccounting\API;

use DataAccounting\Verification\VerificationEngine;
use DataAccounting\Verification\VerificationEntity;
use Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use NamespaceInfo;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

class GetHashChainInfoHandler extends AuthorizedEntityHandler {
	/** @var Language */
	private $contLang;
	/** @var NamespaceInfo */
	private $nsInfo;

	public function __construct(
		PermissionManager $permissionManager, VerificationEngine $verificationEngine,
		Language $contentLang, NamespaceInfo $nsInfo
	) {
		parent::__construct( $permissionManager, $verificationEngine );
		$this->contLang = $contentLang;
		$this->nsInfo = $nsInfo;
	}

	/** @inheritDoc */
	public function run( string $id_type, string $id ) {
		$entity = $this->getEntity( $id_type, $id );
		return [
			VerificationEntity::GENESIS_HASH => $entity->getHash( VerificationEntity::GENESIS_HASH ),
			VerificationEntity::DOMAIN_ID => $entity->getDomainId(),
			'latest_verification_hash' => $entity->getHash( VerificationEntity::VERIFICATION_HASH ),
			'site_info' => $this->getSiteInfo(),
			'title' => $this->verificationEntity->getTitle()->getDBkey(),
			'namespace' => $this->verificationEntity->getTitle()->getNamespace(),
			'chain_height' => $this->verificationEngine->getPageChainHeight(
				$this->verificationEntity->getTitle()
			),
		];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'id_type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => [ 'genesis_hash', 'title' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @param string $idType
	 * @param string $id
	 * @return VerificationEntity|null
	 */
	protected function getEntity( string $idType, string $id ): ?VerificationEntity {
		$conds = [];
		if ( $idType === 'title' ) {
			// TODO: DB data should hold Db key, not prefixed text (spaces replaced with _)
			// Once that is done, remove next line
			$id = str_replace( '_', ' ', $id );
			$conds['page_title'] = $id;
		} else {
			$conds[VerificationEntity::GENESIS_HASH] = $id;
		}
		return $this->verificationEngine->getLookup()->verificationEntityFromQuery( $conds );
	}

	private function getSiteInfo() {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$nsList = [];
		foreach ( $this->contLang->getFormattedNamespaces() as $ns => $title ) {
			$nsList[$ns] = [
				'case' => $this->nsInfo->isCapitalized( $ns ),
				'title' => $title
			];
		}
		return [
			'sitename' => $config->get( 'Sitename' ),
			'dbname' => $config->get( 'DBname' ),
			'base' => Title::newMainPage()->getCanonicalURL(),
			'generator' => 'MediaWiki ' . MW_VERSION,
			'case' => $config->get( 'CapitalLinks' ) ? 'first-letter' : 'case-sensitive',
			'namespaces' => $nsList
		];
	}
}
