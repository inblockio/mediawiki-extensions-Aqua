<?php
/**
 * An extension that demonstrates how to create a new page action.
 *
 * @author Ævar Arnfjörð Bjarmason <avarab@gmail.com>
 * @copyright Copyright © 2005, Ævar Arnfjörð Bjarmason
 * @license GPL-2.0-or-later
 */

namespace DataAccounting;

use FormlessAction;

class DAAction extends FormlessAction {

	/** @inheritDoc */
	public function getName(): string {
		return 'daact';
	}

	/** @inheritDoc */
	protected function getDescription(): string {
		// Disable subtitle under page heading
		return '';
	}

	/** @inheritDoc */
	public function onView() {
		return null;
	}

	/** @inheritDoc */
	public function show() {
		parent::show();

		$this->getContext()->getOutput()->addWikiTextAsInterface(
			'This is a custom action for page [[' . $this->getTitle()->getText() . ']].'
		);
	}

	/**
	 * Indicates whether this action may perform database writes.
	 * See https://doc.wikimedia.org/mediawiki-core/master/php/classAction.html
	 *
	 * @return bool
	 */
	public function doesWrites(): bool {
		return true;
	}
}
