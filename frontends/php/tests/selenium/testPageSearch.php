<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageSearch extends CWebTest {
	public function testPageSearch_FindZabbixServer() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$this->zbxTestInputTypeWait('search', 'ЗАББИКС Сервер');
		$this->webDriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
		$this->zbxTestCheckTitle('Search');
		$this->zbxTestCheckHeader('Search: ЗАББИКС Сервер');
		$this->zbxTestTextPresent(['Hosts', 'Host groups', 'Templates']);
		$this->zbxTestTextPresent('Displaying 1 of 1 found');
		$this->zbxTestTextPresent('No data found.');
		$this->zbxTestTextPresent('ЗАББИКС Сервер');
		$this->zbxTestTextNotPresent('Zabbix server');
		$this->zbxTestTextPresent('127.0.0.1');
		$this->zbxTestTextPresent(['Latest data', 'Triggers', 'Applications', 'Items', 'Triggers', 'Graphs', 'Problems']);
	}

	public function testPageSearch_FindNotExistingHost() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$this->zbxTestInputTypeWait('search', 'Not existing host');
		$this->webDriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
		$this->zbxTestCheckTitle('Search');
		$this->zbxTestCheckHeader('Search: Not existing host');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
		$this->zbxTestTextPresent('No data found.');
		$this->zbxTestTextNotPresent('Zabbix server');
	}

	/**
	 * Test if the global search form is not being submitted with empty search string.
	 */
	public function testPageSearch_FindEmptyString() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');

		// Do not search if the search field is empty.
		$this->zbxTestInputTypeWait('search', '');
		$this->webDriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
		$this->zbxTestCheckTitle('Dashboard');
		$this->zbxTestCheckHeader('Dashboard');

		// Do not search if search string consists only of whitespace characters.
		$this->zbxTestInputTypeWait('search', '   ');
		$this->webDriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
		$this->zbxTestCheckTitle('Dashboard');
		$this->zbxTestCheckHeader('Dashboard');
	}
}
