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

	public function create_validation() {
		return[
			[
				[
					'mappings' => [
						['expression' => 'Create with empty name']
					],
					'error' => 'Invalid parameter "/1/name": cannot be empty.',
					'NoDbCheck' => true
				],
			],
			[
				[
					'name' => ' ',
					'mappings' => [
						['expression' => 'Create with spaces in name']
					],
					'error' => 'Invalid parameter "/1/name": cannot be empty.',
				],
			],
			[
				[
					'name' => 'Icon mapping one for testPage',
					'mappings' => [
						['expression' => 'Create with existing name']
					],
					'error' => 'Icon map "Icon mapping one for testPage" already exists.',
					'NoDbCheck' => true
				]
			],
			[
				[
					'name' => 'Icon mapping create with slash',
					'mappings' => [
						['expression' => '/']
					],
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
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
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
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
					'name' => 'Icon mapping add not existen regular expression',
					'mappings' => [
						['expression' => '@regexpnotexist'],
					],
					'error' => 'Global regular expression "regexpnotexist" does not exist.'
				]
			],
			[
				[
					'name' => 'Icon mapping add not existen global regular expression',
					'mappings' => [
						['expression' => '@'],
					],
					'error' => 'Global regular expression "" does not exist.'
				]
			],
			[
				[
					'name' => 'Icon mapping without expressions',
					'mappings' => [
						['expression' => 'first expression', 'remove' => true]
					],
					'error' => 'Invalid parameter "/1/mappings": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider create_validation
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
			$expression_count = 0;

			foreach ($data['mappings'] as $mapping_row) {
				$this->zbxTestInputTypeWait('iconmap_mappings_new'.$expression_count.'_expression', $mapping_row['expression']);

				if (array_key_exists('remove', $mapping_row)) {
					$this->zbxTestClickXpathWait("//tr[@id='iconmapidRow_new". $expression_count ."']//button");
				}

				$expression_count++;
				if (count($data['mappings']) == $expression_count) {
					break;
				}
				$this->zbxTestClick('addMapping');
			}
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot create icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		if (!array_key_exists('NoDbCheck', $data)) {
			$this->assertEquals(0, DBcount("SELECT NULL FROM icon_map WHERE name='".$data['name']."'"));
		}
	}

	public function create() {
		return[
			[
				[
					'name' => 'Icon mapping testForm create default inventory and icons',
					'mappings' => [
						['expression' => '!@#$%^&*()123abc']
					],
					'dbCheck' => true,
					'formCheckDefaultValues' => true
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
					'default' => 'Firewall_(96)',
					'dbCheck' => true,
					'formCheck' => true
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
					'default' => 'Hub_(24)',
					'dbCheck' => true,
					'formCheck' => true
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
					'dbCheck' => true
				]
			],
			[
				[
					'name' => 'Icon mapping add and remove one expressions',
					'mappings' => [
						['expression' => 'one expression', 'remove' => true],
						['expression' => 'one expression']
					]
				]
			],
			[
				[
					'name' => 'Icon mapping add and remove two expressions',
					'mappings' => [
						['expression' => 'first expression', 'remove' => true],
						['expression' => 'second expression', 'remove' => true],
						['expression' => 'first expression'],
						['expression' => 'second expression']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
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

		if (array_key_exists('default', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default']);
		}

		// Input new row for Icon mapping.
		if (array_key_exists('mappings', $data)) {
			$expression_count = 0;

			foreach ($data['mappings'] as $mapping_row) {
				$this->zbxTestInputTypeWait('iconmap_mappings_new'.$expression_count.'_expression', $mapping_row['expression']);

				if (array_key_exists('remove', $mapping_row)) {
					$this->zbxTestClickXpathWait("//tr[@id='iconmapidRow_new". $expression_count ."']//button");
				}

				$expression_count++;
				if (count($data['mappings']) == $expression_count) {
					break;
				}
				$this->zbxTestClick('addMapping');
			}
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map created');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB.
		if (array_key_exists('dbCheck', $data)) {
			$expressions = [];

			foreach ($data['mappings'] as $options) {
				$expressions[] = $options['expression'];
			}

			$sql = 'SELECT null FROM icon_map LEFT JOIN icon_mapping '
					. 'ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = \''.$data['name'].'\' AND '
					. dbConditionString('icon_mapping.expression', $expressions);

			$this->assertEquals(count($expressions), DBcount($sql));
		}

		// Check the results in form
		if (array_key_exists('formCheck', $data)) {
			$this->zbxTestDoubleClickLinkText($data['name'], 'iconmap_name');
			$this->zbxTestAssertElementValue('iconmap_name', $data['name']);
			$this->zbxTestAssertElementValue('iconmap_mappings_0_expression', $data['mappings'][0]['expression']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][inventory_link]', $data['inventory']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][iconid]', $data['icon']);
			$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', $data['default']);
		}

		// Check default values in form
		if (array_key_exists('formCheckDefaultValues', $data)) {
			$this->zbxTestDoubleClickLinkText($data['name'], 'iconmap_name');
			$this->zbxTestAssertElementValue('iconmap_name', $data['name']);
			$this->zbxTestAssertElementValue('iconmap_mappings_0_expression', $data['mappings'][0]['expression']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][inventory_link]', 'Type');
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][iconid]', 'Cloud_(24)');
			$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', 'Cloud_(24)');
		}
	}

	/**
	* Test cancel creation of icon mapping
	*/
	public function testFormAdministrationGeneralIconMapping_CancelCreation(){
		$cancel_name='Cancel Icon mapping creation';
		$cancel_expression='Cancel Icon mapping creation';

		$this->zbxTestLogin('adm.iconmapping.php?form=create');
		$this->zbxTestInputTypeWait('iconmap_name', $cancel_name);
		$this->zbxTestInputTypeWait('iconmap_mappings_new0_expression', $cancel_expression);
		$this->zbxTestClick('cancel');

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestTextNotPresent($cancel_name);

		// Check the results in DB
		$this->assertEquals(0, DBcount("SELECT NULL FROM icon_map WHERE name='".$cancel_name."'"));
	}

	/**
	 * Test update without any modification of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_SimpleUpdate(){
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

		$new_icon_map = DBhash($sql_icon_map);
		$this->assertEquals($old_icon_map, $new_icon_map);

		$new_expression = DBhash($sql_expression_hash);
		$this->assertEquals($old_expression, $new_expression);
	}

	public function update_validation(){
		return [
			[
				[
					'new_name' => '',
					'expression' => 'Update with empty name',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'new_name' => 'Icon mapping one for testPage',
					'expression' => 'Update with existing name',
					'error' => 'Icon map "Icon mapping one for testPage" already exists.'
				]
			],
			// Expression with slash
			[
				[
					'expression' => '/',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Expression with backslash
			[
				[
					'expression' => '\\',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Expression with double slash
			[
				[
					'expression' => '//',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Empty expression
			[
				[
					'expression' => '',
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
				]
			],
			// Not existen regular expression
			[
				[
					'expression' => '@regexpnotexist',
					'error' => 'Global regular expression "regexpnotexist" does not exist.'
				]
			]
		];
	}

	/**
	 * @dataProvider update_validation
	 *
	 * Test validate icon mapping updating
	 */
	public function testFormAdministrationGeneralIconMapping_ValidateChangeAndUpdate($data) {
		$name = 'Icon mapping two for testPage';
		$sql_hash = "SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$name."'";
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');

		if (array_key_exists('new_name', $data)) {
			$this->zbxTestInputType('iconmap_name', $data['new_name']);
		}

		$this->zbxTestInputType('iconmap_mappings_0_expression', $data['expression']);

		$this->zbxTestClick('update');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot update icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function update(){
		return [
			[
				[
					'name' => 'Icon mapping testForm update expression',
					'new_name' => 'Icon mapping testForm update expression',
					'expression' => '!@#$%^&*()123updated',
					'dbCheck' => true,
				]
			],
			[
				[
					'name' => 'Icon mapping testForm update expression',
					'new_name' => 'Icon mapping testForm update expression',
					'expression' => 'Test expression updated',
					'inventory' => 'Serial number B',
					'icon' => 'Firewall_(96)',
					'default' => 'Crypto-router_(96)',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'name' => 'Icon mapping testForm update expression',
					'new_name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsaz0123456789',
					'expression' => 'Update with long name',
				]
			],
			[
				[
					'name' => 'Icon mapping two for testPage',
					'new_name' => 'Икона карты обновленна утф-8',
					'expression' => 'Выражение обновленно утф-8',
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default' => 'Hub_(24)',
					'dbCheck' => true,
					'formCheck' => true
				]
			]
		];
	}

	/**
	 * @dataProvider update
	 *
	 * Test updating of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_ChangeAndUpdate($data) {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['name'], 'iconmap_name');

		$this->zbxTestInputTypeOverwrite('iconmap_name', $data['new_name']);

		if (array_key_exists('expression', $data)) {
			$this->zbxTestInputTypeOverwrite('iconmap_mappings_0_expression', $data['expression']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_0_inventory_link', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_0_iconid', $data['icon']);
		}

		if (array_key_exists('default', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default']);
		}

		$this->zbxTestClick('update');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map updated');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();

		if (array_key_exists('dbCheck', $data)) {
			$result = DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$data['new_name']."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $data['new_name']);
				$this->assertEquals($row['expression'], $data['expression']);
			}
		}

		// Check the results in form
		if (array_key_exists('formCheck', $data)) {
			$this->zbxTestClickLinkText($data['new_name'], 'iconmap_name');
			$this->zbxTestAssertElementValue('iconmap_name', $data['new_name']);

			$this->zbxTestAssertElementValue('iconmap_mappings_0_expression', $data['expression']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][inventory_link]', $data['inventory']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][iconid]', $data['icon']);
			$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', $data['default']);
		}
	}

	/**
	 * Test cancel update of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CancelUpdating(){
		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');

		foreach (DBdata("SELECT name FROM icon_map LIMIT 2", false) as $iconmap) {
			$iconmap = $iconmap[0];
			$this->zbxTestClickLinkText($iconmap['name']);
			$this->zbxTestInputTypeOverwrite('iconmap_name', $iconmap['name'].' (updated)');
			$this->zbxTestClick('cancel');
			$this->zbxTestCheckTitle('Configuration of icon mapping');
			$this->zbxTestCheckHeader('Icon mapping');
			$this->zbxTestCheckFatalErrors();
			$this->zbxTestTextNotPresent($iconmap['name'].' (updated)');
		}

		$new_hash = DBhash($sql_hash);
		$this->assertEquals($old_hash, $new_hash);
	}

	public function validate_cloning(){
		return [
			[
				[
					'new_name' => 'Icon mapping one for testPage',
					'expression' => 'Make Clone with existing name',
					'error' => 'Icon map "Icon mapping one for testPage" already exists.',
					'DBexist' => true
				]
			],
			[
				[
					'new_name' => '',
					'expression' => 'Clone with empty name',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'new_name' => 'CLONE: Icon mapping update expression with slash',
					'expression' => '/',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			// Icon mapping update with backslash.
			[
				[
					'new_name' => 'CLONE: Icon mapping one for testPage',
					'expression' => '\\',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			// Icon mapping update with double slash.
			[
				[
					'new_name' => 'CLONE: Icon mapping one for testPage',
					'expression' => '//',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			// Update with empty expression.
			[
				[
					'new_name' => 'CLONE: Icon mapping one for testPage',
					'expression' => '',
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
				]
			],
		];
	}

	/**
	 * @dataProvider validate_cloning
	 *
	 * Test cloning of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_ValidateClone($data) {
		$name='Icon mapping one for testPage';
		$sql_hash = "SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$name."'";
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickWait('clone');

		$this->zbxTestInputType('iconmap_name', $data['new_name']);
		$this->zbxTestInputType('iconmap_mappings_0_expression', $data['expression']);

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot create icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		if (!array_key_exists('DBexist', $data)){
			$this->assertEquals(0, DBcount("SELECT NULL FROM icon_map WHERE name='".$data['new_name']."'"));
		}

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function cloning(){
		return [
			[
				[
					'name' => 'Икона карты обновленна утф-8',
					'new_name' => 'CLONE: Икона карты обновленна утф-8',
					'dbCheck' => true,
				]
			],
			[
				[
					'name' => 'Icon mapping one for testPage',
					'new_name' => 'Clone Icon mapping with expression update',
					'expression' => '!@#$%^&*()123updated',
					'dbCheck' => true,
				]
			],
			[
				[
					'name' => 'Icon mapping testForm create',
					'new_name' => 'CLONE: Icon mapping testForm create',
					'expression' => 'Test expression updated',
					'inventory' => 'Serial number B',
					'icon' => 'Firewall_(96)',
					'default' => 'Crypto-router_(96)',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'name' => 'Icon mapping testForm create default inventory and icons',
					'new_name' => 'LongNameqwertyuioplkjhg0123456789mqwertyuioplkjhgfdsazxcvbnmqwer',
					'expression' => 'Update with long name',
				]
			],
			[
				[
					'name' => 'Icon mapping one for testPage',
					'new_name' => 'Икона карты кирилица утф-8',
					'expression' => 'Выражение обновленно кирилица утф-8',
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default' => 'Hub_(24)',
					'dbCheck' => true,
					'formCheck' => true
				]
			]
		];
	}

	/**
	 * @dataProvider cloning
	 *
	 * Test cloning of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Clone($data) {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['name'], 'iconmap_name');
		$this->zbxTestClick('clone');
		if (array_key_exists('new_name', $data)) {
			$this->zbxTestInputTypeOverwrite('iconmap_name', $data['new_name']);
		}

		if (array_key_exists('expression', $data)) {
			$this->zbxTestInputTypeOverwrite('iconmap_mappings_0_expression', $data['expression']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_0_inventory_link', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_0_iconid', $data['icon']);
		}

		if (array_key_exists('default', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default']);
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map created');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB.
		if (array_key_exists('dbCheck', $data)) {
			$result = DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$data['new_name']."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $data['new_name']);
			}
		}
		// Check the results in form
		if (array_key_exists('formCheck', $data)) {
			$this->zbxTestDoubleClickLinkText($data['new_name'], 'iconmap_name');
			$this->zbxTestAssertElementValue('iconmap_name', $data['new_name']);
			$this->zbxTestAssertElementValue('iconmap_mappings_0_expression', $data['expression']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][inventory_link]', $data['inventory']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][iconid]', $data['icon']);
			$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', $data['default']);
		}
	}

	/**
	 * Test cancel cloning of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CancelCloning(){
		$name='Icon mapping one for testPage';
		$new_name='CANCEL CLONING: Icon mapping one for testPage';

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickWait('clone');
		$this->zbxTestInputTypeOverwrite('iconmap_name', $new_name);
		$this->zbxTestClick('cancel');

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestTextNotPresent($new_name);

		// Check the results in DB
		$this->assertEquals(0, DBcount("SELECT NULL FROM icon_map WHERE name='".$new_name."'"));
	}

	/**
	 * Test deleting of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Delete() {
		$name='Icon mapping to check delete functionality';

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickWait('delete');
		$this->webDriver->switchTo()->alert()->accept();

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map deleted');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB.
		$sql = "SELECT * FROM icon_map WHERE name='$name'";
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Test cancel deleting of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CancelDelete() {
		$name='Icon mapping one for testPage';

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickWait('delete');
		$this->webDriver->switchTo()->alert()->dismiss();

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		$this->assertEquals(1, DBcount("SELECT NULL FROM icon_map WHERE name='".$name."'"));
	}

	/**
	 * Try delete icon mapping used in map.
	 */
	public function testFormAdministrationGeneralIconMapping_DeleteUsedInMap() {
		$name='used_by_map';

		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid where name='.zbx_dbstr($name);
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickWait('delete');
		$this->webDriver->switchTo()->alert()->accept();

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Icon map "'.$name.'" cannot be deleted. Used in map');

		// Check the results in DB.
		$sql = 'SELECT * FROM icon_map WHERE name='.zbx_dbstr($name);
		$this->assertEquals(1, DBcount($sql));
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}
}
