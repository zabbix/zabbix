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
 * Main menu element
 */
class CMainMenuElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('class:nav-main'))->asMainMenu();
	}

	/**
	 * Select main menu section.
	 *
	 * @param string $section		first level menu
	 */
	public function select($section) {
		$this->query('link', $section)->waitUntilVisible()->one()->click();
		$element = $this->query('xpath://a[text()='.CXPathHelper::escapeQuotes($section).
				']/../ul[@class="submenu"]')->one();

		// Waits until menu section is expanded.
		CElementQuery::wait()->until(function () use ($element) {
			return CElementQuery::getDriver()->executeScript('return arguments[0].clientHeight ==='.
					' parseInt(arguments[0].style.maxHeight, 10)', [$element]);
		});
	}

	/**
	 * Check that the corresponding element exists.
	 *
	 * @param string $page		page name
	 *
	 * @return boolean
	 */
	public function exists($page) {
		return ($this->query('link', $page)->one(false)->isValid());
	}
}
