<?php

namespace DataAccounting\Content;

interface DataAccountingContent {
	/**
	 * Get count of items, if applicable
	 * @return int
	 */
	public function getItemCount(): int;

	/**
	 * Does slot require user to do something
	 *
	 * @return bool
	 */
	public function requiresAction(): bool;

	/**
	 *
	 * @return string
	 */
	public function getSlotHeader(): string;

	/**
	 * Should we show the slot
	 *
	 * @return bool
	 */
	public function shouldShow(): bool;
}
