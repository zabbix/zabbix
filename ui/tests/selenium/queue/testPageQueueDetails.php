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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

class testPageQueueDetails extends CLegacyWebTest {
	public function testPageQueueDetails_CheckLayout() {
		$this->zbxTestLogin('queue.php?config=2');
		$this->zbxTestCheckTitle('Queue [refreshed every 30 sec.]');
		$this->zbxTestTextNotPresent('Cannot display item queue.');
		$this->zbxTestCheckHeader('Queue of items to be updated');
		$this->zbxTestDropdownSelectWait('config', 'Details');
		$this->zbxTestDropdownHasOptions('config', ['Overview', 'Overview by proxy', 'Details']);
		$this->zbxTestTextPresent(['Scheduled check', 'Delayed by', 'Host', 'Name']);
		$this->zbxTestTextPresent('Total:');
	}

}
