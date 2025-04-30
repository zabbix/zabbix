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
require_once __DIR__.'/../behaviors/CMacrosBehavior.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup hosts
 */
class testFormHostPrototype extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior and MacrosBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMacrosBehavior::class,
			CMessageBehavior::class
		];
	}

	/**
	 * Discovery rule id used in test.
	 */
	const DISCOVERY_RULE_ID = 90001;
	const HOST_ID = 90001;
	const HOST_PROTOTYPE_ID = 90012;

	public function testFormHostPrototype_CheckLayout() {
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');
		$visible_name = 'Host prototype visible name';
		$name = 'Host prototype {#33}';

		$this->zbxTestClickLinkTextWait($visible_name);
		$this->zbxTestWaitForPageToLoad();
		// Check layout at Host tab.
		$this->zbxTestAssertElementValue('host', $name);
		$this->zbxTestAssertElementValue('name', $visible_name);
		$this->zbxTestAssertElementPresentXpath('//div[contains(@class,"interface-cell-ip")]/input[@readonly]');
		$this->zbxTestAssertElementPresentXpath('//div[contains(@class,"interface-cell-dns")]/input[@readonly]');
		$this->zbxTestAssertElementPresentXpath('//label[@for="interfaces_50024_useip_1" and text()="IP"]/../input[@readonly]');
		$this->zbxTestAssertElementPresentXpath('//label[@for="interfaces_50024_useip_0" and text()="DNS"]/../input[@readonly]');
		$this->zbxTestAssertElementPresentXpath('//div[contains(@class,"interface-cell-port")]/input[@type="text"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//div[contains(@class,"interface-cell-default")]/input[@readonly]');

		foreach (['SNMP', 'JMX', 'IPMI'] as $interface) {
			$this->zbxTestAssertElementNotPresentXpath('//div[contains(@class,"interface-cell-type") and contains(text(),"'.$interface.'")]');
		}

		// Check layout at IPMI tab.
		$this->zbxTestTabSwitch('IPMI');
		$this->zbxTestAssertElementPresentXpath('//z-select[@id="ipmi_authtype"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//z-select[@id="ipmi_privilege"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="ipmi_username"][@readonly]');
		$this->zbxTestAssertElementPresentXpath('//input[@id="ipmi_password"][@readonly]');

		// Check layout at Macros tab.
		$this->zbxTestTabSwitch('Macros');
		$this->zbxTestAssertElementPresentXpath('//input[@id="show_inherited_macros_0"]');
		// Compare host prototype's macros from DB and frontend.
		$expected_macros = CDBHelper::getAll(
			'SELECT macro,value,description,type FROM hostmacro WHERE hostid='.self::HOST_PROTOTYPE_ID.' ORDER BY macro'
		);

		// Get host prototype macros from macros tab.
		$actual_macros = $this->getMacros();
		$macros_count = count($actual_macros);
		$macro_types = CDBHelper::getAll('SELECT macro, type FROM hostmacro');

		// Add Type from DB to array with actual macros
		$types = [];
		foreach ($macro_types as $macro_type) {
			$types[$macro_type['macro']] = $macro_type['type'];
		}

		for ($i = 0; $i < $macros_count; $i++) {
			$actual_macros[$i]['type'] = $types[$actual_macros[$i]['macro']];
		}

		$this->assertEquals($expected_macros, $actual_macros);

		// Check global macros.
		$this->zbxTestClickXpath('//label[@for="show_inherited_macros_1"]');
		$this->zbxTestWaitForPageToLoad();

		// Create two macros arrays: from DB and from Frontend form.
		$macros = [
			'database' => array_merge(
					CDBHelper::getAll('SELECT macro, value, description, type FROM globalmacro'),
					$expected_macros
				),
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

		// Check layout at Encryption tab.
		$this->zbxTestTabSwitch('Encryption');
		foreach (['tls_connect_0', 'tls_connect_1', 'tls_connect_2', 'tls_in_none', 'tls_in_cert', 'tls_in_psk'] as $id) {
			$this->zbxTestAssertElementPresentXpath('//input[@id="'.$id.'"][@readonly]');
		}
	}

	public static function getCreateValidationData() {
		return [
			// Create host prototype with empty name.
			[
				[
					'error' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Host name": cannot be empty.',
					'check_db' => false
				]
			],
			// Create host prototype with space in name field.
			[
				[
					'name' => ' ',
					'error' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Host name": cannot be empty.'
				]
			],
			// Create host prototype with invalid name.
			[
				[
					'name' => 'Host prototype {#3}',
					'hostgroup' => 'Discovered hosts',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Host prototype with host name "Host prototype {#3}" already exists in discovery rule "Discovery rule 1".',
					'check_db' => false
				]
			],
			[
				[
					'name' => 'Host prototype with existen visible {#NAME}',
					'visible_name' => 'Host prototype visible name',
					'hostgroup' => 'Discovered hosts',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Host prototype with visible name "Host prototype visible name" already exists in discovery rule "Discovery rule 1".',
					'check_db' => false
				]
			],
			[
				[
					'name' => 'Кириллица Прототип хоста {#FSNAME}',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			[
				[
					'name' => 'Host prototype without macro in name',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Invalid parameter "/1/host": must contain at least one low-level discovery macro.'
				]
			],
			[
				[
					'name' => 'Host prototype with / in name',
					'hostgroup' => 'Linux servers',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			// Create host prototype with invalid group.
			[
				[
					'name' => 'Host prototype {#GROUP_EMPTY}',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Invalid parameter "/1/groupLinks": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Host prototype without macro in group prototype',
					'hostgroup' => 'Linux servers',
					'group_prototypes' => [
						'Group prototype'
					],
					'error' => 'Cannot add host prototype',
					'error_message' => 'Invalid parameter "/1/host": must contain at least one low-level discovery macro.'
				]
			],
			[
				[
					'name' => '{#HOST} prototype with duplicated Group prototypes',
					'hostgroup' => 'Linux servers',
					'group_prototypes' => [
						'Group prototype {#MACRO}',
						'Group prototype {#MACRO}'
					],
					'error' => 'Cannot add host prototype',
					'error_message' => 'Invalid parameter "/1/groupPrototypes/2": value (name)=(Group prototype {#MACRO}) already exists.'
				]
			]
		];
	}

	/**
	 * Test validation of host prototype creation.
	 *
	 * @dataProvider getCreateValidationData
	 */
	public function testFormHostPrototype_CreateValidation($data) {
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host&form=create');
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('host', $data['name']);
		}

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputType('name', $data['visible_name']);
		}

		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestClickButtonMultiselect('group_links_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkText($data['hostgroup']);
		}

		if (array_key_exists('group_prototypes', $data)) {
			foreach ($data['group_prototypes'] as $i => $group) {
				$this->zbxTestInputTypeByXpath('//*[@name="group_prototypes['.$i.'][name]"]', $group);
				$this->zbxTestClick('group_prototype_add');
			}
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		$this->zbxTestTextPresentInMessageDetails($data['error_message']);

		if (!array_key_exists('check_db', $data) || $data['check_db'] === true) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hosts WHERE flags=2 AND name='.zbx_dbstr($data['name'])));
		}
	}

	public static function getValidationData() {
		return [
			[
				[
					'name' => '',
					'error' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Host name": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Host prototype {#3}',
					'hostgroup' => 'Discovered hosts',
					'error_message' => 'Host prototype with host name "Host prototype {#3}" already exists in discovery rule "Discovery rule 1".'
				]
			],
			[
				[
					'name' => 'Host prototype with existen visible {#NAME}',
					'visible_name' => 'Host prototype visible name',
					'error_message' => 'Host prototype with visible name "Host prototype visible name" already exists in discovery rule "Discovery rule 1".'
				]
			],
			[
				[
					'name' => 'Кириллица Прототип хоста {#FSNAME}',
					'error_message' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			[
				[
					'name' => 'Host prototype without macro in name',
					'error_message' => 'Invalid parameter "/1/host": must contain at least one low-level discovery macro.'
				]
			],
			[
				[
					'name' => 'Host prototype with / in name',
					'error_message' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			[
				[
					'name' => 'Host prototype {#GROUP_EMPTY}',
					'clear_groups' => true,
					'error_message' => 'Invalid parameter "/1/groupLinks": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Host prototype without macro in group prototype',
					'clear_groups' => true,
					'group_prototypes' => [
						'Group prototype'
					],
					'error_message' => 'Invalid parameter "/1/host": must contain at least one low-level discovery macro.'
				]
			],
			[
				[
					'name' => '{#HOST} prototype with duplicated Group prototypes',
					'group_prototypes' => [
						'Group prototype {#MACRO}',
						'Group prototype {#MACRO}'
					],
					'error_message' => 'Invalid parameter "/1/groupPrototypes/2": value (name)=(Group prototype {#MACRO}) already exists.'
				]
			]
		];
	}

	/**
	 * Test form validation.
	 */
	public function executeValidation($data, $action) {
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');

		switch ($action) {
			case 'update':
				$update_prototype = 'Host prototype {#2}';
				$this->zbxTestClickLinkTextWait($update_prototype);
				break;

			case 'clone':
				$clone_prototype = 'Host prototype {#1}';
				$this->zbxTestClickLinkTextWait($clone_prototype);
				$this->zbxTestClickWait('clone');
				break;
		}

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputClearAndTypeByXpath('//input[@id="host"]', $data['name']);
		}

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputClearAndTypeByXpath('//input[@id="name"]', $data['visible_name']);
		}

		if (array_key_exists('clear_groups', $data)) {
			$this->zbxTestMultiselectClear('group_links_');
		}

		if (array_key_exists('group_prototypes', $data)) {
			foreach ($data['group_prototypes'] as $i => $group) {
				$this->zbxTestInputClearAndTypeByXpath('//*[@name="group_prototypes['.$i.'][name]"]', $group);
				$this->zbxTestClick('group_prototype_add');
			}
		}

		// Press action button.
		switch ($action) {
			case 'update':
				$this->zbxTestClickWait('update');
				if (!array_key_exists('error', $data)) {
					$data['error'] = 'Cannot update host prototype';
				}
				break;

			case 'clone':
				$this->zbxTestClickWait('add');
				if (!array_key_exists('error', $data)) {
					$data['error'] = 'Cannot add host prototype';
				}
				break;
		}

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		$this->zbxTestTextPresentInMessageDetails($data['error_message']);

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Test host prototype form validation when updating.
	 *
	 * @dataProvider getValidationData
	 */
	public function testFormHostPrototype_UpdateValidation($data) {
		$this->executeValidation($data, 'update');
	}

	/**
	 * Test host prototype form validation when cloning.
	 *
	 * @dataProvider getValidationData
	 */
	public function testFormHostPrototype_CloneValidation($data) {
		$this->executeValidation($data, 'clone');
	}

	public static function getCreateData() {
		return [
			[
				[
					'name' => 'Host with minimum fields {#FSNAME}',
					'hostgroup' => 'Virtual machines'
				]
			],
			[
				[
					'name' => 'Host with all fields {#FSNAME}',
					'visible_name' => 'Host with all fields visible name',
					'hostgroup' => 'Virtual machines',
					'group_prototype' => '{#FSNAME}',
					'template' => 'Template-layout-test-001',
					'inventory' => 'Automatic',
					'checkbox' => false,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$NEW_MACRO}',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					]
				]
			]
		];
	}

	/**
	 * Test creation of a host prototype with all possible fields and with default values.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormHostPrototype_Create($data) {
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host&form=create');
		$this->zbxTestInputTypeWait('host', $data['name']);

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputType('name', $data['visible_name']);
		}

		if (array_key_exists('checkbox', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['checkbox']);
		}

		$this->zbxTestClickButtonMultiselect('group_links_');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestClickLinkTextWait($data['hostgroup']);

		if (array_key_exists('group_prototype', $data)) {
			$this->zbxTestInputTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['group_prototype']);
		}

		if (array_key_exists('template', $data)) {
			$this->zbxTestClickButtonMultiselect('add_templates_');
			$this->zbxTestLaunchOverlayDialog('Templates');
			COverlayDialogElement::find()->one()->setDataContext('Templates');
			$this->zbxTestClickLinkTextWait($data['template']);
		}


		if (array_key_exists('macros', $data)) {
			$this->zbxTestTabSwitch('Macros');
			$this->page->waitUntilReady();
			$this->fillMacros($data['macros']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Inventory');
			$this->zbxTestClickXpathWait('//label[text()="'.$data['inventory'].'"]');
		}

		$this->zbxTestClick('add');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype added');

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['visible_name'].'"]');
		}
		else {
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['name'].'"]');
		}

		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['name']));
		// Check the results in form.
		$this->checkFormFields($data);

		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['name'])));
	}

	/**
	 * Test update without any modification of host prototype.
	 */
	public function testFormHostPrototype_SimpleUpdate() {
		$sql = 'SELECT name'.
				' FROM hosts'.
				' WHERE hostid IN ('.
				'SELECT hostid'.
				' FROM host_discovery'.
				' WHERE parent_itemid='.self::DISCOVERY_RULE_ID.
				')'.
				'LIMIT 2';
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');
		foreach (CDBHelper::getAll($sql) as $host) {
			$this->zbxTestClickLinkTextWait($host['name']);
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestClickWait('update');

			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$host['name'].'"]');
		}

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public static function getUpdateData() {
		return [
			[
				[
					'old_name' => 'Host prototype {#2}',
					'name' => 'New Host prototype {#2}',
					'checkbox' => true,
					'hostgroup' => 'Virtual machines',
					'group_prototype' => 'New test {#MACRO}',
					'template' => 'Windows by Zabbix agent',
					'inventory' => 'Automatic'
				]
			],
			[
				[
					'old_visible_name' => 'Host prototype visible name',
					'visible_name' => 'New prototype visible name'
				]
			]
		];
	}

	/**
	 * Test update of a host prototype with all possible fields.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormHostPrototype_Update($data) {
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');
		$this->zbxTestClickLinkTextWait(array_key_exists('old_visible_name', $data) ? $data['old_visible_name'] : $data['old_name']);

		// Change name and visible name.
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('host', $data['name']);
		}
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['visible_name']);
		}
		// Change status.
		if (array_key_exists('checkbox', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['checkbox']);
		}

		// Change Host group and Group prototype.
		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestMultiselectClear('group_links_');
			$this->zbxTestClickButtonMultiselect('group_links_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkText($data['hostgroup']);
			$this->zbxTestInputClearAndTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['group_prototype']);
		}

		// Change template.
		if (array_key_exists('template', $data)) {
			$this->zbxTestClickXpathWait('//button[contains(@onclick,"unlink")]');
			$this->zbxTestClickButtonMultiselect('add_templates_');
			$this->zbxTestLaunchOverlayDialog('Templates');
			COverlayDialogElement::find()->one()->setDataContext('Templates');
			$this->query('link', $data['template'])->waitUntilClickable()->one()->click();
		}

		// Change inventory mode.
		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Inventory');
			$this->zbxTestClickXpathWait('//label[text()="'.$data['inventory'].'"]');
		}

		$this->zbxTestClick('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestTextPresent($data['visible_name']);
		}
		if (array_key_exists('name', $data)) {
			$this->zbxTestTextPresent($data['name']);
		}

		// Check the results in form
		$this->checkFormFields($data);

		if (array_key_exists('name', $data)) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host = '.zbx_dbstr($data['name'])));
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host = '.zbx_dbstr($data['old_name'])));
		}

		if (array_key_exists('visible_name', $data)) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE name = '.zbx_dbstr($data['visible_name'])));
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hosts WHERE name = '.zbx_dbstr($data['old_visible_name'])));
		}
	}

	/**
	 * Check IPMI tab before and after changes on parent host.
	 */
	public function testFormHostPrototype_CheckIPMIFromHost() {
		$this->page->login()->open('host_prototypes.php?form=update&parent_discoveryid='.self::DISCOVERY_RULE_ID.
				'&context=host&hostid='.self::HOST_PROTOTYPE_ID);
		$this->zbxTestWaitForPageToLoad();

		// Check IPMI settings on prototype before changes on host.
		$this->zbxTestTabSwitch('IPMI');
		$this->zbxTestTextPresent(['Authentication algorithm', 'Privilege level', 'Username', 'Password']);

		$old_values = [
			'ipmi_authtype' => '-1',	// IPMI_AUTHTYPE_DEFAULT
			'ipmi_privilege' => '2',	// IPMI_PRIVILEGE_USER
			'ipmi_username' => '',
			'ipmi_password' => ''
		];

		foreach ($old_values as $field_name => $value) {
			$this->zbxTestAssertElementValue($field_name, $value);
		}

		// Go to host and change IPMI settings.
		$this->page->open('zabbix.php?action=popup&popup=host.edit&hostid='.self::HOST_ID);
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$form->selectTab('IPMI');

		$new_values = [
			'ipmi_authtype' => 'MD2',
			'ipmi_privilege' => 'Operator',
			'ipmi_username' => 'TestUsername',
			'ipmi_password' => 'TestPassword'
		];

		foreach ($new_values as $field_name => $value) {
			$tag = $this->query('id', $field_name)->one()->getTagName();
			if ($tag === 'z-select') {
				$this->query('name:'.$field_name)->asDropdown()->one()->select($value);
			}
			else {
				$this->zbxTestInputType($field_name, $value);
			}
		}
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Host updated');

		// Go back to prototype and check changes.
		$this->page->open('host_prototypes.php?form=update&parent_discoveryid='.self::DISCOVERY_RULE_ID.
				'&context=host&hostid='.self::HOST_PROTOTYPE_ID);
		$prototype_form = $this->query('id:host-prototype-form')->asForm()->one()->waitUntilVisible();
		$prototype_form->selectTab('IPMI');

		$new_values['ipmi_authtype'] = 1;	// IPMI_AUTHTYPE_MD2
		$new_values['ipmi_privilege'] = 3;	// IPMI_PRIVILEGE_OPERATOR

		foreach ($new_values as $field_name => $value) {
			$this->zbxTestAssertElementValue($field_name, $value);
		}
	}

	public static function getCheckEncryptionFromHostData() {
		return [
			[
				[
					'connection_to_host' => 'PSK',
					'connection_from_host' => ['No encryption' => false, 'PSK' => true],
					'psk' => ['identity' => 'Test_Identity', 'number' => '16777216000000000000000000000000']
				]
			],
			[
				[
					'connection_to_host' => 'Certificate',
					'connection_from_host' => ['No encryption' => false, 'PSK' => false, 'Certificate' => true],
					'issuer' => 'Test_Issuer',
					'subject' => 'Test_Subject'
				]
			],
			[
				[
					'connection_to_host' => 'Certificate',
					'connection_from_host' => ['No encryption' => false, 'PSK' => true, 'Certificate' => true],
					'psk' => ['identity' => 'Test_Identity2', 'number' => '16777216000000000000000000000000'],
					'issuer' => 'Test_Issuer_2',
					'subject' => 'Test_Subject_2'
				]
			]
		];
	}

	/**
	 * Check Encryption tab before and after changes on parent host.
	 *
	 * @dataProvider getCheckEncryptionFromHostData
	 */
	public function testFormHostPrototype_CheckEncryptionFromHost($data) {
		$this->zbxTestLogin('host_prototypes.php?form=update&parent_discoveryid='.self::DISCOVERY_RULE_ID.
				'&hostid='.self::HOST_PROTOTYPE_ID.'&context=host');
		$this->zbxTestWaitForPageToLoad();

		// Check Encryption settings on prototype before changes on host.
		$this->zbxTestTabSwitch('Encryption');
		$this->zbxTestTextPresent(['Connections to host', 'Connections from host']);

		$labels = [
			['type' => 'radio', 'value' => 'No encryption'],
			['type' => 'radio', 'value' => 'PSK'],
			['type' => 'radio', 'value' => 'Certificate'],
			['type' => 'checkbox', 'value' => 'No encryption'],
			['type' => 'checkbox', 'value' => 'PSK'],
			['type' => 'checkbox', 'value' => 'Certificate']
		];

		foreach ($labels as $label) {
			$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label['value'].'"]/../input[@type="'.$label['type'].'"][@readonly]');
		}

		// Go to host and change Encryption settings.
		$this->page->open('zabbix.php?action=popup&popup=host.edit&hostid='.self::HOST_ID);

		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$form->selectTab('Encryption');
		$form->fill(['Connections to host' => $data['connection_to_host']]);

		foreach ($data['connection_from_host'] as $label => $state) {
			$id = $this->zbxTestGetAttributeValue('//div[@class="form-field"]//label[text()="'.$label.
					'"]/..//input[@class="checkbox-radio"]', 'id');
			$this->zbxTestCheckboxSelect($id, $state);
		}

		if (array_key_exists('psk', $data)) {
			$this->zbxTestInputTypeOverwrite('tls_psk_identity', $data['psk']['identity']);
			$this->zbxTestInputTypeOverwrite('tls_psk', $data['psk']['number']);
		}
		if (array_key_exists('issuer', $data)) {
			$this->zbxTestInputTypeOverwrite('tls_issuer', $data['issuer']);
		}
		if (array_key_exists('subject', $data)) {
			$this->zbxTestInputTypeOverwrite('tls_subject', $data['subject']);
		}
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Host updated');

		// Go back to prototype and check changes.
		$this->zbxTestOpen('host_prototypes.php?form=update&parent_discoveryid='.self::DISCOVERY_RULE_ID.
				'&hostid='.self::HOST_PROTOTYPE_ID.'&context=host');
		$this->zbxTestTabSwitch('Encryption');
		$this->zbxTestWaitForPageToLoad();

		// Check correct radio is selected.
		$this->zbxTestAssertElementPresentXpath('//label[text()="'.$data['connection_to_host'].'"]/../input[@type="radio"][@checked][@readonly]');

		// Check checkboxes.
		foreach ($data['connection_from_host'] as $label => $state) {
			$id = $this->zbxTestGetAttributeValue('//ul[@class="list-check-radio"]//label[text()="'.$label.'"]/../input', 'id');
			$this->assertEquals($state, $this->zbxTestCheckboxSelected($id));
		}

		// Check input fields.
		if (array_key_exists('psk', $data)) {
			$this->assertTrue($this->query('button:Change PSK')->exists());
		}
		if (array_key_exists('issuer', $data)) {
			$this->zbxTestAssertElementValue('tls_issuer', $data['issuer']);
		}
		if (array_key_exists('subject', $data)) {
			$this->zbxTestAssertElementValue('tls_subject', $data['subject']);
		}
	}

	public static function getCloneData() {
		return [
			[
				[
					'name' => 'Clone_3 of Host prototype {#1}',
					'visible_name' => 'Clone_3 Host prototype visible name',
					'inventory' => 'Automatic',
					'checkbox' => false
				]
			],
			[
				[
					'name' => 'Clone_4 of Host prototype {#1}',
					'hostgroup' => 'Hypervisors'
				]
			],
			[
				[
					'name' => 'Clone_5 of Host prototype {#1}',
					'group_prototype' => 'Clone group prototype {#MACRO}'
				]
			]
			,
			[
				[
					'name' => 'Clone_6 of Host prototype {#1}',
					'template' => 'Alcatel Timetra TiMOS by SNMP'
				]
			],
			[
				[
					'name' => 'Clone_7 of Host prototype {#1}',
					'inventory' => 'Manual'
				]
			]
		];
	}

	/**
	 * Test clone of a host prototype with update all possible fields.
	 *
	 * @dataProvider getCloneData
	 */
	public function testFormHostPrototype_Clone($data) {
		$hostname = 'Host prototype {#1}';

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');
		$this->zbxTestClickLinkTextWait($hostname);
		$this->zbxTestClickWait('clone');

		// Change name and visible name.
		$this->zbxTestInputTypeOverwrite('host', $data['name']);
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputType('name', $data['visible_name']);
		}
		// Change status.
		if (array_key_exists('checkbox', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['checkbox']);
		}
		// Change host group.
		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestClickXpathWait('//span['.CXPathHelper::fromClass('zi-remove-smaller').']');
			$this->zbxTestMultiselectClear('group_links_');
			$this->zbxTestClickButtonMultiselect('group_links_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkTextWait($data['hostgroup']);
		}
		// Change host group prototype.
		if (array_key_exists('group_prototype', $data)) {
			$this->zbxTestInputClearAndTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['group_prototype']);
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

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype added');

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['visible_name'].'"]');
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$hostname.'"]');
		}
		else {
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['name'].'"]');
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$hostname.'"]');
		}

		// Check the results in form
		$this->checkFormFields($data);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['name'])));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($hostname)));
	}

	private function checkFormFields($data) {
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestClickLinkTextWait($data['visible_name']);
			$this->zbxTestAssertElementValue('name', $data['visible_name']);
		}
		else {
			$this->zbxTestClickLinkTextWait($data['name']);
			$this->zbxTestAssertElementValue('host', $data['name']);
		}

		if (array_key_exists('checkbox', $data)) {
			$this->assertEquals($data['checkbox'], $this->zbxTestCheckboxSelected('status'));
		}

		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestMultiselectAssertSelected('group_links_', $data['hostgroup']);
			if (array_key_exists('group_prototype', $data)) {
				$this->assertEquals($data['group_prototype'], $this->zbxTestGetValue('//input[@name="group_prototypes[0][name]"]'));
			}
		}

		if (array_key_exists('template', $data)) {
			$this->query('link', $data['template']);
		}

		if (array_key_exists('macros', $data)) {
			$this->zbxTestTabSwitch('Macros');
			$this->assertMacros($data['macros']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Inventory');
			$this->zbxTestAssertElementPresentXpath('//label[text()="'.$data['inventory'].'"]/../input[@checked]');
		}
	}

	public function testFormHostPrototype_Delete() {
		$prototype_name = 'Host prototype {#3}';

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');
		$this->zbxTestClickLinkTextWait($prototype_name);

		$this->zbxTestClickAndAcceptAlert('delete');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype deleted');

		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($prototype_name)));
	}

	public function testFormHostPrototype_CancelCreation() {
		$host = 'Host for host prototype tests';
		$discovery_rule = 'Discovery rule 1';
		$name = 'Host prototype {#FSNAME}';
		$group = 'Virtual machines';

		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin(self::HOST_LIST_PAGE);

		$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
		$form->fill(['Name' => $host]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $host)
				->getColumn('Discovery')->query('link:Discovery')->one()->click();

		$this->zbxTestClickLinkTextWait($discovery_rule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestContentControlButtonClickTextWait('Create host prototype');

		$this->zbxTestInputType('host', $name);
		$this->zbxTestClickButtonMultiselect('group_links_');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestClickLinkText($group);

		$this->zbxTestClick('cancel');

		// Check the results in frontend.
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestTextNotPresent($name);

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Cancel updating, cloning or deleting of host prototype.
	 */
	private function executeCancelAction($action) {
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID.'&context=host');

		$sql = 'SELECT name'.
				' FROM hosts'.
				' WHERE hostid IN ('.
				'SELECT hostid'.
				' FROM host_discovery'.
				' WHERE parent_itemid='.self::DISCOVERY_RULE_ID.
				')'.
				'LIMIT 1';

		foreach (CDBHelper::getAll($sql) as $host) {
			$name = $host['name'];
			$this->zbxTestClickLinkText($name);

			switch ($action) {
				case 'update':
					$name .= ' (updated)';
					$this->zbxTestInputTypeOverwrite('host', $name);
					$this->zbxTestClick('cancel');
					break;

				case 'clone':
					$name .= ' (cloned)';
					$this->zbxTestInputTypeOverwrite('host', $name);
					$this->zbxTestClickWait('clone');
					$this->zbxTestClickWait('cancel');
					break;

				case 'delete':
					$this->zbxTestClickWait('delete');
					$this->webDriver->switchTo()->alert()->dismiss();
					break;
			}

			$this->zbxTestCheckHeader('Host prototypes');
			$this->zbxTestCheckTitle('Configuration of host prototypes');

			if ($action !== 'delete') {
				$this->zbxTestTextNotPresent($name);
			}
			else {
				$this->zbxTestTextPresent($name);
			}
		}
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Cancel update of host prototype.
	 */
	public function testFormHostPrototype_CancelUpdating() {
		$this->executeCancelAction('update');
	}

	/**
	 * Cancel cloning of host prototype.
	 */
	public function testFormHostPrototype_CancelCloning() {
		$this->executeCancelAction('clone');
	}

	/**
	 * Cancel deleting of host prototype.
	 */
	public function testFormHostPrototype_CancelDelete() {
		$this->executeCancelAction('delete');
	}
}
