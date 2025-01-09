<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

/**
 * @backup hosts
 */
class testFormHostLinkTemplates extends CLegacyWebTest {
	public $host_for_template = 'Visible host for template linkage';

	protected static $hostid;

	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function getLinkUnlinkTemplateData() {
		return [
			// #0 Attach Template to Template
			[
				'fields' => [
					'link' => 'templates.php',
					'name' => 'Form test template',
					'entity' => 'Template',
					'standalone' => 'true'
				]
			],
			// #1 Attach Template to Host from Inventory -> Hosts
			[
				'fields' => [
					'link' => 'zabbix.php?action=host.list',
					'name' => 'Visible host for template linkage',
					'entity' => 'Host'
				]
			],
			// #2 Attach Template to Host from Monitoring -> Hosts
			[
				'fields' => [
					'link' => 'zabbix.php?action=host.view',
					'name' => 'Visible host for template linkage',
					'entity' => 'Host'
				]
			],
			// #3 Attach Template to Host from Standalone view
			[
				'fields' => [
					'link' => 'zabbix.php?action=host.edit&hostid=',
					'name' => 'Visible host for template linkage',
					'entity' => 'Host',
					'standalone' => 'true'
				]
			]
		];
	}

	public function testFormHostLinkTemplates_Layout() {
		$this->page->login()->open('zabbix.php?action=host.list')->waitUntilReady();
		$this->query('button:Create host')->one()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$form->selectTab('Inventory');

		$inventoryFields = getHostInventories();
		$inventoryFields = zbx_toHash($inventoryFields, 'db_field');
		foreach ($inventoryFields as $fieldId => $fieldName) {
			$this->zbxTestTextPresent($fieldName['title']);
			$this->zbxTestAssertElementPresentId('host_inventory_'.$fieldId.'');
		}
		COverlayDialogElement::find()->one()->close();
	}

	public function testFormHostLinkTemplates_TemplateLink() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait($this->host_for_template);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();
		$dialog->fill(['Templates' => 'Linux by Zabbix agent active']);

