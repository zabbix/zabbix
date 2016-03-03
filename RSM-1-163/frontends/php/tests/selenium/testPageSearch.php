<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
	public function testPageHosts_FindZabbixServer() {
		$this->zbxTestLogin('dashboard.php');
		$this->input_type('search', "ЗАББИКС Сервер");
		$this->keyPress('search', "\\13");
		$this->wait();
		$this->checkTitle('Search');
		$this->zbxTestTextPresent('Hosts');
		$this->zbxTestTextPresent('Displaying 1 of 1 found');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
		$this->zbxTestTextPresent('Host groups');
		$this->zbxTestTextPresent('Templates');
		$this->zbxTestTextPresent('ЗАББИКС Сервер');
		$this->zbxTestTextNotPresent('Test server');
		$this->zbxTestTextPresent('127.0.0.1');
		$this->zbxTestTextPresent('Latest data');
		$this->zbxTestTextPresent('Triggers');
		$this->zbxTestTextPresent('Applications');
		$this->zbxTestTextPresent('Items');
		$this->zbxTestTextPresent('Triggers');
		$this->zbxTestTextPresent('Graphs');
		$this->zbxTestTextPresent('Events');
	}

	public function testPageHosts_FindNone() {
		$this->zbxTestLogin('dashboard.php');
		$this->input_type('search', "_");
		$this->keyPress('search', "\\13");
		$this->wait();
		$this->checkTitle('Search');
		$this->zbxTestTextNotPresent('Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('ЗАББИКС Сервер');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
		$this->zbxTestTextPresent('...');
	}

	public function testPageHosts_FindNone2() {
		$this->zbxTestLogin('dashboard.php');
		$this->input_type('search', "%");
		$this->keyPress('search', "\\13");
		$this->wait();
		$this->checkTitle('Search');
		$this->zbxTestTextNotPresent('Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('ЗАББИКС Сервер');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
		$this->zbxTestTextPresent('...');
	}

	public function testPageHosts_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
