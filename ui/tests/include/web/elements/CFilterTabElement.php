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
 * Filter tabs element
 */
class CFilterTabElement extends CElement {

	/**
	 * Get names of saved filters tabs. If there are no saved filters, return null.
	 *
	 * @return array
	 */
	public function getTitles() {
		$tabs = $this->query('xpath:.//a[@class="tabfilter-item-link"]')->all();
		if ($tabs->count() > 0) {
			return $tabs->asText();
		}

		return null;
	}

	/**
	 * Get name of selected filter tab.
	 *
	 * @return string
	 */
	public function getSelectedTabName() {
		return $this->query('xpath:.//li[contains(@class, "tabfilter-item-label") and contains(@class, "selected")]/'.
				'a[@class="tabfilter-item-link"]')->one()->getText();
	}

	/**
	 * Select tab by name.
	 *
	 * @param string $name		filter name to be selected
	 * @param integer $count	filter number, if there are several filters with same name
	 */
	public function selectTab($name, $count = null) {
		$xpath = 'xpath:(.//a[@class="tabfilter-item-link" and text()='.CXPathHelper::escapeQuotes($name).'])';

		if ($count !== null) {
			$this->query($xpath.'['.$count.']')->one()->click(true);
		}
		else {
			$this->query($xpath)->one()->click(true);
		}
	}

	/**
	 * Select filter properties.
	 *
	 * @param string $name		filter name to be selected
	 * @param integer $count	filter number, if there are several filters with same name
	 */
	public function editProperties($name = null, $count = null) {
		if ($name !== null) {
			$this->selectTab($name, $count);
		}

		$this->query('xpath:.//a[@class="icon-edit"]')->one()->waitUntilReady()->click(true);
	}
}
