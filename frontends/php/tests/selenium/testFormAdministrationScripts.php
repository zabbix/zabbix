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

class testFormAdministrationScripts extends CWebTest {
	// Data provider
	public static function providerScripts() {
		// data - values for form inputs
		// saveResult - if save action should be successful
		// dbValues - values which should be in db if saveResult is true
		$data = [
			[
				[
					['name' => 'name', 'value' => 'script', 'type' => 'text'],
					['name' => 'command', 'value' => 'run', 'type' => 'text']
				],
				true,
				[
					'name' => 'script',
					'command' => 'run'
				]
			],
			[
				[
					['name' => 'name', 'value' => 'script1', 'type' => 'text'],
					['name' => 'command', 'value' => 'run', 'type' => 'text'],
					['name' => 'type', 'value' => 'IPMI', 'type' => 'select']
				],
				true,
				[
					'name' => 'script1',
					'command' => 'run',
					'type' => 1
				]
			],
			[
				[
					['name' => 'name', 'value' => 'script2', 'type' => 'text'],
					['name' => 'command', 'value' => 'run', 'type' => 'text'],
					['name' => 'enable_confirmation', 'type' => 'check']
				],
				false,
				[]
			],
			[
				[
					['name' => 'name', 'value' => 'script3', 'type' => 'text'],
					['name' => 'command', 'value' => '', 'type' => 'text']
				],
				false,
				[]
			]
		];
		return $data;
	}

	public function testFormAdministrationScripts_testLayout() {
		$this->zbxTestLogin('zabbix.php?action=script.edit');
		$this->zbxTestCheckTitle('Configuration of scripts');
		$this->zbxTestCheckHeader('Scripts');

		$this->zbxTestTextPresent(['Name']);
		$this->zbxTestAssertElementPresentId('name');

		$this->zbxTestTextPresent(['Type']);
		$this->zbxTestAssertElementPresentId('type');
		$this->zbxTestAssertElementText("//ul[@id='type']//label[@for='type_0']", 'IPMI');
		$this->zbxTestAssertElementText("//ul[@id='type']//label[@for='type_1']", 'Script');

		$this->zbxTestTextPresent(['Execute on', 'Zabbix agent', 'Zabbix server']);
		$this->zbxTestAssertElementPresentId('execute_on_0');
		$this->zbxTestAssertElementPresentId('execute_on_1');

		$this->zbxTestTextPresent(['Commands']);
		$this->zbxTestAssertElementPresentId('command');

		$this->zbxTestTextPresent(['Description']);
		$this->zbxTestAssertElementPresentId('description');

		$this->zbxTestTextPresent(['User group']);
		$this->zbxTestAssertElementPresentId('usrgrpid');
		$this->zbxTestDropdownHasOptions('usrgrpid', ['All', 'Disabled', 'Enabled debug mode', 'Guests', 'No access to the frontend', 'Zabbix administrators']);

		$this->zbxTestTextPresent(['Host group']);
		$this->zbxTestAssertElementPresentId('hgstype');
		$this->zbxTestDropdownHasOptions('hgstype', ['All', 'Selected']);

		$this->zbxTestTextPresent(['Required host permissions']);
		$this->zbxTestAssertElementPresentId('host_access');
		$this->zbxTestAssertElementText("//ul[@id='host_access']//label[@for='host_access_0']", 'Read');
		$this->zbxTestAssertElementText("//ul[@id='host_access']//label[@for='host_access_1']", 'Write');

		$this->zbxTestTextPresent(['Enable confirmation']);
		$this->zbxTestAssertElementPresentId('enable_confirmation');
		$this->assertFalse($this->zbxTestCheckboxSelected('enable_confirmation'));

		$this->zbxTestTextPresent(['Confirmation text']);
		$this->zbxTestAssertElementPresentId('confirmation');
	}

	public function testFormAdministrationScripts_backup() {
		DBsave_tables('scripts');
	}

	/**
	 * @dataProvider providerScripts
	 */
	public function testFormAdministrationScripts_testCreate($data, $resultSave, $dbValues) {
		$this->zbxTestLogin('zabbix.php?action=script.edit');
		$this->zbxTestCheckTitle('Configuration of scripts');
		$this->zbxTestCheckHeader('Scripts');

		foreach ($data as $field) {
			switch ($field['type']) {
				case 'text':
					$this->zbxTestInputType($field['name'], $field['value']);
					break;
				case 'select':
					$this->zbxTestClickXpathWait("//ul[@id='".$field['name']."']//label[text()='".$field['value']."']");
					break;
				case 'check':
					$this->zbxTestCheckboxSelect($field['name']);
					break;
			}

			if ($field['name'] == 'name') {
				$keyField = $field['value'];
			}
		}

		$sql = 'SELECT '.implode(', ', array_keys($dbValues)).' FROM scripts';
		if ($resultSave && isset($keyField)) {
			$sql .= ' WHERE name='.zbx_dbstr($keyField);
		}

		if (!$resultSave) {
			$sql = 'SELECT * FROM scripts';
			$DBhash = DBhash($sql);
		}

		$this->zbxTestClickWait('add');

		if ($resultSave) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Script added');

			$dbres = DBfetch(DBselect($sql));
			foreach ($dbres as $field => $value) {
				$this->assertEquals($value, $dbValues[$field]);
			}
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add script');
			$this->assertEquals($DBhash, DBhash($sql));
		}
	}

	public function testFormAdministrationScripts_restore() {
		DBrestore_tables('scripts');
	}

}
