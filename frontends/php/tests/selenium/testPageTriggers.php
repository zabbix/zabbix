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

class testPageTriggers extends CWebTest {
	// Returns all hosts
	public static function data() {
		return DBdata(
			'SELECT hostid,status'.
			' FROM hosts'.
			' WHERE host LIKE '.zbx_dbstr('%-layout-test-%')
		);
	}

	/**
	* @dataProvider data
	*/
	public function testPageTriggers_CheckLayout($data) {
		// Go to the list of triggers
		$this->zbxTestLogin('triggers.php?hostid='.$data['hostid']);
		// We are in the list of items
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
		$this->zbxTestTextPresent('Triggers');
		$this->zbxTestTextPresent('Displaying');
		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('Host list');
			// Header
			$this->zbxTestTextPresent(
				array(
					'Severity',
					'Name',
					'Expression',
					'Status',
					'Error'
				)
			);
		}
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('Template list');
			// Header
			$this->zbxTestTextPresent(
				array(
					'Severity',
					'Name',
					'Expression',
					'Status'
				)
			);
			$this->zbxTestTextNotPresent('Error');
		}
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestDropdownHasOptions('go', array(
				'Enable selected',
				'Disable selected',
				'Mass update',
				'Copy selected to ...',
				'Delete selected'
			));
	}
}