		$this->zbxTestTextPresent('Linux by Zabbix agent active');
		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent($this->host_for_template);
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateLink
	 */
	public function testFormHostLinkTemplates_TemplateUnlink() {
		// Unlink a template from a host from host properties page

		$template = 'Linux by Zabbix agent active';
		$host = 'Template linkage test host';

		$sql = 'select hostid from hosts where host='.zbx_dbstr($host).' and status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$sql2 = "select hostid from hosts where host='".$template."';";
		$this->assertEquals(1, CDBHelper::getCount($sql2));

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait($this->host_for_template);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();

		// Clicks button named "Unlink" next to a template by name.
		$this->assertTrue($dialog->query('link', $template)->exists());
		$dialog->query('id:linked-templates')->asTable()->one()->findRow('Name', $template)->getColumn('Action')
				->query('button:Unlink')->one()->click();
		$this->assertFalse($dialog->query('link', $template)->exists());

		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		// this should be a separate test
		// should check that items, triggers and graphs are not linked to the template anymore
		$this->zbxTestClickXpathWait("//a[contains(@href,'items.php?filter_set=1&filter_hostids%5B0%5D=".$hostid."')]");
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent($template.':');
		// using "host navigation bar" at the top of entity list
		$this->zbxTestHrefClickWait('triggers.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('graphs.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
	}

	public function testFormHostLinkTemplates_TemplateLinkUpdate() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait($this->host_for_template);

		$form = $this->query('name:host-form')->asForm()->waitUntilReady()->one();
		$form->fill(['Templates' => 'Linux by Zabbix agent active']);

		$this->zbxTestTextPresent('Linux by Zabbix agent active');
		$form->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent($this->host_for_template);
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateLinkUpdate
	 */
	public function testFormHostLinkTemplates_TemplateUnlinkAndClear() {
		// Unlink and clear a template from a host from host properties page

		$template = 'Linux by Zabbix agent active';
		$host = 'Template linkage test host';

		$sql = 'select hostid from hosts where host='.zbx_dbstr($host).' and status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$sql2 = "select hostid from hosts where host='".$template."';";
		$this->assertEquals(1, CDBHelper::getCount($sql2));

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait($this->host_for_template);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();

		// Clicks button named "Unlink and clear" next to a template by name.
		$this->assertTrue($dialog->query('link', $template)->exists());
		$dialog->query('id:linked-templates')->asTable()->one()->findRow('Name', $template)->getColumn('Action')
				->query('button:Unlink and clear')->one()->click();
		$this->assertFalse($dialog->query('link', $template)->exists());

		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		$this->zbxTestClickXpathWait("//a[contains(@href,'items.php?filter_set=1&filter_hostids%5B0%5D=".$hostid."')]");
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent($template.':');

		$this->zbxTestHrefClickWait('triggers.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('graphs.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
	}

	/**
	 * @dataProvider getLinkUnlinkTemplateData
	 */
	public function testFormHostLinkTemplates_HostTemplateRelinkage($data) {
		$template = 'Linux by Zabbix agent active';

		// Login, find host/template and open form.
		if (CTestArrayHelper::get($data, 'standalone') && $data['entity'] === 'Host') {
			$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr($this->host_for_template));
			$this->page->login()->open($data['link'].$hostid)->waitUntilReady();
			$form = $this->query('id:host-form')->asForm()->waitUntilVisible()->one();
		}
		elseif (CTestArrayHelper::get($data, 'standalone') && $data['entity'] === 'Template') {
			$this->page->login()->open($data['link'])->waitUntilReady();
			$this->query('button:Reset')->one()->click();
			$templateFilter = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
			$templateFilter->fill(['Name' => $data['name']]);
			$this->query('button:Apply')->one()->click();
			$this->query('link', $data['name'])->waitUntilVisible()->one()->click();
			$form = $this->query('id:templates-form')->asForm()->waitUntilVisible()->one();
		}
		elseif ($data['link'] === 'zabbix.php?action=host.view') {
			$this->page->login()->open($data['link'])->waitUntilReady();
			$this->query('link', $data['name'])->waitUntilVisible()->asPopupButton()->one()->select('Configuration');
			$form = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();
		}
		else {
			$this->page->login()->open($data['link'])->waitUntilReady();
			$this->query('link', $data['name'])->waitUntilVisible()->one()->click();
			$form = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();
		}

		// Check if template is linked from previous test runs and removes it.
		if ($form->query('id:linked-templates')->exists()) {
			$form->query('id:linked-templates')->asTable()->one()->findRow('Name', $template)->getColumn('Action')
				->query('button:Unlink')->one()->click();
		}

		// Link template and save form.
		$form->getField('Templates')->asMultiselect()->fill($template);
		$this->assertEquals($template, $form->query('class:subfilter-enabled')->one()->getText());
		$form->submit();
		$this->assertMessage(TEST_GOOD, $data['entity'].' updated');

		// Open host configuration again, remove template link.
		if (CTestArrayHelper::get($data, 'standalone') && $data['entity'] === 'Host') {
			$this->page->open($data['link'].$hostid)->waitUntilReady();
		}
		elseif ($data['link'] === 'zabbix.php?action=host.view') {
			$this->query('link', $data['name'])->waitUntilVisible()->asPopupButton()->one()->select('Configuration');
		}
		else {
			$this->query('link', $data['name'])->waitUntilVisible()->one()->click();
		}
		$form->query('id:linked-templates')->asTable()->one()->findRow('Name', $template)->getColumn('Action')
				->query('button:Unlink')->one()->click();
		$this->assertEquals('', $form->query('id:add_templates__ms')->one()->getText());

		// Relink the template, save the form and assert that template is successfully linked.
		$form->invalidate();
		$form->getField('Templates')->asMultiselect()->fill($template);
		$this->assertEquals($template, $form->query('class:subfilter-enabled')->one()->getText());
		$form->submit();

		$this->assertMessage(TEST_GOOD, $data['entity'].' updated');

		// Check that template is linked successfully.
		if($data['link'] === 'zabbix.php?action=host.view') {
			$this->query('link', $data['name'])->waitUntilVisible()->asPopupButton()->one()->select('Configuration');
		}
		else {
			$this->query('link', $data['name'])->waitUntilVisible()->one()->click();
		}
		$this->assertTrue($form->query('link', $template)->exists());
	}
}
