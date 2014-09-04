<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		$this->zbxTestClickWait('add');
		$this->zbxTestCheckTitle('Configuration of hosts');
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
		$this->zbxTestClickWait('add');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host added');
		$this->zbxTestTextPresent($host);
	}

	public function testFormHost_SimpleUpdate() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');
		$this->zbxTestTextPresent($this->host);
	}

	/**
	 * Adds two macros to an existing host.
	 */
	public function testFormHost_AddMacros() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClick('tab_macroTab');
		$this->input_type('macros_0_macro', '{$TEST_MACRO}');
		$this->input_type('macros_0_value', '1');
		$this->zbxTestClick('macro_add');
		$this->verifyElementPresent('macros_1_macro');
		$this->input_type('macros_1_macro', '{$TEST_MACRO2}');
		$this->input_type('macros_1_value', '2');
		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Host updated');
	}

	public function testFormHost_CreateHostNoGroups() {
		$host = 'Test host without groups';

		$sqlHosts = 'select * from hosts where host='.zbx_dbstr($host);
		$oldHashHosts = DBhash($sqlHosts);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('form');
		$this->input_type('host', $host);

		$this->zbxTestClickWait('add');

		$this->zbxTestCheckTitle('Configuration of hosts');
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
		$this->zbxTestClickWait('add');

		$this->zbxTestCheckTitle('Configuration of hosts');
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
		$this->zbxTestClickWait('add');

		$this->zbxTestCheckTitle('Configuration of hosts');
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
		$this->zbxTestClickWait('add');
		$this->zbxTestCheckTitle('Configuration of hosts');
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
		$this->zbxTestCheckTitle('Configuration of hosts');
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
		$this->zbxTestClickWait('add');
		$this->zbxTestCheckTitle('Configuration of hosts');
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
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host deleted');
	}

	public function testFormHost_UpdateHostName() {
		// Update Host
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$this->host);
		$this->input_type('host', $this->host_tmp);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
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
		$this->zbxTestClickWait('delete');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host deleted');

		// check if all records have been deleted
		$tables=array('hosts','items','applications','interface','hostmacro','hosts_groups','hosts_templates','maintenances_hosts','host_inventory');
		foreach ($tables as $table) {
			$count=DBcount("select * from $table where hostid=$hostid");
			$this->assertEquals(0, $count, "Records from table '$table' have not been deleted.");
		}
	}

	public function testFormHost_TemplateLink() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link=Visible host for template linkage');

		$this->zbxTestClick('tab_templateTab');
		$this->assertElementPresent("//div[@id='add_templates_']/input");
		$this->input_type("//div[@id='add_templates_']/input", 'Template OS Linux');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClickWait('add_template');

		$this->zbxTestTextPresent('Template OS Linux');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');
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

		$sql2 = "select hostid from hosts where host='".$template."';";
		$this->assertEquals(1, DBcount($sql2));
		$row2 = DBfetch(DBselect($sql2));
		$hostid2 = $row2['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClick('tab_templateTab');
		$this->zbxTestTextPresent($template);

		// clicks button named "Unlink" next to a template by name
		$this->zbxTestClickWait('unlink_'.$hostid2);

		$this->zbxTestTextNotPresent($template);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');

		// this should be a separate test
		// should check that items, triggers, graphs and applications are not linked to the template anymore
		$this->zbxTestHrefClickWait('items.php?filter_set=1&hostid='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		// using "host navigation bar" at the top of entity list
		$this->zbxTestHrefClickWait('triggers.php?hostid='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('graphs.php?hostid='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
		$this->zbxTestHrefClickWait('applications.php?hostid='.$hostid);
		$this->zbxTestTextNotPresent($template.':');
	}

	public function testFormHost_TemplateLinkUpdate() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link=Visible host for template linkage');

		$this->zbxTestClick('tab_templateTab');
		$this->assertElementPresent("//div[@id='add_templates_']/input");
		$this->input_type("//div[@id='add_templates_']/input", 'Template OS Linux');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClickWait('add_template');

		$this->zbxTestTextPresent('Template OS Linux');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');
	}

	public function testFormHost_TemplateUnlinkAndClear() {
		// Unlink and clear a template from a host from host properties page

		$template = 'Template OS Linux';
		$name = 'Visible host for template linkage';

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClick('tab_templateTab');
		$this->zbxTestTextPresent($template);

		$template = 'Template OS Linux';
		$host = 'Template linkage test host';
		$name = 'Visible host for template linkage';

		$sql = 'select hostid from hosts where host='.zbx_dbstr($host).' and status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		$this->assertEquals(1, DBcount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$sql2 = "select hostid from hosts where host='".$template."';";
		$this->assertEquals(1, DBcount($sql2));
		$row2 = DBfetch(DBselect($sql2));
		$hostid2 = $row2['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClick('tab_templateTab');
		$this->zbxTestTextPresent($template);

		// clicks button named "Unlink" next to a template by name
		$this->zbxTestClickWait('unlink_and_clear_'.$hostid2);

		$this->zbxTestTextNotPresent($template);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');
	}
}
