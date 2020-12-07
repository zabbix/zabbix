<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Filter tabs element
 */
class CFilterTabElement extends CElement {

	/**
	 * Get names of saved filters tabs. If there is no saved filters, return null.
	 *
	 * @return array
	 */
	public function getTitles() {
		$result = [];

		if ($this->query('xpath:.//a[@class="tabfilter-item-link"]')->one(false)->isValid() === false) {
			$result = null;
		}
		else {
			$tabs = $this->query('xpath:.//a[@class="tabfilter-item-link"]')->all();
			foreach ($tabs as $tab) {
				$result[] = $tab->getText();
			}
		}

		return $result;
	}

	/**
	 * Get name of selected filter tab.
	 *
	 * @return string
	 */
	public function getText() {
		return $this->query('xpath:.//li[contains(@class, "tabfilter-item-label") and contains(@class, "selected")]/'.
				'a[@class="tabfilter-item-link"]')->one()->getText();
	}

	/**
	 * Select tab by name.
	 *
	 * @param string $data		filter name to be selected
	 * @param integer $count	filter name to be selected, if several filters have same names
	 */
	public function selectTab($data, $count = null) {
		if ($count !== null) {
			$this->query('xpath:(.//a[@class="tabfilter-item-link" and text()="'.$data.'"])['.$count.']')->one()->click();
		}
		else {
			$this->query('xpath:(.//a[@class="tabfilter-item-link" and text()="'.$data.'"])')->one()->click();
		}
	}

	/**
	 * Get filter properties.
	 *
	 * @param string $name		filter name to be selected
	 * @param integer $count	filter name to be selected, if several filters have same names
	 */
	public function getProperties($name = null, $count = null) {
		if ($name !== null && $count === null) {
			$this->selectTab($name);
			$this->query('xpath:.//a[@class="icon-edit"]')->one()->waitUntilReady()->click();
		}

		if ($name !== null && $count !== null) {
			$this->selectTab($name, $count);
			$this->query('xpath:.//a[@class="icon-edit"]')->one()->waitUntilReady()->click();
		}

		if ($name === null) {
			$this->query('xpath:.//a[@class="icon-edit"]')->one()->click();
		}
	}
}
