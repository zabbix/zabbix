<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageSearch extends CWebTest {
	public function testPageHosts_FindZabbixServer() {
		$this->login('dashboard.php');
		$this->input_type('search', "ЗАББИКС Сервер");
		$this->keyPress('search', "\\13");
		$this->wait();
		$this->checkTitle('Search');
		$this->ok('Hosts');
		$this->ok('Displaying 1 of 1 found');
		$this->ok('Displaying 0 of 0 found');
		$this->ok('Host groups');
		$this->ok('Templates');
		$this->ok('ЗАББИКС Сервер');
		$this->nok('Test server');
		$this->ok('127.0.0.1');
		$this->ok('Latest data');
		$this->ok('Triggers');
		$this->ok('Applications');
		$this->ok('Items');
		$this->ok('Triggers');
		$this->ok('Graphs');
		$this->ok('Events');
	}

	public function testPageHosts_FindNone() {
		$this->login('dashboard.php');
		$this->input_type('search', "_");
		$this->keyPress('search', "\\13");
		$this->wait();
		$this->checkTitle('Search');
		$this->nok('Displaying 1 of 1 found');
		$this->nok('ЗАББИКС Сервер');
		$this->ok('Displaying 0 of 0 found');
		$this->ok('...');
	}

	public function testPageHosts_FindNone2() {
		$this->login('dashboard.php');
		$this->input_type('search', "%");
		$this->keyPress('search', "\\13");
		$this->wait();
		$this->checkTitle('Search');
		$this->nok('Displaying 1 of 1 found');
		$this->nok('ЗАББИКС Сервер');
		$this->ok('Displaying 0 of 0 found');
		$this->ok('...');
	}

	public function testPageHosts_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
