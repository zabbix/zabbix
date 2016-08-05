<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

class testPageLatestData extends CWebTest {
	public function testPageLatestData_CheckLayout() {
		$this->zbxTestLogin('latest.php');
		$this->zbxTestCheckTitle('Latest data [refreshed every 30 sec.]');
		$this->zbxTestCheckHeader('Latest data');
		$this->zbxTestTextPresent(['Host groups', 'Hosts', 'Application', 'Name', 'Show items without data', 'Show details']);
		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent(['Host', 'Name', 'Last check', 'Last value', 'Change']);
	}

// Check that no real host or template names displayed
	public function testPageLatestData_NoHostNames() {
		$this->zbxTestLogin('latest.php');
		$this->zbxTestCheckTitle('Latest data [refreshed every 30 sec.]');
		$this->zbxTestCheckNoRealHostnames();
	}
}
