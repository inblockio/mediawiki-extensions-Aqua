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

use Config;
use Language;
use NamespaceInfo;
use TitleFactory;
use UploadRevisionImporter;
use ImportSource;

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
	private Config $config;

	private HookContainer $hookContainer;

	private Language $contentLanguage;

	private NamespaceInfo $namespaceInfo;

	private TitleFactory $titleFactory;

	private WikiPageFactory $wikiPageFactory;

	private UploadRevisionImporter $uploadRevisionImporter;

	private PermissionManager $permissionManager;

	private IContentHandlerFactory $contentHandlerFactory;

	private SlotRoleRegistry $slotRoleRegistry;

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
