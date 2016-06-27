<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

class testFormHost extends CWebTest {
	public $host = "Test host 001";
	public $host_tmp = "Test host 001A";
	public $host_tmp_visible = "Test host 001A (visible)";
	public $host_cloned = "Test host 001 cloned";
	public $host_cloned_visible = "Test host 001 cloned (visible)";
	public $host_fullcloned = "Test host 001 full cloned";
	public $host_fullcloned_visible = "Test host 001 full cloned (visible)";

	public function testFormHost_Layout() {
		$this->zbxTestLogin('hosts.php?form=1');

		$this->zbxTestClick('link=Host inventory');

		$inventoryFields = getHostInventories();
		$inventoryFields = zbx_toHash($inventoryFields, 'db_field');
		foreach ($inventoryFields as $fieldId => $fieldName) {
			$this->zbxTestTextPresent($fieldName['title']);
			$this->assertElementPresent('host_inventory['.$fieldId.']');
		}
	}

	public function testFormHost_Create() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestClickWait('form');
		$this->input_type('host', $this->host);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host added');
		$this->zbxTestTextPresent($this->host);
	}

	public function testFormHost_CreateLongHostName() {
// 64 characters long name
		$host="1234567890123456789012345678901234567890123456789012345678901234";
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestClickWait('form');
		$this->input_type('host', $host);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host added');
		$this->zbxTestTextPresent($host);
	}

	public function testFormHost_SimpleUpdate() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');
		$this->zbxTestTextPresent($this->host);
	}

	/**
	 * Adds two macros to an existing host.
	 */
	public function testFormHost_AddMacros() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->tab_switch('Macros');
		$this->type("name=macros[0][macro]", '{$TEST_MACRO}');
		$this->type("name=macros[0][value]", "1");
		$this->zbxTestClick("//table[@id='tbl_macros']//input[@id='macro_add']");
		$this->verifyElementPresent("name=macros[1][macro]");
		$this->type("name=macros[1][macro]", '{$TEST_MACRO2}');
		$this->type("name=macros[1][value]", "2");
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent("Host updated");
	}

	public function testFormHost_CreateHostNoGroups() {
		$host = 'Test host w/o groups';

		$sqlHosts = 'select * from hosts where host='.zbx_dbstr($host);
		$oldHashHosts = DBhash($sqlHosts);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('form');
		$this->input_type('host', $host);
		$this->zbxTestClickWait('save');

		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('ERROR: Cannot add host');
		$this->zbxTestTextPresent('No groups for host "'.$host.'".');

		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
	}

	public function testFormHost_CreateHostExistingHostName() {
		$host = 'Test host';

		$sqlHosts = 'select * from hosts where host='.zbx_dbstr($host);
		$oldHashHosts = DBhash($sqlHosts);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestClickWait('form');
		$this->input_type('host', $host);
		$this->zbxTestClickWait('save');

		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('ERROR: Cannot add host');
		$this->zbxTestTextPresent('Host with the same name "'.$host.'" already exists.');

		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
	}

	public function testFormHost_CreateHostExistingVisibleName() {
		$host = 'Test host 001 with existing visible name';
		$hostVisible = 'ЗАББИКС Сервер';

		$sqlHosts = 'select * from hosts where host='.zbx_dbstr($host);
		$oldHashHosts = DBhash($sqlHosts);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestClickWait('form');
		$this->input_type('host', $host);
		$this->input_type('visiblename', $hostVisible);
		$this->zbxTestClickWait('save');

		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('ERROR: Cannot add host');
		$this->zbxTestTextPresent('Host with the same visible name "'.$hostVisible.'" already exists.');

		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
	}

	public function testFormHost_CloneHost() {
		// Clone Host
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('clone');
		$this->input_type('host', $this->host_cloned);
		$this->input_type('visiblename', $this->host_cloned_visible);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host added');
	}

	public function testFormHost_DeleteClonedHost() {
		$this->chooseOkOnNextConfirmation();

		// Delete Host
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->host_cloned_visible);
		$this->zbxTestClickWait('delete');
		$this->getConfirmation();
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host deleted');
	}

	public function testFormHost_FullCloneHost() {
		// Full clone Host
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('full_clone');
		$this->input_type('host', $this->host_fullcloned);
		$this->input_type('visiblename', $this->host_fullcloned_visible);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host added');
	}

	public function testFormHost_DeleteFullClonedHost() {
		$this->chooseOkOnNextConfirmation();

		// Delete Host
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->host_fullcloned_visible);
		$this->zbxTestClickWait('delete');
		$this->getConfirmation();
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host deleted');
	}

	public function testFormHost_UpdateHostName() {
		// Update Host
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->host);
		$this->input_type('host', $this->host_tmp);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');
	}

	public function testFormHost_Delete() {
		$this->chooseOkOnNextConfirmation();

		// save the ID of the host
		$host = DBfetch(DBSelect("select hostid from hosts where host='".$this->host_tmp."'"));
		$hostid=$host['hostid'];

		// Delete Host
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->host_tmp);
		$this->zbxTestClick('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host deleted');

		// check if all records have been deleted
		$tables=array('hosts','items','applications','interface','hostmacro','hosts_groups','hosts_templates','maintenances_hosts','host_inventory');
		foreach ($tables as $table) {
			$count=DBcount("select * from $table where hostid=$hostid");
			$this->assertEquals(0, $count, "Records from table '$table' have not been deleted.");
		}
	}

	public function testFormHost_TemplateLink() {
		$this->templateLink("Visible host for template linkage", "Template OS Linux");
	}

	public function testFormHost_TemplateUnlink() {
		// Unlink a template from a host from host properties page

		$template = 'Template OS Linux';
		$host = 'Template linkage test host';
		$name = 'Visible host for template linkage';

		$sql = 'select hostid from hosts where host='.zbx_dbstr($host).' and status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		$this->assertEquals(1, DBcount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$name);
		$this->tab_switch('Templates');
		$this->zbxTestTextPresent($template);
		// clicks button named "Unlink" next to a template by name
		$this->zbxTestClickWait("xpath=//div[text()='$template']/../div[@class='dd']/input[@value='Unlink']");

		$this->zbxTestTextNotPresent($template);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');

		// this should be a separate test
		// should check that items, triggers, graphs and applications are not linked to the template anymore
		$this->href_click('items.php?filter_set=1&hostid='.$hostid.'&sid=');
		$this->wait();
		$this->zbxTestTextNotPresent($template.':');
		// using "host navigation bar" at the top of entity list
		$this->href_click('triggers.php?hostid='.$hostid.'&sid=');
		$this->wait();
		$this->zbxTestTextNotPresent($template.':');
		$this->href_click('graphs.php?hostid='.$hostid.'&sid=');
		$this->wait();
		$this->zbxTestTextNotPresent($template.':');
		$this->href_click('applications.php?hostid='.$hostid.'&sid=');
		$this->wait();
		$this->zbxTestTextNotPresent($template.':');
	}

	public function testFormHost_TemplateLinkUpdate() {
		$this->templateLink("Visible host for template linkage", "Template OS Linux");
	}

	public function testFormHost_TemplateUnlinkAndClear() {
		// Unlink and clear a template from a host from host properties page

		$template = 'Template OS Linux';
		$name = 'Visible host for template linkage';

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$name);
		$this->tab_switch('Templates');
		$this->zbxTestTextPresent($template);

		// clicks button named "Unlink and clear" next to template named $template
		$this->zbxTestClickWait("xpath=//div[text()='$template']/../div[@class='dd']/input[@value='Unlink']/../input[@value='Unlink and clear']");

		$this->zbxTestTextNotPresent($template);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');
	}

}
