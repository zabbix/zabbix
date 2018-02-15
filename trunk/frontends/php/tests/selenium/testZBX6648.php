<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

class testZBX6648 extends CWebTest {


	// Returns test data
	public static function zbx_data() {
		return [
			[
				[
					'hostgroup' => 'ZBX6648 All Triggers',
					'host' => 'ZBX6648 All Triggers Host',
					'triggers' => 'both'
				]
			],
			[
				[
					'hostgroup' => 'ZBX6648 Enabled Triggers',
					'host' => 'ZBX6648 Enabled Triggers Host',
					'triggers' => 'enabled'
				]
			],
			[
				[
					'hostgroup' => 'ZBX6648 Disabled Triggers',
					'host' => 'ZBX6648 Disabled Triggers Host',
					'triggers' => 'disabled'
				]
			],
			[
				[
					'hostgroup' => 'ZBX6648 Group No Hosts',
					'triggers' => 'no triggers'
				]
			]
		];
	}

	/**
	 * @dataProvider zbx_data
	 */
	public function testZBX6648_eventFilter($zbx_data) {
		$this->zbxTestLogin('zabbix.php?action=problem.view');

		$this->zbxTestClickButtonMultiselect('filter_triggerids_');
		$this->zbxTestLaunchOverlayDialog('Triggers');

		switch ($zbx_data['triggers']) {
			case 'both' :
				$this->zbxTestDropdownSelectWait('groupid', $zbx_data['hostgroup']);
				$this->zbxTestDropdownSelectWait('hostid', $zbx_data['host']);
				break;
			case 'enabled' :
				$this->zbxTestDropdownSelectWait('groupid', $zbx_data['hostgroup']);
				$this->zbxTestDropdownSelectWait('hostid', $zbx_data['host']);
				break;
			case 'disabled' :
				$hostgroup = $zbx_data['hostgroup'];
				$host = $zbx_data['host'];
				$this->zbxTestAssertElementNotPresentXpath("//select[@id='groupid']/option[text()='$hostgroup']");
				$this->zbxTestAssertElementNotPresentXpath("//select[@id='hostid']/option[text()='$host']");
				break;
			case 'no triggers' :
				$hostgroup = $zbx_data['hostgroup'];
				$this->zbxTestAssertElementNotPresentXpath("//select[@id='groupid']/option[text()='$hostgroup']");
				break;
		}
	}
}
