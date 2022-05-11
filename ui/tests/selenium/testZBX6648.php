<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testZBX6648 extends CLegacyWebTest {


	// Returns test data
	public static function zbx_data() {
		return [
			[
				[
					'host' => 'ZBX6648 All Triggers Host',
					'hostgroup' => 'ZBX6648 All Triggers',
					'triggers' => 'both'
				]
			],
			[
				[
					'host' => 'ZBX6648 Enabled Triggers Host',
					'hostgroup' => 'ZBX6648 Enabled Triggers',
					'triggers' => 'enabled'
				]
			],
			[
				[
					'hostgroup' => 'ZBX6648 Disabled Triggers',
					'triggers' => 'disabled'
				]
			],
			[
				[
					'host' => 'Test item host',
					'hostgroup' => 'Zabbix servers',
					'triggers' => 'no triggers'
				]
			],
			[
				[
					'hostgroup' => 'ZBX6648 Group No Hosts',
					'triggers' => 'no hosts'
				]
			]
		];
	}

	/**
	 * @dataProvider zbx_data
	 */
	public function testZBX6648_eventFilter($zbx_data) {
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestClickButtonMultiselect('triggerids_0');
		$this->zbxTestLaunchOverlayDialog('Triggers');

		switch ($zbx_data['triggers']) {
			case 'both' :
			case 'enabled' :
				$host = COverlayDialogElement::find()->one()->query('class:multiselect-control')->asMultiselect()->one();
				$host->fill([
					'values' => $zbx_data['host'],
					'context' => $zbx_data['hostgroup']
				]);
				$this->zbxTestLaunchOverlayDialog('Triggers');
				break;
			case 'disabled' :
			case 'no hosts' :
				COverlayDialogElement::find()->one()->query('class:multiselect-button')->one()->click();
				$this->zbxTestLaunchOverlayDialog('Hosts');
				COverlayDialogElement::find()->all()->last()->query('class:multiselect-button')->one()->click();
				$this->zbxTestLaunchOverlayDialog('Host groups');
				$this->zbxTestAssertElementNotPresentXpath('//a[text()="'.$zbx_data['hostgroup'].'"]');
				break;
			case 'no triggers' :
				COverlayDialogElement::find()->one()->query('class:multiselect-button')->one()->click();
				COverlayDialogElement::find()->all()->last()->waitUntilReady()->setDataContext($zbx_data['hostgroup']);
				$this->zbxTestLaunchOverlayDialog('Hosts');
				$this->zbxTestAssertElementNotPresentXpath('//a[text()="'.$zbx_data['host'].'"]');
				break;
		}
	}
}
