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
 * Global popup button element.
 */
class CPopupButtonElement extends CElement {

	/**
	 * Click on button and select item in popup menu.
	 *
	 * @param string|array $text    text of menu item(s)
	 *
	 * @return $this
	 */
	public function select($text) {
		$is_nested = false;
		if (is_array($text)) {
			foreach ($text as $item) {
				if (is_array($item)) {
					$is_nested = true;
					break;
				}
			}
		}

		if (!$is_nested) {
			$text = [$text];
		}

		foreach ($text as $item) {
			$this->getMenu()->waitUntilReady()->fill($item);
		}

		return $this;
	}

	/**
	 * Get popup menu.
	 *
	 * @return CPopupMenuElement
	 */
	public function getMenu() {
		$query = $this->query('xpath://ul[contains(@class, "menu-popup-top")]')->asPopupMenu();

		$menu = $query->one(false);
		if ($menu->isValid()) {
			return $menu;
		}

		// Sometimes menu is not summoning from the first time.
		for ($i = 0; $i < 2; $i++) {
			try {
				$this->click(true);

				$menu = $query->waitUntilVisible()->one(false);
				if ($menu->isValid()) {
					return $menu;
				}
			}
			catch (Exception $e) {
				sleep(1);
				// Code is not missing here.
			}
		}

		throw new Exception('Failed to wait for menu to be visible!');
	}

	/**
	 * @inheritdoc
	 */
	public function fill($text) {
		return $this->select($text);
	}
}
