<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testPageItems extends CWebTest {
	// Returns all hosts
	public static function allHosts() {
		return DBdata('select * from hosts where status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')');
	}

	/**
	* @dataProvider allHosts
	*/

	public function testPageItems_CheckLayout($host) {
		$hostid = $host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('HOSTS');
		// Go to the list of items
		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();
		// We are in the list of items
		$this->checkTitle('Configuration of items');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
		$this->zbxTestTextPresent('Items');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent('Host list');
		// Header
		$this->zbxTestTextPresent(
			array(
				'Wizard',
				'Name',
				'Triggers',
				'Key',
				'Interval',
				'History',
				'Trends',
				'Type',
				'Applications',
				'Status',
				'Error'
			)
		);
		// someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc

		$this->zbxTestDropdownHasOptions('go', array(
				'Enable selected',
				'Disable selected',
				'Mass update',
				'Copy selected to ...',
				'Clear history for selected',
				'Delete selected'
		));
	}
}
