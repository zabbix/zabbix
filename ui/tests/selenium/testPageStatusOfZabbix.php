<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageStatusOfZabbix extends CLegacyWebTest {
	public function testPageStatusOfZabbix_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=report.status');
		$this->zbxTestCheckTitle('System information');
		$this->zbxTestCheckHeader('System information');
		$this->zbxTestTextPresent(['Parameter', 'Value', 'Details']);

		$this->zbxTestTextPresent('Zabbix server is running');
		$this->zbxTestTextPresent('Number of hosts (enabled/disabled)');
		$this->zbxTestTextPresent('Number of templates');
		$this->zbxTestTextPresent('Number of items (enabled/disabled/not supported)');
		$this->zbxTestTextPresent('Number of triggers (enabled/disabled [problem/ok])');
		$this->zbxTestTextPresent('Number of users (online)');
		$this->zbxTestTextPresent('Required server performance, new values per second');
	}

}
