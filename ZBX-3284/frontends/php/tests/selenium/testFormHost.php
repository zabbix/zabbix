<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
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
		$this->login('hosts.php?form=1');

		$this->click('link=Host inventory');

		$inventoryFields = getHostInventories();
		$inventoryFields = zbx_toHash($inventoryFields, 'db_field');
		foreach ($inventoryFields as $fieldId => $fieldName) {
			$this->ok($fieldName['title']);
			$this->assertElementPresent('host_inventory['.$fieldId.']');
		}
	}

	public function testFormHost_Create() {
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'Zabbix servers');
		$this->button_click('form');
		$this->wait();
		$this->input_type('host', $this->host);
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host added');
		$this->ok($this->host);
	}

	public function testFormHost_CreateLongHostName() {
// 64 characters long name
		$host="1234567890123456789012345678901234567890123456789012345678901234";
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'Zabbix servers');
		$this->button_click('form');
		$this->wait();
		$this->input_type('host', $host);
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host added');
		$this->ok($host);
	}

	public function testFormHost_SimpleUpdate() {
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'Zabbix servers');
		$this->click('link='.$this->host);
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host updated');
		$this->ok($this->host);
	}

	/**
	 * Adds two macros to an existing host.
	 */
	public function testFormHost_AddMacros() {
		$this->login('hosts.php');
		$this->click("link=".$this->host);
		$this->waitForPageToLoad("30000");
		$this->tab_switch('Macros');
		$this->type("name=macros[0][macro]", '{$TEST_MACRO}');
		$this->type("name=macros[0][value]", "1");
		$this->click("//table[@id='tbl_macros']//input[@id='macro_add']");
		$this->verifyElementPresent("name=macros[1][macro]");
		$this->type("name=macros[1][macro]", '{$TEST_MACRO2}');
		$this->type("name=macros[1][value]", "2");
		$this->click("id=save");
		$this->waitForPageToLoad("30000");
		$this->ok("Host updated");

	}

	public function testFormHost_CreateHostNoGroups() {
		$host = 'Test host w/o groups';

		$sqlHosts = 'select * from hosts where host='.zbx_dbstr($host);
		$oldHashHosts = DBhash($sqlHosts);

		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->button_click('form');
		$this->wait();
		$this->input_type('host', $host);
		$this->button_click('save');
		$this->wait();

		$this->checkTitle('Configuration of hosts');
		$this->ok('ERROR: Cannot add host');
		$this->ok('No groups for host "'.$host.'".');

		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
	}

	public function testFormHost_CreateHostExistingHostName() {
		$host = 'Test host';

		$sqlHosts = 'select * from hosts where host='.zbx_dbstr($host);
		$oldHashHosts = DBhash($sqlHosts);

		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'Zabbix servers');
		$this->button_click('form');
		$this->wait();
		$this->input_type('host', $host);
		$this->button_click('save');
		$this->wait();

		$this->checkTitle('Configuration of hosts');
		$this->ok('ERROR: Cannot add host');
		$this->ok('Host with the same name "'.$host.'" already exists.');

		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
	}

	public function testFormHost_CreateHostExistingVisibleName() {
		$host = 'Test host 001 with existing visible name';
		$hostVisible = 'ЗАББИКС Сервер';

		$sqlHosts = 'select * from hosts where host='.zbx_dbstr($host);
		$oldHashHosts = DBhash($sqlHosts);

		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'Zabbix servers');
		$this->button_click('form');
		$this->wait();
		$this->input_type('host', $host);
		$this->input_type('visiblename', $hostVisible);
		$this->button_click('save');
		$this->wait();

		$this->checkTitle('Configuration of hosts');
		$this->ok('ERROR: Cannot add host');
		$this->ok('Host with the same visible name "'.$hostVisible.'" already exists.');

		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
	}

	public function testFormHost_CloneHost() {
		// Clone Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click('link='.$this->host);
		$this->wait();
		$this->button_click('clone');
		$this->wait();
		$this->input_type('host', $this->host_cloned);
		$this->input_type('visiblename', $this->host_cloned_visible);
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host added');
	}

	public function testFormHost_DeleteClonedHost() {
		$this->chooseOkOnNextConfirmation();

		// Delete Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click('link='.$this->host_cloned_visible);
		$this->wait();
		$this->button_click('delete');
		$this->wait();
		$this->getConfirmation();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host deleted');
	}

	public function testFormHost_FullCloneHost() {
		// Full clone Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click('link='.$this->host);
		$this->wait();
		$this->button_click('full_clone');
		$this->wait();
		$this->input_type('host', $this->host_fullcloned);
		$this->input_type('visiblename', $this->host_fullcloned_visible);
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host added');
	}

	public function testFormHost_DeleteFullClonedHost() {
		$this->chooseOkOnNextConfirmation();

		// Delete Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click('link='.$this->host_fullcloned_visible);
		$this->wait();
		$this->button_click('delete');
		$this->wait();
		$this->getConfirmation();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host deleted');
	}

	public function testFormHost_UpdateHostName() {
		// Update Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click('link='.$this->host);
		$this->wait();
		$this->input_type('host', $this->host_tmp);
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host updated');
	}

	public function testFormHost_Delete() {
		$this->chooseOkOnNextConfirmation();

		// save the ID of the host
		$host = DBfetch(DBSelect("select hostid from hosts where host='".$this->host_tmp."'"));
		$hostid=$host['hostid'];

		// Delete Host
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click('link='.$this->host_tmp);
		$this->wait();
		$this->button_click('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host deleted');

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

		$template = "Template OS Linux";
		$host = "Template linkage test host";

		$sql = "select hostid from hosts where host='".$host."' and status in (".HOST_STATUS_MONITORED.",".HOST_STATUS_NOT_MONITORED.")";
		$this->assertEquals(1, DBcount($sql), "Chuck Norris: No such host:$host");
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click('link=Visible host for template linkage');
		$this->wait();
		$this->tab_switch("Templates");
		$this->ok("$template");
		// clicks button named "Unlink" next to a template by name
		$this->click("xpath=//div[text()='$template']/../div[@class='dd']/input[@value='Unlink']");

		$this->wait();
		$this->nok("$template");
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host updated');

		// this should be a separate test
		// should check that items, triggers, graphs and applications are not linked to the template anymore
		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();
		$this->nok("$template");
		// using "host navigation bar" at the top of entity list
		$this->href_click("triggers.php?hostid=$hostid&sid=");
		$this->wait();
		$this->nok("$template");
		$this->href_click("graphs.php?hostid=$hostid&sid=");
		$this->wait();
		$this->nok("$template");
		$this->href_click("applications.php?hostid=$hostid&sid=");
		$this->wait();
		$this->nok("$template");
	}

	public function testFormHost_TemplateLinkUpdate() {
		$this->templateLink("Visible host for template linkage", "Template OS Linux");
	}

	public function testFormHost_TemplateUnlinkAndClear() {
		// Unlink and clear a template from a host from host properties page

		$template = "Template OS Linux";

		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click('link=Visible host for template linkage');
		$this->wait();
		$this->tab_switch("Templates");
		$this->ok("$template");

		// clicks button named "Unlink and clear" next to template named $template
		$this->click("xpath=//div[text()='$template']/../div[@class='dd']/input[@value='Unlink']/../input[@value='Unlink and clear']");

		$this->wait();
		$this->nok("$template");
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host updated');
	}

}
?>
