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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup hosts
 */
class testInheritanceHostPrototype extends CWebTest {

	public static function getLayoutData() {
		return [
			[
				[
					'host_prototype' => 'Host prototype for update {#TEST}',
					'discovery_id' => 'Discovery rule for host prototype test'
				]
			]
		];
	}

	/**
	* @dataProvider getLayoutData
	*/
	public function testInheritanceHostPrototype_CheckLayout($data) {
		$this->selectHostPrototypeForUpdate('host', $data);

		// Get hostid and discoveryid to check href to template.
		$host_prototype = DBfetch(DBSelect('SELECT hostid FROM hosts WHERE templateid IS NULL AND host='.zbx_dbstr($data['host_prototype'])));
		$discovery_id = DBfetch(DBSelect('SELECT itemid FROM items WHERE templateid IS NULL AND name='.zbx_dbstr($data['discovery_id'])));
		$host_prototype = $host_prototype['hostid'];
		$discovery_id = $discovery_id['itemid'];

		// Check layout at Host tab.
		$this->zbxTestAssertElementPresentXpath('//a[contains(@href, \'?form=update&hostid='.$host_prototype.'&parent_discoveryid='.$discovery_id.'\')]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="name"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="host"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//td[@class="interface-ip"]/input[@type="text"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//td[@class="interface-dns"]/input[@type="text"][@readonly]');
		$interface = DBfetch(DBSelect('SELECT interfaceid'.
						' FROM interface'.
						' WHERE hostid IN ('.
							'SELECT hostid'.
							' FROM items'.
							' WHERE templateid IS NOT NULL'.
							' AND name='.zbx_dbstr($data['discovery_id']).
							')'
			));
		$interface=$interface['interfaceid'];
		$this->zbxTestAssertElementPresentXpath('//td/ul[@id="interfaces_'.$interface.'_useip"]/li/input[@value="0"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//td/ul[@id="interfaces_'.$interface.'_useip"]/li/input[@value="1"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//td[@class="interface-port"]/input[@type="text"][@readonly]');
		$this->zbxTestAssertElementText('//tr[@id="SNMPInterfacesFooter"]/td[2]','No SNMP interfaces found.');
		$this->zbxTestAssertElementText('//tr[@id="JMXInterfacesFooter"]/td[2]', 'No JMX interfaces found.');
		$this->zbxTestAssertElementText('//tr[@id="IPMIInterfacesFooter"]/td[2]', 'No IPMI interfaces found.');
		$this->zbxTestAssertElementPresentXpath('//input[@id="proxy_hostid"][@readonly]');

		// Check layout at Groups tab.
		$this->zbxTestClick('tab_groupTab');
		$this->zbxTestAssertElementPresentXpath('//div[@id="group_links_"]/div/ul[@class="multiselect-list disabled"]');
		$this->zbxTestAssertElementPresentXpath('//button[@class="btn-grey"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@name="group_prototypes[0][name]"][@disabled]');

		// Check layout at IPMI tab.
		$this->zbxTestClick('tab_ipmiTab');
		$this->zbxTestAssertElementPresentXpath('//input[@id=\'ipmi_authtype\'][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//input[@id=\'ipmi_privilege\'][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//input[@id=\'ipmi_username\'][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//input[@id=\'ipmi_password\'][@readonly]');

		//Check layout at HostInventory.
		$this->zbxTestClick('tab_inventoryTab');
		$this->zbxTestAssertElementPresentXpath('//input[@id="inventory_mode_0"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="inventory_mode_1"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="inventory_mode_2"][@disabled]');

		//Check layout at Encryption tab.
		$this->zbxTestClick('tab_encryptionTab');
		$this->zbxTestAssertElementPresentXpath('//input[@id="tls_connect_0"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="tls_connect_1"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="tls_connect_2"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="tls_in_none"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="tls_in_cert"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="tls_in_psk"][@disabled]');

		$this->zbxTestAssertAttribute('//button[@id=\'delete\']', 'disabled');

		// Macro tab check must be after IPMI tab (this must be changed when ZBX-14609 will CLOSED).
		// Check layout at Macros tab.
		$this->zbxTestClick('tab_macroTab');
		$this->zbxTestAssertElementPresentXpath('//input[@id="show_inherited_macros_0"]');
		$this->zbxTestClickXpath('//label[@for="show_inherited_macros_1"]');

		$macros = DBdata('SELECT * FROM globalmacro', false);
		foreach ($macros as $macro) {
			$macro = $macro[0];
			// Macro check and row selection.
			$element = $this->webDriver->findElement(WebDriverBy::xpath('//input[@class="macro"][@readonly][@value="'.$macro['macro'].'"]/../..'));
			// Effective value.
			$this->assertEquals($macro['value'], $element->findElement(WebDriverBy::xpath('./td[3]/input[@type="text"][@readonly]'))->getAttribute('value'));
			// Template value.
			$this->assertEquals('', $element->findElement(WebDriverBy::xpath('./td[5]/div'))->getText());
			// Global value.
			$this->assertEquals('"'.$macro['value'].'"', $element->findElement(WebDriverBy::xpath('./td[7]/div'))->getText());
		}

		// Total macro count.
		$this->assertEquals(count($macros), count($this->webDriver->findElements(WebDriverBy::xpath('//input[@class="macro"]'))));
	}

