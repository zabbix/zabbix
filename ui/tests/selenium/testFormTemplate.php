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

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverBy;

/**
 * @backup hosts
 */
class testFormTemplate extends CLegacyWebTest {
	public $template = 'Form test template';
	public $template_edit_name = 'Template-layout-test-001';
	public $template_clone = 'Linux by Zabbix agent';
	public $template_full_delete = 'Inheritance test template';

	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Selenium Test Template',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Test Template name',
					'visible_name' => 'Selenium Test template with visible name',
					'group' => 'Linux servers',
					'new_group' => 'Selenium new group',
					'description' => 'template description',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium Test Template',
					'error_msg' => 'Cannot add template',
					'errors' => [
						'Template with host name "Selenium Test Template" already exists.'
					]

				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Existing visible name',
					'visible_name' => 'Selenium Test template with visible name',
					'error_msg' => 'Cannot add template',
					'errors' => [
						'Template with visible name "Selenium Test template with visible name" already exists.'
					]

				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => '',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Template name": cannot be empty.'
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
						'Field "groups" is mandatory.'
					]

				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormTemplate_Create($data) {
		$this->zbxTestLogin('templates.php?page=1');
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Host groups')->select('Templates');
		$filter->submit();
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
			$selected = false;

			for ($i = 0; $i < 3; $i++) {
				try {
					$this->zbxTestMultiselectNew('groups_', $data['new_group']);
					$selected = true;
					break;
				} catch (NoSuchElementException $ex) {
					// Retry. Code is not missing here.
				}
			}

			if (!$selected) {
				$this->fail('Failed to set new group "'.$data['new_group'].'" in multiselect.');
			}
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
				$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='".$data['name']."'"));
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
				$this->assertEquals(1, CDBHelper::getCount("SELECT groupid FROM hstgrp WHERE name='".$data['new_group']."'"));
			}
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestLogin('templates.php?page=1');
			$filter->invalidate();
			$filter->getField('Host groups')->select('Templates');
			$filter->submit();

			$name = CTestArrayHelper::get($data, 'visible_name', $data['name']);
			$this->filterAndOpenTemplate($name);

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

	public function testFormTemplate_UpdateTemplateName() {
		$new_template_name = 'Changed template name';

		$this->zbxTestLogin('templates.php');
		$this->filterAndOpenTemplate($this->template_edit_name);
		$this->zbxTestInputTypeOverwrite('template_name', $new_template_name);
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template updated');
		$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='".$new_template_name."'"));
		$this->assertEquals(0, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='$this->template_edit_name'"));
	}

	public function testFormTemplate_CloneTemplate() {
		$cloned_template_name = 'Cloned template';

		$this->zbxTestLogin('templates.php?page=1');
		$this->filterAndOpenTemplate($this->template_clone);
		$this->zbxTestClickWait('clone');
		$this->zbxTestInputTypeOverwrite('template_name', $cloned_template_name);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template added');
		$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='".$cloned_template_name."'"));
		$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='$this->template_clone'"));

		$template = CDBHelper::getRow("select hostid from hosts where host like '".$cloned_template_name."'");
		$this->assertEquals(0, CDBHelper::getCount("SELECT itemid FROM items WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(0, CDBHelper::getCount("SELECT dashboardid FROM dashboard WHERE templateid='".$template['hostid']."'"));
	}

	public function testFormTemplate_FullCloneTemplate() {
		$cloned_template_name = 'Full cloned template';

		$this->zbxTestLogin('templates.php?page=2');
		$this->filterAndOpenTemplate($this->template_clone);
		$this->zbxTestClickWait('full_clone');
		$this->zbxTestInputTypeOverwrite('template_name', $cloned_template_name);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template added');
		$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='".$cloned_template_name."'"));
		$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='$this->template_clone'"));

		$template = CDBHelper::getRow("select hostid from hosts where host like '".$cloned_template_name."'");
		$this->assertEquals(67, CDBHelper::getCount("SELECT itemid FROM items WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(2, CDBHelper::getCount("SELECT dashboardid FROM dashboard WHERE templateid='".$template['hostid']."'"));
	}

	public function testFormTemplate_Delete() {
		$template = CDBHelper::getRow("select hostid from hosts where host like '".$this->template."'");

		$this->zbxTestLogin('templates.php?page=1');
		$this->filterAndOpenTemplate($this->template);
		$this->zbxTestClickWait('delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template deleted');

		$this->assertEquals(0, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='$this->template'"));
		$this->assertEquals(0, CDBHelper::getCount("select * from hostmacro where hostid='".$template['hostid']."'"));
	}

	public function testFormTemplate_DeleteAndClearTemplate() {
		$template = CDBHelper::getRow("select hostid from hosts where host like '".$this->template_full_delete."'");
		$this->zbxTestLogin('templates.php');
		$this->filterAndOpenTemplate($this->template_full_delete);
		$this->zbxTestClickWait('delete_and_clear');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template deleted');
		$this->assertEquals(0, CDBHelper::getCount("SELECT hostid FROM hosts WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(0, CDBHelper::getCount("SELECT itemid FROM items WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(0, CDBHelper::getCount("SELECT graphid FROM graphs WHERE templateid='".$template['hostid']."'"));
		$this->assertEquals(0, CDBHelper::getCount("SELECT triggerid FROM triggers WHERE templateid='".$template['hostid']."'"));
		$this->assertEquals(0, CDBHelper::getCount("SELECT hostgroupid FROM hosts_groups WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(0, CDBHelper::getCount("SELECT httptestid FROM httptest WHERE hostid='".$template['hostid']."'"));
	}

	/**
	 * Function for filtering necessary template and opening its form.
	 *
	 * @param string    name    name of a template
	 */
	public function filterAndOpenTemplate($name) {
		$this->query('button:Reset')->one()->click();
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->fill(['Name' => $name]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)
				->getColumn('Name')->query('link', $name)->one()->click();
		$this->page->waitUntilReady();
	}
}
