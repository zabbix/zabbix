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

/**
 * @backup screens
 */
class testFormScreen extends CWebTest {
	public $testscreen = 'Test screen (clock)';
	public $new_screen_name = 'Changed screen name';
	public $testscreen_graph = 'Test screen (graph)';
	public $cloned_screen = 'Cloned screen';
	public $testscreen_history = 'Test screen (history of actions)';
	public $testscreen_ = 'Test screen (simple graph)';

	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Test Screen',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Test Screen owner guest, max column and row',
					'owner' => 'guest',
					'columns' => 100,
					'rows' => 100,
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Test Screen',
					'error_msg' => 'Cannot add screen',
					'errors' => [
						'Screen "Test Screen" already exists.',
					]

				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => '',
					'columns' => 1,
					'rows' => 1,
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Name": cannot be empty.',
					]

				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'without owner',
					'columns' => 1,
					'rows' => 1,
					'remove_owner' => true,
					'error_msg' => 'Cannot add screen',
					'errors' => [
						'Screen owner cannot be empty.',
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'empty columns and rows',
					'columns' => 0,
					'rows' => 0,
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "0" for "Columns" field: must be between 1 and 100.',
						'Incorrect value "0" for "Rows" field: must be between 1 and 100.',
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'incorrect columns and rows',
					'columns' => 101,
					'rows' => 101,
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "101" for "Columns" field: must be between 1 and 100.',
						'Incorrect value "101" for "Rows" field: must be between 1 and 100.',
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormScreen_Create($data) {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestClickButton('Create screen');

		$this->zbxTestInputTypeWait('name', $data['name']);

		if (isset($data['columns'])) {
			$this->zbxTestInputTypeOverwrite('hsize', $data['columns']);
		}
		$hsize = $this->zbxTestGetValue("//input[@id='hsize']");

		if (isset($data['rows'])) {
			$this->zbxTestInputTypeOverwrite('vsize', $data['rows']);
		}
		$vsize = $this->zbxTestGetValue("//input[@id='vsize']");

		if (isset($data['owner'])) {
			$this->zbxTestClickButtonMultiselect('userid');
			$this->zbxTestLaunchOverlayDialog('Users');
			$this->zbxTestClickLinkTextWait($data['owner']);
		}

		if (isset($data['remove_owner'])) {
			$this->zbxTestClickXpathWait("//div[@id='userid']//span[@class='subfilter-disable-btn']");
		}

		if ($data['expected'] == TEST_GOOD) {
			$user_id = $this->zbxTestGetAttributeValue("//div[@id='userid']//li[@data-id]", 'data-id');
		}

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Screen added');
				$this->assertEquals(1, DBcount("SELECT screenid FROM screens WHERE name='".$data['name']."'"));
				break;

		case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				break;
		}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT name, hsize, vsize, userid FROM screens where name = '".$data['name']."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $data['name']);
				$this->assertEquals($row['hsize'], $hsize);
				$this->assertEquals($row['vsize'], $vsize);
				$this->assertEquals($row['userid'], $user_id);
			}
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestClickXpathWait("//a[text()='".$data['name']."']/../..//a[text()='Properties']");
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('name'));
			$this->zbxTestAssertElementValue('name', $data['name']);
			$this->zbxTestAssertElementValue('hsize', $hsize);
			$this->zbxTestAssertElementValue('vsize', $vsize);
			$this->zbxTestAssertAttribute("//div[@id='userid']//li[@data-id]", 'data-id', $user_id);
		}
	}

	public function testFormScreen_UpdateScreenName() {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestClickXpathWait("//a[text()='$this->testscreen']/../..//a[text()='Properties']");

		$this->zbxTestInputTypeOverwrite('name', $this->new_screen_name);
		$this->zbxTestClickWait('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Screen updated');
		$this->zbxTestTextPresent($this->new_screen_name);
		$this->assertEquals(1, DBcount("SELECT screenid FROM screens WHERE name='$this->new_screen_name'"));
		$this->assertEquals(0, DBcount("SELECT screenid FROM screens WHERE name='$this->testscreen'"));
	}

	public function testFormScreen_CloneScreen() {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestClickXpathWait("//a[text()='$this->testscreen_graph']/../..//a[text()='Properties']");
		$this->zbxTestClickWait('clone');
		$this->zbxTestInputType('name', $this->cloned_screen);
		$this->zbxTestClickWait('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Screen added');
		$this->zbxTestTextPresent($this->cloned_screen);
		$this->assertEquals(1, DBcount("SELECT screenid FROM screens WHERE name='$this->cloned_screen'"));
		$this->assertEquals(1, DBcount("SELECT screenid FROM screens WHERE name='$this->testscreen_graph'"));
	}

	public function testFormScreen_DeleteScreen() {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestClickXpathWait("//a[text()='$this->testscreen_history']/../..//a[text()='Properties']");
		$this->zbxTestClickWait('delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Screen deleted');
		$this->assertEquals(0, DBcount("SELECT screenid FROM screens WHERE name='$this->testscreen_history'"));
	}

	public function testFormScreen_ZBX6030() {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestClickLinkTextWait($this->testscreen_);
		$this->zbxTestClickWait('edit');
		$this->zbxTestClickLinkTextWait('Change');
		$this->assertFalse($this->zbxTestCheckboxSelected('dynamic'));
		$this->zbxTestCheckboxSelect('dynamic');
		$this->zbxTestInputTypeOverwrite('colspan', '1');
		$this->zbxTestInputTypeOverwrite('rowspan', '1');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Screen updated');
		$this->zbxTestClickLinkTextWait('Change');
		$this->assertTrue($this->zbxTestCheckboxSelected('dynamic'));
		$this->zbxTestCheckboxSelect('dynamic', false);
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Screen updated');
		$this->zbxTestClickLinkTextWait('Change');
		$this->assertFalse($this->zbxTestCheckboxSelected('dynamic'));
	}
}
