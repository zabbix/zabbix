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
require_once dirname(__FILE__).'/traits/MacrosTrait.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup hosts
 */
class testInheritanceHostPrototype extends CLegacyWebTest {

	use MacrosTrait;

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
		$this->zbxTestAssertElementPresentXpath('//div[contains(@class,"interface-cell-ip")]/input[@readonly]');
		$this->zbxTestAssertElementPresentXpath('//div[contains(@class,"interface-cell-dns")]/input[@readonly]');
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
		$this->zbxTestAssertElementPresentXpath('//div[contains(@class,"interface-cell-port")]/input[@type="text"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="proxy_hostid"][@readonly]');

		// Check layout at Groups tab.
		$this->zbxTestAssertElementPresentXpath('//div[@id="group_links_"]//ul[@class="multiselect-list disabled"]');
		$this->zbxTestAssertElementPresentXpath('//button[@class="btn-grey"][@disabled]');
		$this->zbxTestAssertElementPresentXpath('//input[@name="group_prototypes[0][name]"][@readonly]');

		// Check layout at IPMI tab.
		$this->zbxTestTabSwitch('IPMI');
		foreach (['ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password'] as $id) {
			$this->zbxTestAssertElementPresentXpath('//input[@id="'.$id.'"][@readonly]');
		}

		// Check layout at Macros tab.
		$this->zbxTestTabSwitch('Macros');
		$this->zbxTestAssertElementPresentXpath('//input[@id="show_inherited_macros_0"]');
		$this->zbxTestAssertElementPresentXpath('//li[@id="macros_container"]/div[text()="No macros found."]');
		$this->zbxTestClickXpath('//label[@for="show_inherited_macros_1"]');
		$this->zbxTestWaitForPageToLoad();

		// Create two macros arrays: from DB and from Frontend form.
		$macros = [
			'database' => CDBHelper::getAll('SELECT macro, value, description, type FROM globalmacro'),
			'frontend' => []
		];

		// If the macro is expected to have type "Secret text", replace the value from db with the secret macro pattern.
		for ($i = 0; $i < count($macros['database']); $i++) {
			if ($macros['database'][$i]['type'] === '1') {
				$macros['database'][$i]['value'] = '******';
			}
		}

		// Write macros rows from Frontend to array.
		$table = $this->query('id:tbl_macros')->waitUntilVisible()->asTable()->one();
		$count = $table->getRows()->count() - 1;
		for ($i = 0; $i < $count; $i += 2) {
			$macro = [];
			$row = $table->getRow($i);
			$macro['macro'] = $row->query('xpath:./td[1]/textarea')->one()->getValue();
			$macro['value'] = $this->getValueField($macro['macro'])->getValue();
			$macro['description'] = $table->getRow($i + 1)->query('tag:textarea')->one()->getValue();
			$macro['type'] = ($this->getValueField($macro['macro'])->getInputType() === 'Secret text') ? '1' : '0';

			$macros['frontend'][] = $macro;
		}

		// Sort arrays by Macros.
		foreach ($macros as &$array) {
			usort($array, function ($a, $b) {
				return strcmp($a['macro'], $b['macro']);
			});
		}
		unset($array);

		// Compare macros from DB with macros from Frontend.
		$this->assertEquals($macros['database'], $macros['frontend']);

		// Check layout at Host Inventory tab.
		$this->zbxTestTabSwitch('Inventory');
		for ($i = 0; $i < 3; $i++) {
			$this->zbxTestAssertElementPresentXpath('//input[@id="inventory_mode_'.$i.'"][@disabled]');
		}

		// Check layout at Encryption tab.
		$this->zbxTestTabSwitch('Encryption');
		foreach (['tls_connect_0', 'tls_connect_1', 'tls_connect_2', 'tls_in_none', 'tls_in_cert', 'tls_in_psk'] as $id) {
			$this->zbxTestAssertElementPresentXpath('//input[@id="'.$id.'"][@disabled]');
		}

