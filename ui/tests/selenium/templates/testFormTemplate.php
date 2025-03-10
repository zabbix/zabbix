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
					'group' => 'Templates/SAN',
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
					'error_msg' => 'Cannot add template',
					'errors' => [
						'Incorrect value for field "template_name": cannot be empty.'
					]

				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Without groups',
					'remove_group' => 'Templates',
					'error_msg' => 'Cannot add template',
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
		$this->page->login()->open('zabbix.php?action=template.list&filter_rst=1')->waitUntilReady();
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Template groups')->select('Templates');
		$filter->submit();
		$this->zbxTestContentControlButtonClickTextWait('Create template');
		$this->zbxTestInputTypeWait('template_name', $data['name']);
		$this->zbxTestAssertElementValue('template_name', $data['name']);

		if (isset ($data['visible_name'])) {
			$this->zbxTestInputTypeOverwrite('visiblename', $data['visible_name']);
			$this->zbxTestAssertElementValue('visiblename', $data['visible_name']);
		}

		if (array_key_exists('group', $data)) {
			$this->zbxTestClickButtonMultiselect('template_groups_');
			$this->zbxTestLaunchOverlayDialog('Template groups');
			$this->zbxTestClickLinkTextWait($data['group']);
		}

		if (array_key_exists('new_group', $data)) {
			$selected = false;

			for ($i = 0; $i < 3; $i++) {
				try {
					$this->zbxTestMultiselectNew('template_groups_', $data['new_group']);
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
			$this->zbxTestMultiselectRemove('template_groups_', $data['remove_group']);
		}

		$this->clickModalFooterButton('Add');

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
			$this->zbxTestLogin('zabbix.php?action=template.list');

			$name = CTestArrayHelper::get($data, 'visible_name', $data['name']);
			$this->filterAndOpenTemplate($name);

			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('template_name'));
			$this->zbxTestAssertElementValue('template_name', $data['name']);

			$this->zbxTestMultiselectAssertSelected('template_groups_', 'Templates');

			if (array_key_exists('new_group', $data)) {
				$this->zbxTestMultiselectAssertSelected('template_groups_', $data['new_group']);
			}

			if (array_key_exists('group', $data)) {
				$this->zbxTestMultiselectAssertSelected('template_groups_', $data['group']);
			}

			if (isset ($data['visible_name'])) {
				$this->zbxTestAssertElementValue('visiblename', $data['visible_name']);
			}

			if (isset ($data['description'])) {
				$this->zbxTestAssertElementValue('description', $data['description']);
			}
		}

		COverlayDialogElement::find()->one()->close();
	}

	public function testFormTemplate_UpdateTemplateName() {
		$new_template_name = 'Changed template name';

		$this->zbxTestLogin('zabbix.php?action=template.list');
		$this->filterAndOpenTemplate($this->template_edit_name);
		$this->zbxTestInputTypeOverwrite('template_name', $new_template_name);
		$this->clickModalFooterButton('Update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template updated');
		$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='".$new_template_name."'"));
		$this->assertEquals(0, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='$this->template_edit_name'"));
	}

	public function testFormTemplate_CloneTemplate() {
		$cloned_template_name = 'Cloned template';

		$this->zbxTestLogin('zabbix.php?action=template.list');
		$this->filterAndOpenTemplate($this->template_clone);

		$this->clickModalFooterButton('Clone');
		COverlayDialogElement::find()->one()->waitUntilReady();
		$this->zbxTestInputTypeOverwrite('template_name', $cloned_template_name);

		$this->clickModalFooterButton('Add');
		COverlayDialogElement::find()->one()->ensureNotPresent();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template added');
		$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='".$cloned_template_name."'"));
		$this->assertEquals(1, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='$this->template_clone'"));

		$template = CDBHelper::getRow("select hostid from hosts where host like '".$cloned_template_name."'");
		$this->assertEquals(71, CDBHelper::getCount("SELECT itemid FROM items WHERE hostid='".$template['hostid']."'"));
		$this->assertEquals(3, CDBHelper::getCount("SELECT dashboardid FROM dashboard WHERE templateid='".$template['hostid']."'"));
	}

	public function testFormTemplate_Delete() {
		$template = CDBHelper::getRow("select hostid from hosts where host like '".$this->template."'");

		$this->zbxTestLogin('zabbix.php?action=template.list');
		$this->filterAndOpenTemplate($this->template);
		$this->clickModalFooterButton('Delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Template deleted');

		$this->assertEquals(0, CDBHelper::getCount("SELECT hostid FROM hosts WHERE host='$this->template'"));
		$this->assertEquals(0, CDBHelper::getCount("select * from hostmacro where hostid='".$template['hostid']."'"));
	}

	public function testFormTemplate_DeleteAndClearTemplate() {
		$template = CDBHelper::getRow("select hostid from hosts where host like '".$this->template_full_delete."'");
		$this->zbxTestLogin('zabbix.php?action=template.list');
		$this->filterAndOpenTemplate($this->template_full_delete);
		$this->clickModalFooterButton('Delete and clear');
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

	/**
	 * Clicks a button on the footer of the modal.
	 *
	 * @param string text    text of the button to be clicked
	 */
	protected function clickModalFooterButton($text) {
		COverlayDialogElement::find()->one()
				->query('xpath:./div[@class="overlay-dialogue-footer"]/button[text()='.CXPathHelper::escapeQuotes($text).']')
				->WaitUntilClickable()->one()->click();
	}
}
