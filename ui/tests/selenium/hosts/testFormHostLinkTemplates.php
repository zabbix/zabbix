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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

/**
 * @backup hosts
 *
 * @onBefore prepareHostData
 */
class testFormHostLinkTemplates extends CLegacyWebTest {
	const HOST_VISIBLE_NAME = 'Visible host for template linkage';

	public static function prepareHostData() {
		CDataHelper::call('host.create', [
			[
				'host' => 'Template linkage test host',
				'name' => self::HOST_VISIBLE_NAME,
				'groups' => ['groupid' => 4] // Zabbix servers.
			]
		]);
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
		$this->zbxTestClickLinkTextWait(self::HOST_VISIBLE_NAME);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();
		$dialog->fill(['Templates' => 'Linux by Zabbix agent active']);

		$this->zbxTestTextPresent('Linux by Zabbix agent active');
		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent(self::HOST_VISIBLE_NAME);
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
		$this->zbxTestClickLinkTextWait(self::HOST_VISIBLE_NAME);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();

		// Clicks button named "Unlink" next to a template by name.
		$this->assertTrue($dialog->query('link', $template)->exists());
		$dialog->query('id:linked-templates')->asTable()->one()->findRow('Name', $template)->getColumn('Actions')
				->query('button:Unlink')->one()->click();
		$this->assertFalse($dialog->query('link', $template)->exists());

		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		// this should be a separate test
		// should check that items, triggers and graphs are not linked to the template anymore
		$this->zbxTestClickXpathWait("//a[contains(@href,'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D=".$hostid."')]");
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent($template.':');
		// using "host navigation bar" at the top of entity list
		$this->zbxTestHrefClickWait('zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('graphs.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
	}

	public function testFormHostLinkTemplates_TemplateLinkUpdate() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait(self::HOST_VISIBLE_NAME);

		$form = $this->query('name:host-form')->asForm()->waitUntilReady()->one();
		$form->fill(['Templates' => 'Linux by Zabbix agent active']);

		$this->zbxTestTextPresent('Linux by Zabbix agent active');
		$form->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent(self::HOST_VISIBLE_NAME);
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
		$this->zbxTestClickLinkTextWait(self::HOST_VISIBLE_NAME);

		$dialog = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();

		// Clicks button named "Unlink and clear" next to a template by name.
		$this->assertTrue($dialog->query('link', $template)->exists());
		$dialog->query('id:linked-templates')->asTable()->one()->findRow('Name', $template)->getColumn('Actions')
				->query('button:Unlink and clear')->one()->click();
		$this->assertFalse($dialog->query('link', $template)->exists());

		$dialog->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		$this->zbxTestClickXpathWait("//a[contains(@href,'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D=".$hostid."')]");
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent($template.':');

		$this->zbxTestHrefClickWait('zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('graphs.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
	}
}
