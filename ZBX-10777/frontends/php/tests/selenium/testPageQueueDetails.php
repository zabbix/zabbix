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

class testPageQueueDetails extends CWebTest {
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
