<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Global popup menu element.
 */
class CPopupMenuElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('xpath://ul[contains(@class, "menu-popup-top")]'))->asPopupMenu();
	}

	/**
	 * Get collection of popup menu titles.
	 *
	 * @return array
	 */
	public function getTitles() {
		return $this->query('xpath:.//h3')->all();
	}

	/**
	 * Check if titles exists.
	 *
	 * @param string|array $titles    titles to be searched for
	 *
	 * @return boolean
	 */
	public function hasTitles($titles) {
		if (!is_array($titles)) {
			$titles = [$titles];
		}

		return count(array_diff($titles, $this->getTitles()->asText())) === 0;
	}

	/**
	 * Get collection of menu items.
	 *
	 * @return CElementCollection
	 */
	public function getItems() {
		return $this->query('xpath:./li/a')->all();
	}

	/**
	 * Get a single menu item.
	 *
	 * @return CElement
	 */
	public function getItem($name) {
		$element = $this->query('xpath', './li/a[text()='.CXPathHelper::escapeQuotes($name).']')->one(false);
		if (!$element->isValid()) {
			throw new Exception('Failed to find menu item by name: "'.$name.'".');
		}

		return $element;
	}

	/**
	 * Check if items exists.
	 *
	 * @param string|array $items    items to be searched for
	 *
	 * @return boolean
	 */
	public function hasItems($items) {
		if (!is_array($items)) {
			$items = [$items];
		}

		return count(array_diff($items, $this->getItems()->asText())) === 0;
	}


	/**
	 * Select item from popup menu.
	 *
	 * @param string|array $items    text of menu item(s)
	 *
	 * @return $this
	 */
	public function select($items) {
		if (!is_array($items)) {
			$items = [$items];
		}
		// Get item by name.
		$element = $this->getItem(array_shift($items));

		if ($items) {
			$parents = $element->parents('tag:li')->one()->hover();
			$parents->query('class:menu-popup')->asPopupMenu()->waitUntilVisible()->one()->select($items);
		}
		else {
			$element->waitUntilClickable()->click(true);
		}

		return $this;
	}

	/**
	 * Alias for select.
	 * @see self::select
	 *
	 * @param string $items    items text to be selected
	 *
	 * @return $this
	 */
	public function fill($items) {
		return $this->select($items);
	}
}
