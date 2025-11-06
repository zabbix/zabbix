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
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup icon_map
 */
class testFormAdministrationGeneralIconMapping extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	public function getCreateValidationData() {
		return [
			// Create icon mapping with empty name
			[
				[
					'error_inline' => [
						'Name' => 'This field cannot be empty.'
					],
					'check_db' => false
				]
			],
			// Create with spaces in name
			[
				[
					'name' => ' ',
					'error_inline' => [
						'Name' => 'This field cannot be empty.'
					]
				]
			],
			[
				[
					'name' => 'Icon mapping one',
					'mappings' => [
						['expression' => 'Create with existing name']
					],
					'error_inline' => [
						'Name' => 'This object already exists.'
					],
					'check_db' => false
				]
			],
			[
				[
					'name' => 'Icon mapping create with backslash',
					'mappings' => [
						['expression' => '\\']
					],
					'error_inline' => [
						'id:mappings_0_expression' => 'Expression: Invalid regular expression.'
					]
				]
			],
			[
				[
					'name' => 'Create with empty expression',
					'error_inline' => [
						'id:mappings_0_expression' => 'Expression: This field cannot be empty.'
					]
				]
			],
			[
				[
					'name' => 'Icon mapping add two equals expressions',
					'mappings' => [
						['expression' => 'first expression'],
						['expression' => 'first expression']
					],
					'error_inline' => [
						'name:mappings[1][inventory_link]' => 'Entry "inventory_link=1, expression=first expression" is not unique.'
					]
				]
			],
			[
				[
					'name' => 'Icon mapping add empty second expressions',
					'mappings' => [
						['expression' => 'first expression'],
						['expression' => '']
					],
					'error_inline' => [
						'id:mappings_1_expression' => 'Expression: This field cannot be empty.'
					]
				]
			],
			[
				[
					'name' => 'Icon mapping add not existen regular expression',
					'mappings' => [
						['expression' => '@regexpnotexist']
					],
					'error' => [
						'Global regular expression "regexpnotexist" does not exist.'
					]
				]
			],
			[
				[
					'name' => 'Icon mapping add not existen global regular expression',
					'mappings' => [
						['expression' => '@']
					],
					'error' => [
						'Global regular expression "" does not exist.'
					]
				]
			],
			[
				[
					'name' => 'Icon mapping without expressions',
					'mappings' => [
						['action' => 'remove']
					],
					'error_inline' => [
						'Mappings' => 'This field cannot be empty.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateValidationData
	 *
	 * Test validate icon mapping creation.
	 */
	public function testFormAdministrationGeneralIconMapping_CreateValidation($data) {
		$this->zbxTestLogin('zabbix.php?action=iconmap.edit');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeWait('name', $data['name']);
		}

		// Input new row for Icon mapping
		if (array_key_exists('mappings', $data)) {
			$this->processExpressionRows($data['mappings']);
		}

		$form = $this->query('id:iconmap')->asForm()->one();
		$this->zbxTestClickXpath('//button[@value="Add"]');

		// Check the results in frontend.
		if (array_key_exists('error_inline', $data)) {
			$this->assertInlineError($form, $data['error_inline']);
		}
		else {
			$this->assertMessage(TEST_BAD, null, $data['error']);
		}

		// Check the results in DB
		if (!array_key_exists('check_db', $data) || $data['check_db'] === true) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM icon_map WHERE name='.zbx_dbstr($data['name'])));
		}
	}

	public function getCreateData() {
		return [
			[
				[
					'name' => 'Icon mapping testForm create default inventory and icons',
					'mappings' => [
						['expression' => '/!@#$%^&*()123abc']
					],
					'check_db' => true,
					'check_form' => true
				]
			],
			[
				[
					'name' => 'Icon mapping testForm create',
					'mappings' => [
						['expression' => 'test expression']
					],
					'inventory' => 'Alias',
					'icon' => 'Crypto-router_(96)',
					'default_icon' => 'Firewall_(96)',
					'check_db' => true,
					'check_form' => true
				]
			],
			[
				[
					'name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwerr',
					'mappings' => [
						['expression' => 'Create with long name']
					]
				]
			],
			[
				[
					'name' => 'Икона карты утф-8',
					'mappings' => [
						['expression' => 'Выражение утф-8']
					],
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default_icon' => 'Hub_(24)',
					'check_db' => true,
					'check_form' => true
				]
			],
			[
				[
					'name' => 'Icon mapping add three expressions',
					'mappings' => [
						['expression' => 'first expression'],
						['expression' => 'second expression'],
						['expression' => 'third expression']
					],
					'check_db' => true
				]
			],
			[
				[
					'name' => 'Icon mapping remove and add one expressions',
					'mappings' => [
						['action' => 'remove'],
						['expression' => 'one expression']
					]
				]
			],
			[
				[
					'name' => 'Icon mapping remove and add two expressions',
					'mappings' => [
						['action' => 'remove'],
						['expression' => 'first expression'],
						['expression' => 'second expression']
					],
					'screenshot' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 *
	 * Test creation of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Create($data) {
		$this->zbxTestLogin('zabbix.php?action=iconmap.edit');

		$this->zbxTestInputTypeWait('name', $data['name']);

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('mappings[0][inventory_link]', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('mappings[0][iconid]', $data['icon']);
		}

		if (array_key_exists('default_icon', $data)) {
			$this->zbxTestDropdownSelect('default-mapping-icon', $data['default_icon']);
		}

		// Input new row for Icon mapping.
		if (array_key_exists('mappings', $data)) {
			$this->processExpressionRows($data['mappings']);
		}

		// Take a screenshot of icon mapping form.
		if (array_key_exists('screenshot', $data)) {
			$this->page->removeFocus();
			$this->assertScreenshot($this->query('id:iconmap')->waitUntilVisible()->one(), 'Icon mapping');
		}

		$this->zbxTestClickXpath('//button[@value="Add"]');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map created');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');

		// Check the results in DB.
		if (array_key_exists('check_db', $data) && $data['check_db'] === true) {
			$expressions = [];

			foreach ($data['mappings'] as $options) {
				$expressions[] = $options['expression'];
			}

			$sql = 'SELECT null FROM icon_map LEFT JOIN icon_mapping'
					.' ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '.zbx_dbstr($data['name'])
					.' AND '.dbConditionString('icon_mapping.expression', $expressions);

			$this->assertEquals(count($expressions), CDBHelper::getCount($sql));
		}

		// Check the results in form
		if (array_key_exists('check_form', $data) && $data['check_form'] === true) {
			$defaults = [
				'inventory' => 'Type',
				'icon' => 'Cloud_(24)',
				'default_icon' => 'Cloud_(24)'
			];

			foreach ($defaults as $field => $value) {
				if (!array_key_exists($field, $data)) {
					$data[$field] = $value;
				}
			}

			$this->checkFormFields($data);
		}
	}

	/**
	 * Test cancel creation of icon mapping
	 */
	public function testFormAdministrationGeneralIconMapping_CancelCreation() {
		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=iconmap.edit');
		$this->zbxTestInputTypeWait('name', 'CancelCreation');
		$this->zbxTestInputTypeWait('mappings_0_expression', 'CancelCreation');
		$this->zbxTestClick('cancel');

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestTextNotPresent('CancelCreation');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Test update without any modification of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_SimpleUpdate() {
		$sql_icon_map = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_icon_map = CDBHelper::getHash($sql_icon_map);

		$sql_expression_hash = 'SELECT * FROM icon_mapping ORDER BY iconmappingid';
		$old_expression = CDBHelper::getHash($sql_expression_hash);

		$this->zbxTestLogin('zabbix.php?action=iconmap.list');

		foreach (CDBHelper::getAll('SELECT name FROM icon_map') as $iconmap) {
			$this->zbxTestClickLinkText($iconmap['name']);
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestClickWait('update');
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map updated');
		}

		$this->assertEquals($old_icon_map, CDBHelper::getHash($sql_icon_map));
		$this->assertEquals($old_expression, CDBHelper::getHash($sql_expression_hash));
	}

	public function getUpdateValidationData() {
		return [
			// Update with empty name.
			[
				[
					'name' => '',
					'error_inline' => [
						'Name' => 'This field cannot be empty.'
					]
				]
			],
			// Update with existing name.
			[
				[
					'name' => 'Icon mapping one',
					'error_inline' => [
						'Name' => 'This object already exists.'
					]
				]
			],
			// Expression with backslash
			[
				[
					'mappings' => [
						['expression' => '\\', 'action' => 'update']
					],
					'error_inline' => [
						'id:mappings_0_expression' => 'Expression: Invalid regular expression.'
					]
				]
			],
			// Empty expression
			[
				[
					'mappings' => [
						['expression' => '', 'action' => 'update']
					],
					'error_inline' => [
						'id:mappings_0_expression' => 'Expression: This field cannot be empty.'
					]
				]
			],
			// Not existen regular expression
			[
				[
					'mappings' => [
						['expression' => '@regexpnotexist', 'action' => 'update']
					],
					'error' => 'Global regular expression "regexpnotexist" does not exist.'
				]
			],
			// Remove expression
			[
				[
					'mappings' => [
						['action' => 'remove']
					],
					'error_inline' => [
						'Mappings' => 'This field cannot be empty.'
					]
				]
			],
			// Add the same second expression as the first
			[
				[
					'inventory' => 'Alias',
					'mappings' => [
						['expression' => '(1!@#$%^-=2*)', 'action' => 'update'],
						['expression' => '(1!@#$%^-=2*)']
					],
					'error_inline' => [
						'name:mappings[1][inventory_link]' => 'Entry "inventory_link=4, expression=(1!@#$%^-=2*)" is not unique.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateValidationData
	 *
	 * Test validate icon mapping updating
	 */
	public function testFormAdministrationGeneralIconMapping_UpdateValidation($data) {
		$update_icon = 'Icon mapping for update';
		$sql_hash = 'SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping'
				.' ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '.zbx_dbstr($update_icon);
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=iconmap.list');
		$this->zbxTestClickLinkTextWait($update_icon);
		$this->zbxTestWaitForPageToLoad();

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('name', $data['name']);
		}

		if (array_key_exists('mappings', $data)) {
			$this->processExpressionRows($data['mappings']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('mappings[1][inventory_link]', $data['inventory']);
		}

		$form = $this->query('id:iconmap')->asForm()->one();
		$this->zbxTestClick('update');
		$form->query('button:Update')->one()->waitUntilClassesNotPresent('is-loading');

		// Check the results in frontend.
		if (array_key_exists('error_inline', $data)) {
			$this->assertInlineError($form, $data['error_inline']);
		}
		else {
			$this->assertMessage(TEST_BAD, null, $data['error']);
		}

		// Check the results in DB
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function getUpdateData() {
		return [
			[
				[
					'old_name' => 'Icon mapping testForm update expression',
					'mappings' => [
						['expression' => '/!@#$%^&*()123updated', 'action' => 'update']
					],
					'check_db' => true
				]
			],
			[
				[
					'old_name' => 'Icon mapping testForm update expression',
					'mappings' => [
						['expression' => 'Test expression updated', 'action' => 'update']
					],
					'inventory' => 'Serial number B',
					'icon' => 'Firewall_(96)',
					'default_icon' => 'Crypto-router_(96)',
					'check_db' => true,
					'check_form' => true
				]
			],
			[
				[
					'old_name' => 'Icon mapping testForm update expression',
					'name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsaz0123456789',
					'mappings' => [
						['expression' => 'Update with long name', 'action' => 'update']
					]
				]
			],
			[
				[
					'old_name' => 'Icon mapping for update',
					'name' => 'Икона карты обновленна утф-8',
					'mappings' => [
						['expression' => 'Выражение обновленно утф-8', 'action' => 'update']
					],
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default_icon' => 'Hub_(24)',
					'check_db' => true,
					'check_form' => true
				]
			],
			[
				[
					'old_name' => 'Icon mapping one',
					'mappings' => [
						['expression' => 'Updated expression 1', 'action' => 'update'],
						['expression' => 'Updated expression 2', 'action' => 'update']
					],
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default_icon' => 'Hub_(24)',
					'check_db' => true,
					'check_form' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 *
	 * Test updating of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Update($data) {
		$this->zbxTestLogin('zabbix.php?action=iconmap.list');
		$this->zbxTestClickLinkTextWait($data['old_name']);
		$this->zbxTestWaitForPageToLoad();

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
		}
		else {
			$data['name'] = $data['old_name'];
		}

		// Input new row for Icon mapping
		if (array_key_exists('mappings', $data)) {
			$this->processExpressionRows($data['mappings']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('mappings[0][inventory_link]', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('mappings[0][iconid]', $data['icon']);
		}

		if (array_key_exists('default_icon', $data)) {
			$this->zbxTestDropdownSelect('default-mapping-icon', $data['default_icon']);
		}

		$this->zbxTestClick('update');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map updated');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');

		if (array_key_exists('check_db', $data)) {
			$expressions = [];

			foreach ($data['mappings'] as $options) {
				$expressions[] = $options['expression'];
			}

			$result = DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					."ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = ".zbx_dbstr($data['name'])
					.' AND '.dbConditionString('icon_mapping.expression', $expressions)
					.' ORDER BY icon_mapping.sortorder');

			$e = 0;
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $data['name']);

				$mapping_row = $data['mappings'][$e];
				$this->assertEquals($row['expression'], $mapping_row['expression']);

				$e++;
			}
		}

		// Check the results in form
		if (array_key_exists('check_form', $data)) {
			$this->checkFormFields($data);
		}
	}

	/**
	 * Test cancel update of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CancelUpdating() {
		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=iconmap.list');

		foreach (CDBHelper::getAll('SELECT name FROM icon_map LIMIT 1') as $iconmap) {
			$this->zbxTestClickLinkText($iconmap['name']);
			$this->zbxTestInputTypeOverwrite('name', $iconmap['name'].' (updated)');
			$this->zbxTestClick('cancel');
			$this->zbxTestCheckTitle('Configuration of icon mapping');
			$this->zbxTestCheckHeader('Icon mapping');
			$this->zbxTestTextNotPresent($iconmap['name'].' (updated)');
		}

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function getCloneValidationData() {
		return [
			[
				[
					'new_name' => 'Icon mapping one',
					'error' => [
						'Name' => 'This object already exists.'
					]
				]
			],
			[
				[
					'new_name' => '',
					'error' => [
						'Name' => 'This field cannot be empty.'
					]
				]
			],
			// Icon mapping clone with backslash.
			[
				[
					'new_name' => 'CLONE: Icon mapping update expression with two backslash',
					'mappings' => [
						['expression' => '\\', 'action' => 'update']
					],
					'error' => [
						'id:mappings_0_expression' => 'Expression: Invalid regular expression.'
					]
				]
			],
			// Clone with empty expression.
			[
				[
					'new_name' => 'CLONE: with empty expression',
					'mappings' => [
						['expression' => '', 'action' => 'update']
					],
					'error' => [
						'id:mappings_0_expression' => 'Expression: This field cannot be empty.'
					]
				]
			],
			// Clone and remove expressions.
			[
				[
					'new_name' => 'CLONE: with empty mappings',
					'mappings' => [
						['action' => 'remove'],
						['action' => 'remove'],
						['action' => 'remove'],
						['action' => 'remove']
					],
					'error' => [
						'Mappings' => 'This field cannot be empty.'
					]
				]
			],
			// Clone and change first expression name as the second expression name.
			[
				[
					'new_name' => 'CLONE: change first expression name as the second expression name',
					'mappings' => [
						['expression' => 'expression 2 for clone', 'action' => 'update']
					],
					'error' => [
						'name:mappings[1][inventory_link]' => 'Entry "inventory_link=1, expression=expression 2 for clone" is not unique.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneValidationData
	 *
	 * Test cloning of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CloneValidation($data) {
		$name = 'Icon mapping to check clone functionality';
		$sql_hash = 'SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping'
				.' ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '.zbx_dbstr($name);
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=iconmap.list');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('clone');
		$this->zbxTestWaitForPageToLoad();

		$this->zbxTestInputType('name', $data['new_name']);
		if (array_key_exists('mappings', $data)) {
			$this->processExpressionRows($data['mappings']);
		}

		$this->zbxTestClickXpath('//button[@value="Add"]');
		$this->zbxTestWaitForPageToLoad();
		$form = $this->query('id:iconmap')->asForm()->one();
		$form->query('xpath:.//tr[@id="iconmap-list-footer"]//button')->one()->waitUntilClassesNotPresent('is-loading');

		// Check the results in frontend.
		$this->assertInlineError($form, $data['error']);

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function getCloneData() {
		return [
			[
				[
					'old_name' => 'Icon mapping to check clone functionality',
					'name' => 'CLONE: Икона карты обновленна утф-8',
					'check_db' => '4'
				]
			],
			[
				[
					'old_name' => 'Icon mapping to check clone functionality',
					'name' => 'Clone Icon mapping with expression update',
					'mappings' => [
						['expression' => '/!@#$%^&*()123updated', 'action' => 'update']
					],
					'check_db' => '4'
				]
			],
			[
				[
					'old_name' => 'Icon mapping to check clone functionality',
					'name' => 'CLONE: Icon mapping testForm create',
					'mappings' => [
						['expression' => 'Test expression updated', 'action' => 'update']
					],
					'inventory' => 'Serial number B',
					'icon' => 'Firewall_(96)',
					'default_icon' => 'Crypto-router_(96)',
					'check_db' => '4',
					'check_form' => true
				]
			],
			[
				[
					'old_name' => 'Icon mapping to check clone functionality',
					'name' => 'LongNameqwertyuioplkjhg0123456789mqwertyuioplkjhgfdsazxcvbnmqwer',
					'mappings' => [
						['expression' => 'Update with long name', 'action' => 'update']
					],
					'check_db' => '4'
				]
			],
			[
				[
					'old_name' => 'Icon mapping to check clone functionality',
					'name' => 'Икона карты кирилица утф-8',
					'mappings' => [
						['expression' => 'Выражение обновленно кирилица утф-8', 'action' => 'update']
					],
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default_icon' => 'Hub_(24)',
					'check_db' => '4',
					'check_form' => true
				]
			],
			[
				[
					'old_name' => 'Icon mapping to check clone functionality',
					'name' => 'Add one more expression',
					'mappings' => [
						['expression' => 'Выражение обновленно кирилица утф-8', 'action' => 'update'],
						['expression' => 'New second expression', 'action' => 'update']
					],
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default_icon' => 'Hub_(24)',
					'check_db' => '4',
					'check_form' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneData
	 *
	 * Test cloning of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Clone($data) {
		$this->zbxTestLogin('zabbix.php?action=iconmap.list');
		$this->zbxTestClickLinkTextWait($data['old_name']);
		$this->zbxTestClickWait('clone');
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
		}

		if (array_key_exists('mappings', $data)) {
			$this->processExpressionRows($data['mappings']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('mappings[0][inventory_link]', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('mappings[0][iconid]', $data['icon']);
		}

		if (array_key_exists('default_icon', $data)) {
			$this->zbxTestDropdownSelect('default-mapping-icon', $data['default_icon']);
		}

		$this->zbxTestClickXpath('//button[@value="Add"]');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map created');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');

		// Check the results in DB.
		if (array_key_exists('check_db', $data)) {
			$this->assertEquals($data['check_db'], CDBHelper::getCount('SELECT icon_map.name, icon_mapping.expression FROM icon_map'
					.' LEFT JOIN icon_mapping ON icon_map.iconmapid = icon_mapping.iconmapid'
					.' WHERE icon_map.name = '.zbx_dbstr($data['name'])));
		}

		// Check the results in form
		if (array_key_exists('check_form', $data)) {
			$this->checkFormFields($data);
		}
	}

	/**
	 * Test cancel cloning of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CancelCloning() {
		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=iconmap.list');

		foreach (CDBHelper::getAll('SELECT name FROM icon_map LIMIT 2') as $iconmap) {
			$this->zbxTestClickLinkText($iconmap['name']);
			$this->zbxTestInputTypeOverwrite('name', $iconmap['name'].' (cloned)');
			$this->zbxTestClickWait('clone');
			$this->zbxTestClick('cancel');

			// Check the results in frontend.
			$this->zbxTestCheckTitle('Configuration of icon mapping');
			$this->zbxTestCheckHeader('Icon mapping');
			$this->zbxTestTextNotPresent($iconmap['name'].' (cloned)');
		}

		// Check the results in DB
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Test deleting of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Delete() {
		$name = 'Icon mapping to check delete functionality';

		$this->zbxTestLogin('zabbix.php?action=iconmap.list');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickAndAcceptAlert('delete');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map deleted');

		// Check the results in DB.
		$sql = 'SELECT * FROM icon_map WHERE name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Test cancel deleting of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CancelDelete() {
		$name = 'Icon mapping one';

		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=iconmap.list');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('delete');
		$this->zbxTestDismissAlert();

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');

		// Check the results in DB
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Try delete icon mapping used in map.
	 */
	public function testFormAdministrationGeneralIconMapping_DeleteUsedInMap() {
		$name = 'used_by_map';

		$sql_hash = 'SELECT * FROM icon_map WHERE name='.zbx_dbstr($name).' ORDER BY iconmapid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=iconmap.list');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickAndAcceptAlert('delete');

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Icon map "'.$name.'" cannot be deleted. Used in map');

		// Check the results in DB.
		$sql = 'SELECT * FROM icon_map WHERE name='.zbx_dbstr($name);
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	private function checkFormFields($data) {
		$this->zbxTestClickLinkTextWait($data['name']);
		$this->zbxTestAssertElementValue('name', $data['name']);
		$this->zbxTestAssertElementValue('mappings_0_expression', $data['mappings'][0]['expression']);
		$this->zbxTestDropdownAssertSelected('mappings[0][inventory_link]', $data['inventory']);
		$this->zbxTestDropdownAssertSelected('mappings[0][iconid]', $data['icon']);
		$this->zbxTestDropdownAssertSelected('default-mapping-icon', $data['default_icon']);
	}

	private function processExpressionRows($rows) {
		foreach ($rows as $i => $mapping_row) {
			switch (CTestArrayHelper::get($mapping_row, 'action', 'add')) {
				case 'add':
					// After removing last icon mapping and adding new one - it has index 0.
					if (isset($rows[0]['action']) && $rows[0]['action'] === 'remove') {
						$i--;
					}
					if (!$this->zbxTestElementPresentId('mappings_'.$i.'_expression')) {
						$this->zbxTestClickXpath('//button[@id="add" and @type="button"]');
					}
					$this->zbxTestInputTypeWait('mappings_'.$i.'_expression', $mapping_row['expression']);
					break;

				case 'update':
					if (!$this->zbxTestElementPresentId('mappings_'.$i.'_expression')) {
						$this->zbxTestClickXpath('//button[@id="add" and @type="button"]');
						$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('mappings_'.$i.'_expression'));
					}
					$this->zbxTestInputType('mappings_'.$i.'_expression', $mapping_row['expression']);
					break;

				case 'remove':
					$this->zbxTestClickXpathWait('//input[@id="mappings_'.$i.'_expression"]/../../td/button');

					break;
			}
		}
	}
}
