<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

	public function testFormHostLinkTemplates_Layout() {
		$this->zbxTestLogin('hosts.php?form=1');

		$this->zbxTestTabSwitch('Inventory');

		$inventoryFields = getHostInventories();
		$inventoryFields = zbx_toHash($inventoryFields, 'db_field');
		foreach ($inventoryFields as $fieldId => $fieldName) {
			$this->zbxTestTextPresent($fieldName['title']);
			$this->zbxTestAssertElementPresentId('host_inventory_'.$fieldId.'');
		}
	}

	public function testFormHostLinkTemplates_TemplateLink() {
		$this->zbxTestLogin('hosts.php');
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait($this->host_for_template);

		$this->zbxTestTabSwitch('Templates');
		$this->zbxTestClickButtonMultiselect('add_templates_');
		$this->zbxTestLaunchOverlayDialog('Templates');
		COverlayDialogElement::find()->one()->setDataContext('Templates');
		$this->zbxTestClickLinkTextWait('Template OS Linux by Zabbix agent');

		$this->zbxTestTextPresent('Template OS Linux by Zabbix agent');
		$this->zbxTestClick('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent($this->host_for_template);
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateLink
	 */
	public function testFormHostLinkTemplates_TemplateUnlink() {
		// Unlink a template from a host from host properties page

		$template = 'Template OS Linux by Zabbix agent';
		$host = 'Template linkage test host';

		$sql = 'select hostid from hosts where host='.zbx_dbstr($host).' and status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$sql2 = "select hostid from hosts where host='".$template."';";
		$this->assertEquals(1, CDBHelper::getCount($sql2));
		$row2 = DBfetch(DBselect($sql2));
		$hostid2 = $row2['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait($this->host_for_template);
		$this->zbxTestTabSwitch('Templates');
		$this->zbxTestTextPresent($template);

		// clicks button named "Unlink" next to a template by name
		$this->zbxTestClickXpathWait("//button[contains(@onclick, 'unlink[".$hostid2."]') and text()='Unlink']");

		$this->zbxTestTextNotPresent($template);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		// this should be a separate test
		// should check that items, triggers, graphs and applications are not linked to the template anymore
		$this->zbxTestDoubleClickXpath("//a[contains(@href,'items.php?filter_set=1&filter_hostids%5B0%5D=".$hostid."')]", 'filter_application');
		$this->zbxTestTextNotPresent($template.':');
		// using "host navigation bar" at the top of entity list
		$this->zbxTestHrefClickWait('triggers.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('graphs.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('applications.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
	}

	public function testFormHostLinkTemplates_TemplateLinkUpdate() {
		$this->zbxTestLogin('hosts.php');
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait($this->host_for_template);

		$this->zbxTestTabSwitch('Templates');
		$this->zbxTestClickButtonMultiselect('add_templates_');
		$this->zbxTestLaunchOverlayDialog('Templates');
		COverlayDialogElement::find()->one()->setDataContext('Templates');
		$this->query('link:Template OS Linux by Zabbix agent')->waitUntilVisible()->one()->forceClick();

		$this->zbxTestTextPresent('Template OS Linux by Zabbix agent');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent($this->host_for_template);
	}

	/**
	 * @depends testFormHostLinkTemplates_TemplateLinkUpdate
	 */
	public function testFormHostLinkTemplates_TemplateUnlinkAndClear() {
		// Unlink and clear a template from a host from host properties page

		$template = 'Template OS Linux by Zabbix agent';
		$host = 'Template linkage test host';

		$sql = 'select hostid from hosts where host='.zbx_dbstr($host).' and status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$sql2 = "select hostid from hosts where host='".$template."';";
		$this->assertEquals(1, CDBHelper::getCount($sql2));
		$row2 = DBfetch(DBselect($sql2));
		$hostid2 = $row2['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->query('button:Reset')->one()->click();
		$this->zbxTestClickLinkTextWait($this->host_for_template);
		$this->zbxTestTabSwitch('Templates');
		$this->zbxTestTextPresent($template);

		// clicks button named "Unlink and clear" next to a template by name
		$this->zbxTestClickXpathWait("//button[contains(@onclick, 'unlink_and_clear[".$hostid2."]') and text()='Unlink and clear']");

		$this->zbxTestTextNotPresent($template);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		$this->zbxTestDoubleClickXpath("//a[contains(@href,'items.php?filter_set=1&filter_hostids%5B0%5D=".$hostid."')]", 'filter_application');
		$this->zbxTestTextNotPresent($template.':');

		$this->zbxTestHrefClickWait('triggers.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('graphs.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('applications.php?filter_set=1&filter_hostids%5B0%5D='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
	}
}
