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
 * @backup hosts
 */
class testFormTemplate extends CWebTest {
	public $template = 'Form test template';
	public $template_edit_name = 'Template-layout-test-001';
	public $template_clone = 'Template OS Linux';
	public $template_full_delete = 'Inheritance test template';

	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Test Template',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Test Template name',
					'visible_name' => 'Test template with visible name',
					'group' => 'Linux servers',
					'new_group' => 'Selenium new group',
					'other_group' => 'Zabbix servers',
					'description' => 'template description',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Test Template',
					'error_msg' => 'Cannot add template',
					'errors' => [
						'Template "Test Template" already exists.',
					]

				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Existing visible name',
					'visible_name' => 'Test template with visible name',
					'error_msg' => 'Cannot add template',
					'errors' => [
						'Template with the same visible name "Test template with visible name" already exists.',
					]

				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => '',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Template name": cannot be empty.',
					]

				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Without groups',
					'remove_group' => 'Templates',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Field "groups" is mandatory.',
					]

				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormTemplate_Create($data) {
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Templates');
		$this->zbxTestContentControlButtonClickTextWait('Create template');
		$this->zbxTestInputTypeWait('template_name', $data['name']);
		$this->zbxTestAssertElementValue('template_name', $data['name']);

		if (isset ($data['visible_name'])) {
			$this->zbxTestInputTypeOverwrite('visiblename', $data['visible_name']);
			$this->zbxTestAssertElementValue('visiblename', $data['visible_name']);
		}

		if (array_key_exists('group', $data)) {
			$this->zbxTestClickButtonMultiselect('groups_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkTextWait($data['group']);
		}

		if (array_key_exists('new_group', $data)) {
			$this->zbxTestMultiselectNew('groups_', $data['new_group']);
		}

		if (isset ($data['description'])) {
			$this->zbxTestInputTypeWait('description', $data['description']);
		}

		if (array_key_exists('remove_group', $data)) {
			$this->zbxTestMultiselectRemove('groups_', $data['remove_group']);
		}

		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Template added');
				$this->assertEquals(1, DBcount("SELECT hostid FROM hosts WHERE host='".$data['name']."'"));
				break;

		case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				break;
		}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT host, name, status, description FROM hosts where host = '".$data['name']."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['host'], $data['name']);
				$this->assertEquals($row['status'], HOST_STATUS_TEMPLATE);
				if (isset ($data['visible_name'])) {
					$this->assertEquals($data['visible_name'], $row['name']);
				}
				if (isset ($data['description'])) {
					$this->assertEquals($data['description'], $row['description']);
				}
			}
			if (isset ($data['new_group'])) {
				$this->assertEquals(1, DBcount("SELECT groupid FROM groups WHERE name='".$data['new_group']."'"));
			}
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestDropdownSelectWait('groupid', 'Templates');

			if (isset ($data['visible_name'])) {
				$this->zbxTestClickLinkTextWait($data['visible_name']);;
			}
			else {
				$this->zbxTestClickLinkTextWait($data['name']);
			}

			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('template_name'));
			$this->zbxTestAssertElementValue('template_name', $data['name']);

			$this->zbxTestMultiselectAssertSelected('groups_', 'Templates');

			if (array_key_exists('new_group', $data)) {
				$this->zbxTestMultiselectAssertSelected('groups_', $data['new_group']);
			}

			if (array_key_exists('group', $data)) {
				$this->zbxTestMultiselectAssertSelected('groups_', $data['group']);
			}

			if (isset ($data['visible_name'])) {
				$this->zbxTestAssertElementValue('visiblename', $data['visible_name']);
			}

			if (isset ($data['description'])) {
				$this->zbxTestAssertElementValue('description', $data['description']);
			}
		}
	}

	/**
	 * Adds two macros to an existing host.
	 */
	public function testFormTemplate_AddMacros() {
		$template = DBfetch(DBSelect("select hostid from hosts where host='".$this->template."'"));

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickLinkTextWait($this->template);
		$this->zbxTestTabSwitch('Macros');
		$this->zbxTestInputTypeWait('macros_0_macro', '{$TEST_MACRO}');
		$this->zbxTestInputType('macros_0_value', '1');
		$this->zbxTestClick('macro_add');
		$this->zbxTestInputTypeWait('macros_1_macro', '{$TEST_MACRO2}');
		$this->zbxTestInputType('macros_1_value', '2');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template updated');

		$this->zbxTestClickLinkTextWait($this->template);
		$this->zbxTestTabSwitch('Macros');
		$this->zbxTestAssertElementValue('macros_0_macro', '{$TEST_MACRO}');
		$this->zbxTestAssertElementValue('macros_0_value', '1');
		$this->zbxTestAssertElementValue('macros_1_macro', '{$TEST_MACRO2}');
		$this->zbxTestAssertElementValue('macros_1_value', '2');
		$this->assertEquals(2, DBcount("SELECT * FROM hostmacro WHERE hostid='".$template['hostid']."'"));
	}

	public function testFormTemplate_UpdateTemplateName() {
		$new_template_name = 'Changed template name';

		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickLinkTextWait($this->template_edit_name);
		$this->zbxTestInputTypeOverwrite('template_name', $new_template_name);
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template updated');
		$this->assertEquals(1, DBcount("SELECT hostid FROM hosts WHERE host='".$new_template_name."'"));
		$this->assertEquals(0, DBcount("SELECT hostid FROM hosts WHERE host='$this->template_edit_name'"));
	}

	public function testFormTemplate_CloneTemplate() {
		$cloned_template_name = 'Cloned template';
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestAssertElementPresentId('filter_name');
		$this->zbxTestDoubleClickLinkText($this->template_clone, 'template_name');
		$this->zbxTestClickWait('clone');
		$this->zbxTestInputTypeOverwrite('template_name', $cloned_template_name);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template added');
		$this->assertEquals(1, DBcount("SELECT hostid FROM hosts WHERE host='".$cloned_template_name."'"));
		$this->assertEquals(1, DBcount("SELECT hostid FROM hosts WHERE host='$this->template_clone'"));

		$template = DBfetch(DBSelect("select hostid from hosts where host like '".$cloned_template_name."'"));
		$this->assertEquals(3, DBcount("SELECT itemid FROM items WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(1, DBcount("SELECT applicationid FROM applications WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(1, DBcount("SELECT hostgroupid FROM hosts_groups WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(0, DBcount("SELECT screenid FROM screens WHERE templateid='".$template['hostid']."'"));
	}

	public function testFormTemplate_FullCloneTemplate() {
		$cloned_template_name = 'Full cloned template';
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickLinkTextWait($this->template_clone);
		$this->zbxTestClickWait('full_clone');
		$this->zbxTestInputTypeOverwrite('template_name', $cloned_template_name);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template added');
		$this->assertEquals(1, DBcount("SELECT hostid FROM hosts WHERE host='".$cloned_template_name."'"));
		$this->assertEquals(1, DBcount("SELECT hostid FROM hosts WHERE host='$this->template_clone'"));

		$template = DBfetch(DBSelect("select hostid from hosts where host like '".$cloned_template_name."'"));
		$this->assertEquals(41, DBcount("SELECT itemid FROM items WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(10, DBcount("SELECT applicationid FROM applications WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(1, DBcount("SELECT hostgroupid FROM hosts_groups WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(1, DBcount("SELECT screenid FROM screens WHERE templateid='".$template['hostid']."'"));
	}

		public function testFormTemplate_Delete() {
		$template = DBfetch(DBSelect("select hostid from hosts where host like '".$this->template."'"));

		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickLinkTextWait($this->template);
		$this->zbxTestClickWait('delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template deleted');

		$this->assertEquals(0, DBcount("SELECT hostid FROM hosts WHERE host='$this->template'"));
		$this->assertEquals(0, DBcount("select * from hostmacro where hostid='".$template['hostid']."'"));
	}

	public function testFormTemplate_DeleteAndClearTemplate() {
		$template = DBfetch(DBSelect("select hostid from hosts where host like '".$this->template_full_delete."'"));
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickLinkTextWait($this->template_full_delete);
		$this->zbxTestClickWait('delete_and_clear');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template deleted');
		$this->assertEquals(0, DBcount("SELECT hostid FROM hosts WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(0, DBcount("SELECT itemid FROM items WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(0, DBcount("SELECT graphid FROM graphs WHERE templateid='".$template['hostid']."'"));
		$this->assertEquals(0, DBcount("SELECT triggerid FROM triggers WHERE templateid='".$template['hostid']."'"));
		$this->assertEquals(0, DBcount("SELECT hostgroupid FROM hosts_groups WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(0, DBcount("SELECT httptestid FROM httptest WHERE hostid='".$template['hostid']."'"));
	}
}
