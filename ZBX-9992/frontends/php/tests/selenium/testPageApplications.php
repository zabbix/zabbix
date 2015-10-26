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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testPageApplications extends CWebTest {

	public static function allHosts() {
		return [
			[
				[
					// "Template OS Linux"
					'hostid' => 10001,
					'status' => HOST_STATUS_TEMPLATE
				]
			],
			[
				[
					// "Test host" ("ЗАББИКС Сервер")
					'hostid' => 10084,
					'status' => HOST_STATUS_MONITORED
				]
			]
		];
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageApplications_CheckLayout($data) {
		$this->zbxTestLogin('applications.php?groupid=0&hostid='.$data['hostid']);

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestTextPresent('CONFIGURATION OF APPLICATIONS');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent($data['status'] == HOST_STATUS_TEMPLATE ? 'Template list' : 'Host list');

		// table
		$this->zbxTestTextPresent(['Applications', 'Show']);

		$this->zbxTestDropdownHasOptions('action', ['Enable selected', 'Disable selected', 'Delete selected']);
	}
}