	public static function getCreateData() {
		return [
			[
				[
					'host' => 'test Inheritance host prototype',
					'templates' => [
						['template' => 'Inheritance test template']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testInheritanceHostPrototype_Create_OnHost($data) {
		$this->zbxTestLogin('hosts.php?form=create');
		$this->zbxTestInputTypeWait('host', $data['host']);
		$this->zbxTestClickButtonMultiselect('groups_');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestClickLinkTextWait('Zabbix servers');
		$this->zbxTestClick('tab_templateTab');
		$this->zbxTestClickButtonMultiselect('add_templates_');
		$this->zbxTestLaunchOverlayDialog('Templates');

		foreach ($data['templates'] as $template) {
			$templ = $template['template'];
			$this->zbxTestDropdownSelectWait('groupid', 'Templates');
			$this->zbxTestClickLinkTextWait($templ);
			$this->zbxTestClickXpath('(//button[@type=\'button\'])[8]');
		}

		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host added');

		// DB check.
		foreach ($data['templates'] as $template) {
		$templ = $template['template'];

		// Table hosts_templates check.
		$hosts_templates='SELECT NULL'.
							' FROM hosts_templates'.
							' WHERE hostid IN ('.
								'SELECT hostid'.
								' FROM hosts'.
								' WHERE host='.zbx_dbstr($data['host']).
								')'.
							' AND templateid IN ('.
								'SELECT hostid'.
								' FROM hosts'.
								' WHERE host='.zbx_dbstr($templ).
								')';

		$this->assertEquals(1, DBcount($hosts_templates));

		$host_host = ($this->sqlForHostCompare($data['host']));
		$host_templ = ($this->sqlForHostCompare($templ));

		$this->assertEquals($host_host,$host_templ);
		}
	}

	/**
	 * SQL request from table hosts to check data stay without changes
	 *
	 * @param array $data	test case data from data provider
	 */
	private function sqlForHostCompare($data) {
		$sql = 'SELECT host, status, name, disable_until, error, available,'.
					' errors_from, lastaccess, ipmi_authtype, ipmi_privilege,'.
					' ipmi_username, ipmi_password, ipmi_disable_until,'.
					' snmp_disable_until, snmp_available, ipmi_errors_from,'.
					' ipmi_error, snmp_error, jmx_disable_until, jmx_available,'.
					' jmx_errors_from, jmx_error,description, tls_connect,'.
					' tls_accept, tls_issuer, tls_subject, tls_psk_identity,'.
					' tls_psk, auto_compres s, flags'.
					' FROM hosts'.
					' WHERE flags=2 AND hostid IN ('.
						'SELECT hostid'.
						' FROM host_discovery'.
						' WHERE parent_itemid IN ('.
							'SELECT itemid'.
							' FROM items'.
							' WHERE hostid in ('.
								'SELECT hostid'.
								' FROM hosts'.
								' WHERE host='.zbx_dbstr($data).
								')'.
							')'.
						')'.
					' ORDER BY host, name';

		return DBhash($sql);
	}

	public static function getSimpleUpdateData() {
		return [
			[
				[
					'host_prototype' => 'Host prototype for update {#TEST}',
					'discovery_id' => 'Discovery rule for host prototype test',
					'update' => 'host'
				]
			],
			[
				[
					'host_prototype' => 'Host prototype for update {#TEST}',
					'discovery_id' => 'Discovery rule for host prototype test',
					'update' => 'template'
				]
			]
		];
	}

	/**
	 * @dataProvider getSimpleUpdateData
	 */
	public function testInheritanceHost_SimpleUpdate($data) {
		if ($data['update'] === 'host') {
			$sql = 'SELECT hostid FROM hosts WHERE templateid IS NOT NULL AND host='.zbx_dbstr($data['host_prototype']);
		}
		elseif ($data['update'] === 'template') {
			$sql = 'SELECT hostid FROM hosts WHERE templateid IS NULL AND host='.zbx_dbstr($data['host_prototype']);
		}
		$old_host = DBhash($sql);
		$this->selectHostPrototypeForUpdate($data['update'], $data);
		$this->zbxTestClick('update');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		$this->assertEquals($old_host, DBhash($sql));
	}

		public static function getUpdateTemplateData() {
		return [
			[
				[
					'host_prototype' => 'Host prototype for update {#TEST}',
					'discovery_id' => 'Discovery rule for host prototype test',
					'update_name' => 'New host prototype name {#TEST}',
					'visible_name' => 'New visible name',
					'status_change' => HOST_STATUS_NOT_MONITORED,
					'groups' => [
						['group_name' => 'Templates']
					],
					'macro' => '{#MACRO_NEW}',
					'templates' => [
						['template' => 'Inheritance test template', 'group' => 'Templates']
					],
					'host_inventory' => '2'
				]
			]
		];
	}
	/**
	 * @dataProvider getUpdateTemplateData
	 */
	public function testInheritanceHost_Update($data) {
		$this->selectHostPrototypeForUpdate('template', $data);

		// Host tab.
		if (array_key_exists('update_name', $data)) {
			$this->zbxTestInputTypeOverwrite('host', $data['update_name']);
		}
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['visible_name']);
		}

		if (array_key_exists('status_change', $data)) {
			if ($data['status_change'] === HOST_STATUS_MONITORED) {
				$this->zbxTestCheckboxSelect('status');
			}
			else {
				$this->zbxTestCheckboxSelect('status', false);
			}
		}

		// Groups tab.
		$this->zbxTestClick('tab_groupTab');
		if (array_key_exists('groups', $data)) {
			foreach ($data['groups'] as $i => $group_row) {
				$this->zbxTestClickButtonText('Select');
				$this->zbxTestLaunchOverlayDialog('Host groups');
				$this->zbxTestClickXpath('//div[@id=\'overlay_dialogue\']//a[text()="'.$group_row['group_name'].'"]');
			}
		}

		if (array_key_exists('macro', $data)) {
			$this->zbxTestInputTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['macro']);
		}

		// Templates tab.
		$this->zbxTestClick('tab_templateTab');
		if (array_key_exists('templates', $data)) {
			foreach ($data['templates'] as $template) {
				$templ = $template['template'];
				$this->zbxTestClickButtonMultiselect('add_templates_');
				$this->zbxTestLaunchOverlayDialog('Templates');
				$this->zbxTestDropdownSelectWait('groupid', 'Templates');
				$this->zbxTestClickLinkTextWait($templ);
			}
		}

		// Host inventory tab.
		$this->zbxTestClickWait('tab_inventoryTab');
		if (array_key_exists('host_inventory', $data)) {
			$this->zbxTestClickXpathWait('//label[@for="inventory_mode_'.$data['host_inventory'].'"]');
		}

		$this->zbxTestClick('update');
		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckFatalErrors();

		$host_host = ($this->sqlForHostCompare($data['update_name']));

		foreach ($data['templates'] as $template) {
			$templ = $template['template'];
			$host_templ = ($this->sqlForHostCompare($templ));
		}

		$this->assertEquals($host_host,$host_templ);
	}


	public static function getDeleteData() {
		return [
			[
				[
					'host_prototype' => 'Host prototype for delete {#TEST}',
					'discovery_id' => 'Discovery rule for host prototype test',
					'error' => 'Cannot delete host prototypes'
				]
			],
			[
				[
					'host_prototype' => 'Host prototype for delete {#TEST}',
					'discovery_id' => 'Discovery rule for host prototype test'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testInheritanceHost_Delete($data) {
		if (array_key_exists('error', $data)) {
			$discovery_id = DBfetch(DBSelect('SELECT itemid FROM items WHERE templateid IS NOT NULL AND name='.zbx_dbstr($data['discovery_id'])));
		}
		else {
			$discovery_id = DBfetch(DBSelect('SELECT itemid FROM items WHERE templateid IS NULL AND name='.zbx_dbstr($data['discovery_id'])));
		}
		$discovery_id = $discovery_id['itemid'];
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.$discovery_id);
		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		if (array_key_exists('error', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete host prototypes');
			$sql = 'SELECT hostid FROM hosts WHERE templateid IS NOT NULL AND host='.zbx_dbstr($data['host_prototype']);
			$this->assertEquals(1, DBcount($sql));
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototypes deleted');
			$sql = 'SELECT hostid FROM hosts WHERE templateid IS NULL AND host='.zbx_dbstr($data['host_prototype']);
			$this->assertEquals(0, DBcount($sql));
		}
	}

	/**
	 * Select specified hosts prototype for update from host prototype page.
	 *
	 * @param array $data	test case data from data provider
	 */
	private function selectHostPrototypeForUpdate($action, $data) {
		if ($action === 'host') {
			$host_prototype = DBfetch(DBSelect('SELECT hostid FROM hosts WHERE templateid IS NOT NULL AND host='.zbx_dbstr($data['host_prototype'])));
			$discovery_id = DBfetch(DBSelect('SELECT itemid FROM items WHERE templateid IS NOT NULL AND name='.zbx_dbstr($data['discovery_id'])));
		}
		elseif ($action === 'template') {
			$host_prototype = DBfetch(DBSelect('SELECT hostid FROM hosts WHERE templateid IS NULL AND host='.zbx_dbstr($data['host_prototype'])));
			$discovery_id = DBfetch(DBSelect('SELECT itemid FROM items WHERE templateid IS NULL AND name='.zbx_dbstr($data['discovery_id'])));
		}

		$host_prototype = $host_prototype['hostid'];
		$discovery_id = $discovery_id['itemid'];
		$this->zbxTestLogin('host_prototypes.php?form=update&parent_discoveryid='.$discovery_id.'&hostid='.$host_prototype);
	}
}
