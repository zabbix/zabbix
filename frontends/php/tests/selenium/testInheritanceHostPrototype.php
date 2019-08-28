<?php

/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup hosts
 */
class testInheritanceHostPrototype extends CLegacyWebTest {

	public static function getLayoutData() {
		return [
			[
				[
					'host_prototype' => 'Host prototype for update {#TEST}',
					'discovery' => 'Discovery rule for host prototype test'
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testInheritanceHostPrototype_CheckLayout($data) {
		$this->selectHostPrototypeForUpdate('host', $data);
		$this->zbxTestWaitForPageToLoad();

		// Get hostid and discoveryid to check href to template.
		$host_prototype = CDBHelper::getValue('SELECT hostid FROM hosts WHERE templateid IS NULL AND host='.
				zbx_dbstr($data['host_prototype'])
		);
		$discovery_id = CDBHelper::getValue('SELECT itemid FROM items WHERE templateid IS NULL AND name='.
				zbx_dbstr($data['discovery'])
		);

		// Check layout at Host tab.
		$this->zbxTestAssertElementPresentXpath('//label[text()="Parent discovery rules"]/../..//'.
				'a[contains(@href, "&hostid='.$host_prototype.'") and contains(@href, "&parent_discoveryid='.$discovery_id.'")]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="name"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="host"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//td[@class="interface-ip"]/input[@type="text"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//td[@class="interface-dns"]/input[@type="text"][@readonly]');
		$interface = CDBHelper::getValue('SELECT interfaceid'.
				' FROM interface'.
				' WHERE hostid IN ('.
					'SELECT hostid'.
					' FROM items'.
					' WHERE templateid IS NOT NULL'.
					' AND name='.zbx_dbstr($data['discovery']).
				')'
		);
		$this->zbxTestAssertElementPresentXpath('//ul[@id="interfaces_'.$interface.'_useip"]//input[@value="0"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//ul[@id="interfaces_'.$interface.'_useip"]//input[@value="1"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//td[@class="interface-port"]/input[@type="text"][@readonly]');
		$this->zbxTestAssertElementText('//tr[@id="SNMPInterfacesFooter"]', 'No SNMP interfaces found.');
		$this->zbxTestAssertElementText('//tr[@id="JMXInterfacesFooter"]', 'No JMX interfaces found.');
		$this->zbxTestAssertElementText('//tr[@id="IPMIInterfacesFooter"]', 'No IPMI interfaces found.');
		$this->zbxTestAssertElementPresentXpath('//input[@id="proxy_hostid"][@readonly]');

		// Check layout at Groups tab.
		$this->zbxTestTabSwitch('Groups');
		$this->zbxTestAssertElementPresentXpath('//div[@id="group_links_"]//ul[@class="multiselect-list disabled"]');
		$this->zbxTestAssertElementPresentXpath('//button[@class="btn-grey"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@name="group_prototypes[0][name]"][@readonly]');

		// Check layout at IPMI tab.
		$this->zbxTestTabSwitch('IPMI');
		foreach (['ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password'] as $id) {
			$this->zbxTestAssertElementPresentXpath('//input[@id="'.$id.'"][@readonly]');
		}

		//Check layout at Host Inventory tab.
		$this->zbxTestTabSwitch('Inventory');
		for ($i = 0; $i < 3; $i++) {
			$this->zbxTestAssertElementPresentXpath('//input[@id="inventory_mode_'.$i.'"][@disabled]');
		}

		//Check layout at Encryption tab.
		$this->zbxTestTabSwitch('Encryption');
		foreach (['tls_connect_0', 'tls_connect_1', 'tls_connect_2', 'tls_in_none', 'tls_in_cert', 'tls_in_psk'] as $id) {
			$this->zbxTestAssertElementPresentXpath('//input[@id="'.$id.'"][@disabled]');
		}

		$this->zbxTestAssertAttribute('//button[@id="delete"]', 'disabled');

		// Macro tab check must be after IPMI tab (this must be changed when ZBX-14609 will CLOSED).
		// Check layout at Macros tab.
		$this->zbxTestTabSwitch('Macros');
		$this->zbxTestAssertElementPresentXpath('//input[@id="show_inherited_macros_0"]');
		$this->zbxTestClickXpath('//label[@for="show_inherited_macros_1"]');
		$this->zbxTestWaitForPageToLoad();

		$macros = CDBHelper::getAll('SELECT * FROM globalmacro');
		foreach ($macros as $macro) {
			// Macro check and row selection.
			$element = $this->webDriver->findElement(WebDriverBy::xpath('//textarea[@class="textarea-flexible macro"][@readonly][text()="'.
					$macro['macro'].'"]/../..')
			);
			// Effective value.
			$this->assertEquals($macro['value'], $element->findElement(
					WebDriverBy::xpath('./td[3]/textarea[@readonly]')
			)->getAttribute('value'));

			// Template value.
			$this->assertEquals('', $element->findElement(WebDriverBy::xpath('./td[5]/div'))->getText());
			// Global value.
			$this->assertEquals('"'.$macro['value'].'"', $element->findElement(
					WebDriverBy::xpath('./td[7]/div')
			)->getText());
		}

		// Total macro count.
		$this->assertEquals(count($macros), count($this->webDriver->findElements(
				WebDriverBy::xpath('//textarea[@class="textarea-flexible macro"]')
		)));
	}

	public static function getCreateData() {
		return [
			[
				[
					'host' => 'test Inheritance host prototype',
					'group' => 'Zabbix servers',
					'templates' => [
						['name' => 'Inheritance test template', 'group' => 'Templates']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testInheritanceHostPrototype_CreateHostLinkTemplate($data) {
		$this->zbxTestLogin('hosts.php?form=create');
		$this->zbxTestInputTypeWait('host', $data['host']);
		$this->zbxTestClickButtonMultiselect('groups_');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestClickLinkTextWait($data['group']);
		$this->zbxTestTabSwitch('Templates');
		$this->zbxTestClickButtonMultiselect('add_templates_');
		$this->zbxTestLaunchOverlayDialog('Templates');

		foreach ($data['templates'] as $template) {
			$this->zbxTestDropdownSelectWait('groupid', $template['group']);
			$this->zbxTestClickLinkTextWait($template['name']);
			$this->zbxTestClickXpath('//div[@id="templateTab"]//button[text()="Add"]');
			$this->zbxTestWaitForPageToLoad();
		}

		$this->zbxTestClickWait('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host added');

		// DB check.
		foreach ($data['templates'] as $template) {
			// Linked templates on host.
			$hosts_templates = 'SELECT NULL'.
					' FROM hosts_templates'.
					' WHERE hostid IN ('.
						'SELECT hostid'.
						' FROM hosts'.
						' WHERE host='.zbx_dbstr($data['host']).
					')'.
					' AND templateid IN ('.
						'SELECT hostid'.
						' FROM hosts'.
						' WHERE host='.zbx_dbstr($template['name']).
					')';

			$this->assertEquals(1, CDBHelper::getCount($hosts_templates));

			// Host prototype on host and on template are the same.
			$prototype_on_host = $this->sqlForHostPrototypeCompare($data['host']);
			$prototype_on_template = $this->sqlForHostPrototypeCompare($template['name']);
			$this->assertEquals($prototype_on_host, $prototype_on_template);
		}
	}

	/**
	 * SQL request to get host prototype data.
	 *
	 * @param array $data	test case data from data provider
	 */
	private function sqlForHostPrototypeCompare($data) {
		$sql = 'SELECT host, status, name, disable_until, error, available, errors_from, lastaccess, ipmi_authtype,'.
				' ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, snmp_disable_until,'.
				' snmp_available, ipmi_errors_from, ipmi_error, snmp_error, jmx_disable_until, jmx_available,'.
				' jmx_errors_from, jmx_error, description, tls_connect, tls_accept, tls_issuer, tls_subject,'.
				' tls_psk_identity, tls_psk, auto_compress, flags'.
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

		return CDBHelper::getHash($sql);
	}

	public static function getSimpleUpdateData() {
		return [
			[
				[
					'host_prototype' => 'Host prototype for update {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'update' => 'host'
				]
			],
			[
				[
					'host_prototype' => 'Host prototype for update {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'update' => 'template'
				]
			]
		];
	}

	/**
	 * Test update without any modification of host prototype.
	 *
	 * @dataProvider getSimpleUpdateData
	 */
	public function testInheritanceHostPrototype_SimpleUpdate($data) {
		if ($data['update'] === 'host') {
			$sql = 'SELECT hostid FROM hosts WHERE templateid IS NOT NULL AND host='.zbx_dbstr($data['host_prototype']);
		}
		elseif ($data['update'] === 'template') {
			$sql = 'SELECT hostid FROM hosts WHERE templateid IS NULL AND host='.zbx_dbstr($data['host_prototype']);
		}

		$old_host = CDBHelper::getHash($sql);
		$this->selectHostPrototypeForUpdate($data['update'], $data);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		$this->assertEquals($old_host, CDBHelper::getHash($sql));
	}

	public static function getUpdateTemplateData() {
		return [
			[
				[
					'host' => 'Host for inheritance host prototype tests',
					'template' => 'Inheritance test template with host prototype',
					'host_prototype' => 'Host prototype for update {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'update_name' => 'New host prototype name {#TEST}',
					'visible_name' => 'New visible name',
					'create_enabled' => false,
					'groups' => [
						'Templates'
					],
					'macro' => '{#MACRO_NEW}',
					'templates' => [
						['name' => 'Inheritance test template', 'group' => 'Templates']
					],
					'host_inventory' => 'Automatic'
				]
			]
		];
	}

	/**
	 * Update host prototype on template and check it on linked host.
	 *
	 * @dataProvider getUpdateTemplateData
	 */
	public function testInheritanceHostPrototype_Update($data) {
		$this->selectHostPrototypeForUpdate('template', $data);

		// Host tab.
		if (array_key_exists('update_name', $data)) {
			$this->zbxTestInputTypeOverwrite('host', $data['update_name']);
		}
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['visible_name']);
		}

		if (array_key_exists('create_enabled', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['create_enabled']);
		}

		// Groups tab.
		$this->zbxTestTabSwitch('Groups');
		if (array_key_exists('groups', $data)) {
			foreach ($data['groups'] as $group) {
				$this->zbxTestClickButtonMultiselect('group_links_');
				$this->zbxTestLaunchOverlayDialog('Host groups');
				$this->zbxTestClickXpath('//div[@id="overlay_dialogue"]//a[text()="'.$group.'"]');
			}
		}

		if (array_key_exists('macro', $data)) {
			$this->zbxTestInputTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['macro']);
		}

		// Templates tab.
		$this->zbxTestTabSwitch('Templates');
		if (array_key_exists('templates', $data)) {
			foreach ($data['templates'] as $template) {
				$this->zbxTestClickButtonMultiselect('add_templates_');
				$this->zbxTestLaunchOverlayDialog('Templates');
				$this->zbxTestDropdownSelectWait('groupid', $template['group']);
				$this->zbxTestClickLinkTextWait($template['name']);
				$this->zbxTestClickXpath('//div[@id="templateTab"]//button[text()="Add"]');
				$this->zbxTestWaitForPageToLoad();
			}
		}

		// Host inventory tab.
		$this->zbxTestTabSwitch('Inventory');
		if (array_key_exists('host_inventory', $data)) {
			$this->zbxTestClickXpathWait('//label[text()="'.$data['host_inventory'].'"]');
		}

		$this->zbxTestClick('update');
		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestCheckHeader('Host prototypes');

		$prototype_on_template = $this->sqlForHostPrototypeCompare($data['template']);
		$prototype_on_host = $this->sqlForHostPrototypeCompare($data['host']);

		$this->assertEquals($prototype_on_template, $prototype_on_host);
	}

		public static function getCloneData() {
		return [
			[
				[
					'host' => 'Host for inheritance host prototype tests',
					'host_prototype' => 'Host prototype for Clone {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'cloned_name' => 'Cloned host prototype without macro',
					'error' => 'Cannot add host prototype',
					'error_detail' => 'Invalid parameter "/1/host": must contain at least one low-level discovery macro.'
				]
			],
			[
				[
					'host' => 'Host for inheritance host prototype tests',
					'host_prototype' => 'Host prototype for Clone {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'cloned_name' => ' ',
					'error' => 'Page received incorrect data',
					'error_detail' => 'Incorrect value for field "Host name": cannot be empty.'
				]
			],
			[
				[
					'host' => 'Host for inheritance host prototype tests',
					'host_prototype' => 'Host prototype for Clone {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'error' => 'Cannot add host prototype',
					'error_detail' => 'Host prototype with host name "Host prototype for Clone {#TEST}" already exists in discovery rule "Discovery rule for host prototype test".'
				]
			],
			[
				[
					'host' => 'Host for inheritance host prototype tests',
					'host_prototype' => 'Host prototype for Clone {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'cloned_name' => 'Cloned host prototype with minimum changed fields {#TEST}',
				]
			],
			[
				[
					'host' => 'Host for inheritance host prototype tests',
					'host_prototype' => 'Host prototype for Clone {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'cloned_name' => 'Cloned host prototype {#TEST}',
					'cloned_visible_name' => 'Visible name of Cloned host prototype {#TEST}',
					'create_enabled' => true,
					'hostgroup' => 'Hypervisors',
					'group_prototype' => 'Clone group prototype {#CLONE_GROUP_PROTO}',
					'template' => 'Template OS Mac OS X',
					'inventory' => 'Manual',
					'check_form' => true
				]
			]
		];
	}

	/**
	 * Clone templated host prototype on host.
	 *
	 * @dataProvider getCloneData
	 */
	public function testInheritanceHostPrototype_Clone($data) {
		$this->selectHostPrototypeForUpdate('host', $data);
		$this->zbxTestClickWait('clone');

		if (array_key_exists('cloned_name', $data)) {
			$this->zbxTestInputTypeOverwrite('host', $data['cloned_name']);
		}
		if (array_key_exists('cloned_visible_name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['cloned_visible_name']);
		}
		if (array_key_exists('create_enabled', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['create_enabled']);
		}

		// Change groups.
		if (array_key_exists('hostgroup', $data) || array_key_exists('group_prototype', $data)) {
			$this->zbxTestTabSwitch('Groups');
			if (array_key_exists('hostgroup', $data)) {
				$this->zbxTestClickXpathWait('//span[@class="subfilter-disable-btn"]');
				$this->zbxTestMultiselectClear('group_links_');
				$this->zbxTestClickButtonMultiselect('group_links_');
				$this->zbxTestLaunchOverlayDialog('Host groups');
				$this->zbxTestClickLinkTextWait($data['hostgroup']);
			}
			if (array_key_exists('group_prototype', $data)) {
				$this->zbxTestInputClearAndTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['group_prototype']);
			}
		}

		// Change template.
		if (array_key_exists('template', $data)) {
			$this->zbxTestTabSwitch('Templates');
			$this->zbxTestClickButtonMultiselect('add_templates_');
			$this->zbxTestLaunchOverlayDialog('Templates');
			$this->zbxTestDropdownSelectWait('groupid', 'Templates');
			$this->zbxTestClickLinkTextWait($data['template']);
			$this->zbxTestClickXpathWait('//div[@id="templateTab"]//button[text()="Add"]');
		}

		// Change inventory mode.
		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Inventory');
			$this->zbxTestClickXpathWait('//label[text()="'.$data['inventory'].'"]');
		}

		$this->zbxTestClick('add');

		if (array_key_exists('error', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
			$this->zbxTestTextPresent($data['error_detail']);
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype added');

			$this->zbxTestTextPresent($data['host_prototype']);
			if (array_key_exists('cloned_visible_name', $data)) {
				$this->zbxTestTextPresent($data['cloned_visible_name']);
			}
			else {
				$this->zbxTestTextPresent($data['cloned_name']);
			}

			$this->assertEquals(2, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host = '.zbx_dbstr($data['host_prototype'])));
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host = '.zbx_dbstr($data['cloned_name'])));

			if (array_key_exists('check_form', $data)) {
				$this->zbxTestClickLinkTextWait($data['cloned_visible_name']);
				$this->zbxTestAssertElementValue('host', $data['cloned_name']);
				$this->zbxTestAssertElementValue('name', $data['cloned_visible_name']);
				$this->zbxTestCheckboxSelected('status');
				$this->zbxTestTabSwitch('Groups');
				$this->zbxTestMultiselectAssertSelected('group_links_', $data['hostgroup']);
				$this->zbxTestAssertAttribute('//*[@name="group_prototypes[0][name]"]', 'value' , $data['group_prototype']);
				$this->zbxTestTabSwitch('Templates');
				$this->zbxTestAssertElementText('//div[@id="templateTab"]//a', $data['template']);
				$this->zbxTestTabSwitch('Inventory');
				$this->zbxTestAssertAttribute('//label[text()="'.$data['inventory'].'"]/../input', 'checked');
			}
		}
	}

	public static function getDeleteData() {
		return [
			[
				[
					'host_prototype' => 'Host prototype for delete {#TEST}',
					'discovery' => 'Discovery rule for host prototype test',
					'error' => 'Cannot delete host prototypes'
				]
			],
			[
				[
					'host_prototype' => 'Host prototype for delete {#TEST}',
					'discovery' => 'Discovery rule for host prototype test'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testInheritanceHostPrototype_Delete($data) {
		$discovery_id = CDBHelper::getValue('SELECT itemid FROM items WHERE templateid IS '.
				(array_key_exists('error', $data) ? ' NOT' : '').' NULL AND name='.zbx_dbstr($data['discovery'])
		);

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.$discovery_id);
		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		if (array_key_exists('error', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete host prototypes');
			$sql = 'SELECT hostid FROM hosts WHERE templateid IS NOT NULL AND host='.zbx_dbstr($data['host_prototype']);
			$this->assertEquals(1, CDBHelper::getCount($sql));
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototypes deleted');
			$sql = 'SELECT hostid FROM hosts WHERE templateid IS NULL AND host='.zbx_dbstr($data['host_prototype']);
			$this->assertEquals(0, CDBHelper::getCount($sql));
		}
	}

	/**
	 * Open specified host prototype form for update.
	 *
	 * @param array $data	test case data from data provider
	 */
	private function selectHostPrototypeForUpdate($action, $data) {
		if ($action === 'host') {
			$host_prototype = CDBHelper::getValue('SELECT hostid FROM hosts WHERE templateid IS NOT NULL AND host='.
					zbx_dbstr($data['host_prototype'])
			);
			$discovery_id = CDBHelper::getValue('SELECT itemid FROM items WHERE templateid IS NOT NULL AND name='.
					zbx_dbstr($data['discovery'])
			);
		}
		elseif ($action === 'template') {
			$host_prototype = CDBHelper::getValue('SELECT hostid FROM hosts WHERE templateid IS NULL AND host='.
					zbx_dbstr($data['host_prototype'])
			);
			$discovery_id = CDBHelper::getValue('SELECT itemid FROM items WHERE templateid IS NULL AND name='.
					zbx_dbstr($data['discovery'])
			);
		}

		$this->zbxTestLogin('host_prototypes.php?form=update&parent_discoveryid='.$discovery_id.'&hostid='.
				$host_prototype
		);
	}
}
