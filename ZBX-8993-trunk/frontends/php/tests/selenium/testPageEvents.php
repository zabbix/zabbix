<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

class testPageEvents extends CWebTest {

	public function testPageEvents_Triggers_CheckLayout() {
		$this->zbxTestLogin('events.php');

		$this->zbxTestDropdownSelectWait('source', 'Trigger');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestDropdownSelectWait('hostid', 'all');

		$this->zbxTestCheckTitle('Latest events \[refreshed every 30 sec.\]');
		$this->zbxTestTextPresent('HISTORY OF EVENTS');
		$this->zbxTestTextPresent('Group');
		$this->zbxTestTextPresent('Host');
		$this->zbxTestTextPresent('Source');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(array('Time', 'Host', 'Description', 'Status', 'Severity', 'Duration', 'Ack', 'Actions'));
	}

	public function testPageEvents_Discovery_CheckLayout() {
		$this->zbxTestLogin('events.php');

		$this->zbxTestDropdownSelectWait('source', 'Discovery');

		$this->zbxTestCheckTitle('Latest events \[refreshed every 30 sec.\]');
		$this->zbxTestTextPresent('HISTORY OF EVENTS');
		$this->zbxTestTextPresent('Source');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(array('Time', 'IP', 'DNS', 'Description', 'Status'));
	}

	public function testPageEvents_Triggers_NoHostNames() {
		$this->zbxTestLogin('events.php');
		$this->zbxTestDropdownSelectWait('source', 'Trigger');
		$this->zbxTestCheckTitle('Latest events \[refreshed every 30 sec.\]');

		$this->checkNoRealHostnames();
	}

	public function testPageEvents_Discovery_NoHostNames() {
		$this->zbxTestLogin('events.php');
		$this->zbxTestDropdownSelectWait('source', 'Discovery');
		$this->zbxTestCheckTitle('Latest events \[refreshed every 30 sec.\]');

		$this->checkNoRealHostnames();
	}
}
