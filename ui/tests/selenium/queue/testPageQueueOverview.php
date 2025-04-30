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


require_once __DIR__.'/../../include/CLegacyWebTest.php';

class testPageQueueOverview extends CLegacyWebTest {
	public function testPageQueueOverview_CheckLayout() {
		$this->zbxTestLogin('queue.php?config=0');
		$this->zbxTestCheckTitle('Queue [refreshed every 30 sec.]');
		$this->zbxTestTextNotPresent('Cannot display item queue.');
		$this->zbxTestCheckHeader('Queue of items to be updated');
		$this->zbxTestDropdownSelectWait('config', 'Overview');
		$this->zbxTestDropdownHasOptions('config', ['Overview', 'Overview by proxy', 'Details']);
		$this->zbxTestTextPresent(['Items', '5 seconds', '10 seconds', '30 seconds', '1 minute', '5 minutes', 'More than 10 minutes']);
		$this->zbxTestTextPresent(
			[
				'Zabbix agent',
				'Zabbix agent (active)',
				'Simple check',
				'SNMPv2 agent',
				'SNMPv2 agent',
				'SNMPv3 agent',
				'Zabbix internal',
				'External check',
				'Database monitor',
				'IPMI agent',
				'SSH agent',
				'TELNET agent',
				'JMX agent',
				'Calculated'
			]
		);
	}

}
