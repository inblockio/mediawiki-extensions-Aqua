<?php
/**
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
 * @author Zabe
 */

namespace DataAccounting;

use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\SlotRoleRegistry;

/**
 * Data Accounting modifications:
 * - Change WikiImporter to VerifiedWikiImporter.
 */

/**
 * Factory service for WikiImporter instances.
 *
 * @since 1.37
 */
class VerifiedWikiImporterFactory {
	/** @var Config */
	private $config;

	/** @var HookContainer */
	private $hookContainer;

	/** @var Language */
	private $contentLanguage;

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var UploadRevisionImporter */
	private $uploadRevisionImporter;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var IContentHandlerFactory */
	private $contentHandlerFactory;

	/** @var SlotRoleRegistry */
	private $slotRoleRegistry;

	/**
	 * @param Config $config
	 * @param HookContainer $hookContainer
	 * @param Language $contentLanguage
	 * @param NamespaceInfo $namespaceInfo
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UploadRevisionImporter $uploadRevisionImporter
	 * @param PermissionManager $permissionManager
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param SlotRoleRegistry $slotRoleRegistry
	 */
	public function __construct(
		Config $config,
		HookContainer $hookContainer,
		Language $contentLanguage,
		NamespaceInfo $namespaceInfo,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory,
		UploadRevisionImporter $uploadRevisionImporter,
		PermissionManager $permissionManager,
		IContentHandlerFactory $contentHandlerFactory,
		SlotRoleRegistry $slotRoleRegistry
	) {
		$this->config = $config;
		$this->hookContainer = $hookContainer;
		$this->contentLanguage = $contentLanguage;
		$this->namespaceInfo = $namespaceInfo;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->uploadRevisionImporter = $uploadRevisionImporter;
		$this->permissionManager = $permissionManager;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->slotRoleRegistry = $slotRoleRegistry;
	}

	/**
	 * @param ImportSource $source
	 *
	 * @return VerifiedWikiImporter
	 */
	public function getWikiImporter( ImportSource $source ): VerifiedWikiImporter {
		return new VerifiedWikiImporter(
			$source,
			$this->config,
			$this->hookContainer,
			$this->contentLanguage,
			$this->namespaceInfo,
			$this->titleFactory,
			$this->wikiPageFactory,
			$this->uploadRevisionImporter,
			$this->permissionManager,
			$this->contentHandlerFactory,
			$this->slotRoleRegistry
		);
	}
}
