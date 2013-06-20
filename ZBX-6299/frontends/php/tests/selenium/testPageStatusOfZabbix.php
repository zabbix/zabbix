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

class testPageStatusOfZabbix extends CWebTest {
	public function testPageStatusOfZabbix_CheckLayout() {
		$this->zbxTestLogin('report1.php');
		$this->checkTitle('Status of Zabbix');
		$this->zbxTestTextPresent('STATUS OF ZABBIX');

		// header
		$this->zbxTestTextPresent(array('Parameter', 'Value', 'Details'));

		// data
		$this->zbxTestTextPresent('Zabbix server is running');
		$this->zbxTestTextPresent('Number of hosts (monitored/not monitored/templates)');
		$this->zbxTestTextPresent('Number of items (monitored/disabled/not supported)');
		$this->zbxTestTextPresent('Number of triggers (enabled/disabled) [problem/ok]');
		$this->zbxTestTextPresent('Number of users (online)');
		$this->zbxTestTextPresent('Required server performance, new values per second');
	}

	public function testPageStatusOfZabbix_VerifyDisplayedNumbers() {
// TODO
		$this->markTestIncomplete();
	}
}