		$this->zbxTestAssertAttribute('//button[@id="delete"]', 'disabled');
	}

	public static function getCreateData() {
		return [
			[
				[
					'fields' => [
						'Host name' => 'test Inheritance host prototype',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent'
						]
					],
					'template' => 'Inheritance test template'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testInheritanceHostPrototype_CreateHostLinkTemplate($data) {
		$this->zbxTestLogin('zabbix.php?action=host.edit');
		$form = $this->query('id:host-form')->asForm()->one()->waitUntilVisible();
		$form->fill($data['fields']);

		$form->getFieldContainer('Interfaces')->asHostInterfaceElement(['names' => ['1' => 'default']])
				->fill($data['interfaces']);
		$form->getFieldContainer('Templates')->asMultiselect()->fill($data['template']);
		$form->submit();
		$this->page->waitUntilReady();

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host added');

		// DB check.
		$hosts_templates = 'SELECT NULL'.
				' FROM hosts_templates'.
				' WHERE hostid IN ('.
					'SELECT hostid'.
					' FROM hosts'.
					' WHERE host='.zbx_dbstr($data['fields']['Host name']).
				')'.
				' AND templateid IN ('.
					'SELECT hostid'.
					' FROM hosts'.
					' WHERE host='.zbx_dbstr($data['template']).
				')';

		$this->assertEquals(1, CDBHelper::getCount($hosts_templates));

		// Host prototype on host and on template are the same.
		$prototype_on_host = $this->sqlForHostPrototypeCompare($data['fields']['Host name']);
		$prototype_on_template = $this->sqlForHostPrototypeCompare($data['template']);
		$this->assertEquals($prototype_on_host, $prototype_on_template);
	}

	/**
	 * SQL request to get host prototype data.
	 *
	 * @param array $data	test case data from data provider
	 */
	private function sqlForHostPrototypeCompare($data) {
		$sql = 'SELECT host, status, name, lastaccess, ipmi_authtype,'.
				' ipmi_privilege, ipmi_username, ipmi_password,'.
				' description, tls_connect, tls_accept, tls_issuer, tls_subject,'.
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
					'group_macro' => '{#GROUP_MACRO}',
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

		// Groups.
		if (array_key_exists('groups', $data)) {
			foreach ($data['groups'] as $group) {
				$this->zbxTestClickButtonMultiselect('group_links_');
				$this->zbxTestLaunchOverlayDialog('Host groups');
				$this->zbxTestClickXpath('//div[contains(@class, "overlay-dialogue modal")]//a[text()="'.$group.'"]');
			}
		}

		if (array_key_exists('group_macro', $data)) {
			$this->zbxTestInputTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['group_macro']);
		}

		// Templates.
		if (array_key_exists('templates', $data)) {
			foreach ($data['templates'] as $template) {
				$this->zbxTestClickButtonMultiselect('add_templates_');
				$this->zbxTestLaunchOverlayDialog('Templates');
				COverlayDialogElement::find()->one()->setDataContext('Templates');
				$this->zbxTestClickLinkTextWait($template['name']);
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
					'cloned_name' => 'Cloned host prototype with minimum changed fields {#TEST}'
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
					'template' => 'macOS',
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
			$this->zbxTestClickButtonMultiselect('add_templates_');
			$this->zbxTestLaunchOverlayDialog('Templates');
			COverlayDialogElement::find()->one()->setDataContext('Templates');
			$this->zbxTestClickLinkTextWait($data['template']);
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
				$this->zbxTestMultiselectAssertSelected('group_links_', $data['hostgroup']);
				$this->zbxTestAssertAttribute('//*[@name="group_prototypes[0][name]"]', 'value' , $data['group_prototype']);
				$this->query('link', $data['template']);
				$this->zbxTestTabSwitch('Inventory');
				$this->zbxTestAssertAttribute('//label[text()="'.$data['inventory'].'"]/../input', 'checked');
			}
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

		$this->zbxTestLogin('host_prototypes.php?form=update&context=host&parent_discoveryid='.$discovery_id.'&hostid='.
				$host_prototype
		);
	}

	public function testInheritanceHostPrototype_AddMacroToTemplatedPrototype() {
		// Template: Inheritance test template with host prototype.
		$template_lld_id = 99083;		// Discovery rule for host prototype test.
		$template_prototype_id = 99007;	// Host prototype for update {#TEST}.

		// Host: Host for inheritance host prototype tests
		$host_lld_id = 99084;			// Discovery rule for host prototype test.
		$host_prototype_id = 99008;		// Host prototype for update {#TEST}.

		$macros = [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'macro' => '{$INHERITED_MACRO1}',
				'value' => 'Inherited value1',
				'description' => 'Inherited description1'
			],
			[
				'macro' => '{$INHERITED_MACRO2}',
				'value' => 'Inherited value2',
				'description' => 'Inherited description2'
			]
		];
		// Edit host prototype on template and add macros.
		$this->page->login()->open('host_prototypes.php?form=update&context=host&parent_discoveryid='
			.$template_lld_id.'&hostid='.$template_prototype_id);

		$form = $this->query('name:hostPrototypeForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Macros');
		$this->fillMacros($macros);
		$form->submit();

		// Open host prototype inherited from template on host and check inherited macros.
		$this->page->open('host_prototypes.php?form=update&context=host&parent_discoveryid='
			.$host_lld_id.'&hostid='.$host_prototype_id);
		$form->selectTab('Macros');
		$this->assertMacros($macros);

		// Check that inherited macros field are not editabble.
		$fields = $this->getMacros();
		foreach ($fields as $i => $field) {
			$this->assertFalse($this->query('id:macros_'.$i.'_macro')->one()->isEnabled());
			$this->assertFalse($this->query('id:macros_'.$i.'_value')->one()->isEnabled());
			$this->assertFalse($this->query('id:macros_'.$i.'_description')->one()->isEnabled());
		}

		$sql = 'SELECT macro,type,value,description FROM hostmacro WHERE hostid=%d ORDER BY hostmacroid';
		$this->assertSame(CDBHelper::getHash(vsprintf($sql, $template_prototype_id)),
			CDBHelper::getHash(vsprintf($sql, $host_prototype_id))
		);
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

		$this->zbxTestLogin('host_prototypes.php?context=host&parent_discoveryid='.$discovery_id);
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
}
