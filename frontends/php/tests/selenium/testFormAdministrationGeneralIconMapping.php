<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

/**
 * @backup icon_map
 */
class testFormAdministrationGeneralIconMapping extends CWebTest {

	public function getCreateValidationData() {
		return [
			// Create icon mapping with empty name
			[
				[
					'error' => 'Invalid parameter "/1/name": cannot be empty.',
					'check_db' => false
				]
			],
			// Create with spaces in name
			[
				[
					'name' => ' ',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Icon mapping one',
					'mappings' => [
						['expression' => 'Create with existing name']
					],
					'error' => 'Icon map "Icon mapping one" already exists.',
					'check_db' => false
				]
			],
			[
				[
					'name' => 'Icon mapping create with slash',
					'mappings' => [
						['expression' => '/']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			[
				[
					'name' => 'Icon mapping create with backslash',
					'mappings' => [
						['expression' => '\\']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			[
				[
					'name' => 'Icon mapping create with double slash',
					'mappings' => [
						['expression' => '//']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			[
				[
					'name' => 'Create with empty expression',
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Icon mapping add two equals expressions',
					'mappings' => [
						['expression' => 'first expression'],
						['expression' => 'first expression']
					],
					'error' => 'Invalid parameter "/1/mappings/2": value (inventory_link, expression)=(1, first expression) already exists.'
				]
			],
			[
				[
					'name' => 'Icon mapping add empty second expressions',
					'mappings' => [
						['expression' => 'first expression'],
						['expression' => '']
					],
					'error' => 'Invalid parameter "/1/mappings/2/expression": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Icon mapping add not existen regular expression',
					'mappings' => [
						['expression' => '@regexpnotexist']
					],
					'error' => 'Global regular expression "regexpnotexist" does not exist.'
				]
			],
			[
				[
					'name' => 'Icon mapping add not existen global regular expression',
					'mappings' => [
						['expression' => '@']
					],
					'error' => 'Global regular expression "" does not exist.'
				]
			],
			[
				[
					'name' => 'Icon mapping without expressions',
					'mappings' => [
						['action' => 'remove']
					],
					'error' => 'Invalid parameter "/1/mappings": cannot be empty.'
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
		$this->zbxTestLogin('adm.iconmapping.php?form=create');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeWait('iconmap_name', $data['name']);
		}

		// Input new row for Icon mapping
		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data['mappings']);
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot create icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		if (!array_key_exists('check_db', $data) || $data['check_db'] === true) {
			$this->assertEquals(0, DBcount('SELECT NULL FROM icon_map WHERE name='.zbx_dbstr($data['name'])));
		}
	}

	public function getCreateData() {
		return [
			[
				[
					'name' => 'Icon mapping testForm create default inventory and icons',
					'mappings' => [
						['expression' => '!@#$%^&*()123abc']
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
					],
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
					]
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
		$this->zbxTestLogin('adm.iconmapping.php?form=create');

		$this->zbxTestInputTypeWait('iconmap_name', $data['name']);

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_inventory_link', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_iconid', $data['icon']);
		}

		if (array_key_exists('default_icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default_icon']);
		}

		// Input new row for Icon mapping.
		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data['mappings']);
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map created');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB.
		if (array_key_exists('check_db', $data) && $data['check_db'] === true) {
			$expressions = [];

			foreach ($data['mappings'] as $options) {
				$expressions[] = $options['expression'];
			}

			$sql = 'SELECT null FROM icon_map LEFT JOIN icon_mapping'
					.' ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '.zbx_dbstr($data['name'])
					.' AND '.dbConditionString('icon_mapping.expression', $expressions);

			$this->assertEquals(count($expressions), DBcount($sql));
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
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php?form=create');
		$this->zbxTestInputTypeWait('iconmap_name', 'CancelCreation');
		$this->zbxTestInputTypeWait('iconmap_mappings_new0_expression', 'CancelCreation');
		$this->zbxTestClick('cancel');

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestTextNotPresent('CancelCreation');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	/**
	 * Test update without any modification of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_SimpleUpdate() {
		$sql_icon_map = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_icon_map = DBhash($sql_icon_map);

		$sql_expression_hash = 'SELECT * FROM icon_mapping ORDER BY iconmappingid';
		$old_expression = DBhash($sql_expression_hash);

		$this->zbxTestLogin('adm.iconmapping.php');

		foreach (DBdata("SELECT name FROM icon_map", false) as $iconmap) {
			$iconmap = $iconmap[0];
			$this->zbxTestClickLinkText($iconmap['name']);
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestClickWait('update');
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map updated');
		}

		$this->assertEquals($old_icon_map, DBhash($sql_icon_map));
		$this->assertEquals($old_expression, DBhash($sql_expression_hash));
	}

	public function getUpdateValidationData() {
		return [
			// Update with empty name.
			[
				[
					'name' => '',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			// Update with existing name.
			[
				[
					'name' => 'Icon mapping one',
					'error' => 'Icon map "Icon mapping one" already exists.'
				]
			],
			// Expression with slash
			[
				[
					'mappings' => [
						['expression' => '/', 'action' => 'update']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Expression with backslash
			[
				[
					'mappings' => [
						['expression' => '\\', 'action' => 'update']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Expression with double slash
			[
				[
					'mappings' => [
						['expression' => '//', 'action' => 'update']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Empty expression
			[
				[
					'mappings' => [
						['expression' => '', 'action' => 'update']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
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
					'error' => 'Invalid parameter "/1/mappings": cannot be empty.'
				]
			],
			// Add the same second expression as the first
			[
				[
					'inventory' => 'Alias',
					'mappings' => [
						['expression' => '(1!@#$%^-=2*)']
					],
					'error' => 'Invalid parameter "/1/mappings/2": value (inventory_link, expression)=(4, (1!@#$%^-=2*)) already exists.'
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
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($update_icon, 'iconmap_name');

		if (array_key_exists('name', $data)) {
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('iconmap_name'));
			$this->zbxTestInputType('iconmap_name', $data['name']);
		}

		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data['mappings']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_inventory_link', $data['inventory']);
		}

		$this->zbxTestClick('update');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot update icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function getUpdateData() {
		return [
			[
				[
					'old_name' => 'Icon mapping testForm update expression',
					'mappings' => [
						['expression' => '!@#$%^&*()123updated', 'action' => 'update']
					],
					'check_db' => true,
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
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['old_name'], 'iconmap_name');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('iconmap_name', $data['name']);
		}
		else {
			$data['name'] = $data['old_name'];
		}

		// Input new row for Icon mapping
		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data['mappings']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_0_inventory_link', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_0_iconid', $data['icon']);
		}

		if (array_key_exists('default_icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default_icon']);
		}

		$this->zbxTestClick('update');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map updated');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();

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
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');

		foreach (DBdata("SELECT name FROM icon_map LIMIT 1", false) as $iconmap) {
			$iconmap = $iconmap[0];
			$this->zbxTestClickLinkText($iconmap['name']);
			$this->zbxTestInputTypeOverwrite('iconmap_name', $iconmap['name'].' (updated)');
			$this->zbxTestClick('cancel');
			$this->zbxTestCheckTitle('Configuration of icon mapping');
			$this->zbxTestCheckHeader('Icon mapping');
			$this->zbxTestCheckFatalErrors();
			$this->zbxTestTextNotPresent($iconmap['name'].' (updated)');
		}

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function getCloneValidationData() {
		return [
			[
				[
					'new_name' => 'Icon mapping one',
					'error' => 'Icon map "Icon mapping one" already exists.'
				]
			],
			[
				[
					'new_name' => '',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'new_name' => 'CLONE: Icon mapping update expression with slash',
					'mappings' => [
						['expression' => '/', 'action' => 'update']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Icon mapping clone with backslash.
			[
				[
					'new_name' => 'CLONE: Icon mapping update expression with two backslash',
					'mappings' => [
						['expression' => '\\', 'action' => 'update']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Icon mapping clone with double slash.
			[
				[
					'new_name' => 'CLONE: Icon mapping update expression with two slash',
					'mappings' => [
						['expression' => '//', 'action' => 'update']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Clone with empty expression.
			[
				[
					'new_name' => 'CLONE: with empty expression',
					'mappings' => [
						['expression' => '', 'action' => 'update']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
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
						['action' => 'remove'],
					],
					'error' => 'Invalid parameter "/1/mappings": cannot be empty.'
				]
			],
			// Clone and change first expression name as the second expression name.
			[
				[
					'new_name' => 'CLONE: change first expression name as the second expression name',
					'mappings' => [
						['expression' => 'expresssion 2 for clone', 'action' => 'update'],
					],
					'error' => 'Invalid parameter "/1/mappings/2": value (inventory_link, expression)=(1, expresssion 2 for clone) already exists.'
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
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickWait('clone');

		$this->zbxTestInputType('iconmap_name', $data['new_name']);
		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data['mappings']);
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot create icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($old_hash, DBhash($sql_hash));
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
						['expression' => '!@#$%^&*()123updated', 'action' => 'update']
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
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['old_name'], 'iconmap_name');
		$this->zbxTestClickWait('clone');
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('iconmap_name', $data['name']);
		}

		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data['mappings']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_0_inventory_link', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_0_iconid', $data['icon']);
		}

		if (array_key_exists('default_icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default_icon']);
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map created');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB.
		if (array_key_exists('check_db', $data)) {
			$this->assertEquals($data['check_db'], DBcount('SELECT icon_map.name, icon_mapping.expression FROM icon_map'
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
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');

		foreach (DBdata('SELECT name FROM icon_map LIMIT 2', false) as $iconmap) {
			$iconmap = $iconmap[0];
			$this->zbxTestClickLinkText($iconmap['name']);
			$this->zbxTestInputTypeOverwrite('iconmap_name', $iconmap['name'].' (cloned)');
			$this->zbxTestClickWait('clone');
			$this->zbxTestClick('cancel');

			// Check the results in frontend.
			$this->zbxTestCheckTitle('Configuration of icon mapping');
			$this->zbxTestCheckHeader('Icon mapping');
			$this->zbxTestCheckFatalErrors();
			$this->zbxTestTextNotPresent($iconmap['name'].' (cloned)');
		}

		// Check the results in DB
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	/**
	 * Test deleting of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Delete() {
		$name = 'Icon mapping to check delete functionality';

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickAndAcceptAlert('delete');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map deleted');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB.
		$sql = 'SELECT * FROM icon_map WHERE name='.zbx_dbstr($name);
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Test cancel deleting of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CancelDelete() {
		$name = 'Icon mapping one';

		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickWait('delete');
		$this->webDriver->switchTo()->alert()->dismiss();

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	/**
	 * Try delete icon mapping used in map.
	 */
	public function testFormAdministrationGeneralIconMapping_DeleteUsedInMap() {
		$name = 'used_by_map';

		$sql_hash = 'SELECT * FROM icon_map WHERE name='.zbx_dbstr($name).' ORDER BY iconmapid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickAndAcceptAlert('delete');

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Icon map "'.$name.'" cannot be deleted. Used in map');

		// Check the results in DB.
		$sql = 'SELECT * FROM icon_map WHERE name='.zbx_dbstr($name);
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	private function checkFormFields($data) {
		$this->zbxTestDoubleClickLinkText($data['name'], 'iconmap_name');
		$this->zbxTestAssertElementValue('iconmap_name', $data['name']);
		$this->zbxTestAssertElementValue('iconmap_mappings_0_expression', $data['mappings'][0]['expression']);
		$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][inventory_link]', $data['inventory']);
		$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][iconid]', $data['icon']);
		$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', $data['default_icon']);
	}

	private function addExpressionRows($rows) {
		foreach ($rows as $i => $mapping_row) {
			$action = (array_key_exists('action', $mapping_row) ? $mapping_row['action'] : 'add');

			switch ($action) {
				case 'add':
					if (!$this->zbxTestElementPresentId('iconmap_mappings_new'.$i.'_expression')) {
						$this->zbxTestClick('addMapping');
					}
					$this->zbxTestInputTypeWait('iconmap_mappings_new'.$i.'_expression', $mapping_row['expression']);
					break;

				case 'update':
					if (!$this->zbxTestElementPresentId('iconmap_mappings_'.$i.'_expression')) {
						$this->zbxTestClick('addMapping');
						$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('iconmap_mappings_'.$i.'_expression'));
					}
					$this->zbxTestInputType('iconmap_mappings_'.$i.'_expression', $mapping_row['expression']);
					break;

				case 'remove':
					if ($this->zbxTestIsElementPresent("//tr[@id='iconmapidRow_new".$i."']//button")) {
						$this->zbxTestClickXpathWait("//tr[@id='iconmapidRow_new".$i."']//button");
					}
					else {
						$this->zbxTestClickXpathWait("//tr[@id='iconmapidRow_".$i."']//button");
					}
					break;
			}
		}
	}

}
