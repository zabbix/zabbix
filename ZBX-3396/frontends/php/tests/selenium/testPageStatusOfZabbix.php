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

class testPageStatusOfZabbix extends CWebTest {
	public function testPageStatusOfZabbix_CheckLayout() {
		$this->login('report1.php');
		$this->checkTitle('Status of Zabbix');
		$this->ok('Status of Zabbix');
		$this->ok('STATUS OF ZABBIX');
		$this->ok('Report');
		// Header
		$this->ok(array('Parameter', 'Value', 'Details'));
		// Data
		$this->ok('Zabbix server is running');
		$this->ok('Number of hosts (monitored/not monitored/templates)');
		$this->ok('Number of items (monitored/disabled/not supported)');
		$this->ok('Number of triggers (enabled/disabled)[problem/unknown/ok]');
		$this->ok('Number of users (online)');
		$this->ok('Required server performance, new values per second');
	}

	public function testPageStatusOfZabbix_VerifyDisplayedNumbers() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
