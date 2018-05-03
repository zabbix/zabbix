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
						'DbCheck' => 'Variable for DB check'
					],
				],
				// Create with spaces in name
				[
					[
						'name' => ' ',
						'error' => 'Invalid parameter "/1/name": cannot be empty.',
					],
				],
				[
					[
						'name' => 'Icon mapping one',
						'new' => 'expression fields with "new" suffix',
						'mappings' => [
							['expression' => 'Create with existing name']
						],
						'error' => 'Icon map "Icon mapping one" already exists.',
						'DbCheck' => 'Variable for DB check'
					]
				],
				[
					[
						'name' => 'Icon mapping create with slash',
						'new' => 'expression fields with "new" suffix',
						'mappings' => [
							['expression' => '/']
						],
						'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
					]
				],
				[
					[
						'name' => 'Icon mapping create with backslash',
						'new' => 'expression fields with "new" suffix',
						'mappings' => [
							['expression' => '\\']
						],
						'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
					]
				],
				[
					[
						'name' => 'Icon mapping create with double slash',
						'new' => 'expression fields with "new" suffix',
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
						'new' => 'expression fields with "new" suffix',
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
						'new' => 'expression fields with "new" suffix',
						'mappings' => [
							['expression' => '@regexpnotexist']
						],
						'error' => 'Global regular expression "regexpnotexist" does not exist.'
					]
				],
				[
					[
						'name' => 'Icon mapping add not existen global regular expression',
						'new' => 'expression fields with new suffix',
						'mappings' => [
							['expression' => '@']
						],
						'error' => 'Global regular expression "" does not exist.'
					]
				],
				[
					[
						'name' => 'Icon mapping without expressions',
						'new' => 'expression fields with "new" suffix',
						'mappings' => [
							['expression' => 'first expression', 'remove' => true]
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
			$this->addExpressionRows($data);
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot create icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		if (!array_key_exists('DbCheck', $data)) {
			$this->assertEquals(0, DBcount("SELECT NULL FROM icon_map WHERE name=".zbx_dbstr($data['name'])));
		}
	}

	public function getCreateData() {
		return [
				[
					[
						'name' => 'Icon mapping testForm create default inventory and icons',
						'new' => 'expression fields with "new" suffix',
						'mappings' => [
							['expression' => '!@#$%^&*()123abc']
						],
						'inventory' => 'Type',
						'icon' => 'Cloud_(24)',
						'default' => 'Cloud_(24)',
						'dbCheck' => true,
						'formCheck' => true
					]
				],
				[
					[
						'name' => 'Icon mapping testForm create',
						'new' => 'expression fields with "new" suffix',
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
						'new' => 'expression fields with "new" suffix',
						'mappings' => [
							['expression' => 'Create with long name']
						],
					]
				],
				[
					[
						'name' => 'Икона карты утф-8',
						'new' => 'expression fields with "new" suffix',
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
						'new' => 'expression fields with "new" suffix',
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
						'new' => 'expression fields with "new" suffix',
						'mappings' => [
							['expression' => 'one expression', 'remove' => true],
							['expression' => 'one expression']
						]
					]
				],
				[
					[
						'name' => 'Icon mapping add and remove two expressions',
						'new' => 'expression fields with "new" suffix',
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
	 * @dataProvider getCreateData
	 *
	 * Test creation of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Create($data) { // <-- looks like this can be unified with the previous test
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
			$this->addExpressionRows($data);
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
					. 'ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '.zbx_dbstr($data['name']).' AND '
					. dbConditionString('icon_mapping.expression', $expressions);

			$this->assertEquals(count($expressions), DBcount($sql));
		}

		// Check the results in form
		if (array_key_exists('formCheck', $data)) {
			$this->checkFormFields($data);
		}
	}

	/**
	* Test cancel creation of icon mapping
	*/
	public function testFormAdministrationGeneralIconMapping_CancelCreation(){
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
		$this->zbxTestTextNotPresent(' CancelCreation');

		$this->assertEquals($old_hash, DBhash($sql_hash));
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

	public function getUpdateValidationData(){
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
							['expression' => '/']
						],
						'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
					]
				],
				// Expression with backslash
				[
					[
						'mappings' => [
							['expression' => '\\']
						],
						'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
					]
				],
				// Expression with double slash
				[
					[
						'mappings'=> [
							['expression' => '//']
						],
						'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
					]
				],
				// Empty expression
				[
					[
						'mappings' => [
							['expression' => '']
						],
						'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
					]
				],
				// Not existen regular expression
				[
					[
						'mappings' => [
							['expression' => '@regexpnotexist']
						],
						'error' => 'Global regular expression "regexpnotexist" does not exist.'
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
		$name_st = 'Icon mapping two';
		$sql_hash = "SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$name_st."'";
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($name_st, 'iconmap_name');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('iconmap_name', $data['name']);
		}

		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data);
		}

		$this->zbxTestClick('update');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot update icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function getUpdateData(){
		return [
				[
					[
						'old_name' => 'Icon mapping testForm update expression',
						'name' => 'Icon mapping testForm update expression',
						'mappings'=> [
							['expression' => '!@#$%^&*()123updated']
						],
						'dbCheck' => true,
					]
				],
				[
					[
						'old_name' => 'Icon mapping testForm update expression',
						'name' => 'Icon mapping testForm update expression',
						'mappings'=> [
							['expression' => 'Test expression updated']
						],
						'inventory' => 'Serial number B',
						'icon' => 'Firewall_(96)',
						'default' => 'Crypto-router_(96)',
						'dbCheck' => true,
						'formCheck' => true
					]
				],
				[
					[
						'old_name' => 'Icon mapping testForm update expression',
						'name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsaz0123456789',
						'mappings'=> [
							['expression' => 'Update with long name']
						],
					]
				],
				[
					[
						'old_name' => 'Icon mapping two',
						'name' => 'Икона карты обновленна утф-8',
						'mappings'=> [
							['expression' => 'Выражение обновленно утф-8']
						],
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
	 * @dataProvider getUpdateData
	 *
	 * Test updating of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_Update($data) {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['old_name'], 'iconmap_name');

		$this->zbxTestInputTypeOverwrite('iconmap_name', $data['name']);

		// Input new row for Icon mapping
		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data);
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
			$expressions = [];

			foreach ($data['mappings'] as $options) {
				$expressions[] = $options['expression'];
			}

			$result = DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = ".zbx_dbstr($data['name']).' AND '
					. dbConditionString('icon_mapping.expression', $expressions));

			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $data['name']);
				foreach ($data['mappings'] as $mapping_row) {
					$this->assertEquals($row['expression'],$mapping_row['expression']);
				}
			}
		}

		// Check the results in form
		if (array_key_exists('formCheck', $data)) {
			$this->checkFormFields($data);
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

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function getCloneValidationData(){
		return [
				[
					[
						'new_name' => 'Icon mapping one',
						'expression' => 'Make Clone with existing name',
						'error' => 'Icon map "Icon mapping one" already exists.',
						'DbCheck' => true
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
						'new_name' => 'CLONE: Icon mapping one',
						'expression' => '\\',
						'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
					]
				],
				// Icon mapping update with double slash.
				[
					[
						'new_name' => 'CLONE: Icon mapping one',
						'expression' => '//',
						'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
					]
				],
				// Update with empty expression.
				[
					[
						'new_name' => 'CLONE: Icon mapping one',
						'expression' => '',
						'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
					]
				],
			];
	}

	/**
	 * @dataProvider getCloneValidationData
	 *
	 * Test cloning of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CloneValidation($data) {
		$name = 'Icon mapping one';
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

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function getCloneData(){
		return [
				[
					[
						'old_name' => 'Icon mapping to check clone functionality',
						'name' => 'CLONE: Икона карты обновленна утф-8',
						'dbCheck' => '4'
					]
				],
				[
					[
						'old_name' => 'Icon mapping to check clone functionality',
						'name' => 'Clone Icon mapping with expression update',
						'mappings'=> [
							['expression' => '!@#$%^&*()123updated']
						],
						'dbCheck' => '4'
					]
				],
				[
					[
						'old_name' => 'Icon mapping to check clone functionality',
						'name' => 'CLONE: Icon mapping testForm create',
						'mappings'=> [
							['expression' => 'Test expression updated']
						],
						'inventory' => 'Serial number B',
						'icon' => 'Firewall_(96)',
						'default' => 'Crypto-router_(96)',
						'dbCheck' => '4',
						'formCheck' => true
					]
				],
				[
					[
						'old_name' => 'Icon mapping to check clone functionality',
						'name' => 'LongNameqwertyuioplkjhg0123456789mqwertyuioplkjhgfdsazxcvbnmqwer',
						'mappings'=> [
							['expression' => 'Update with long name']
						],
						'dbCheck' => '4'
					]
				],
				[
					[
						'old_name' => 'Icon mapping to check clone functionality',
						'name' => 'Икона карты кирилица утф-8',
						'mappings'=> [
							['expression' => 'Выражение обновленно кирилица утф-8']
						],
						'inventory' => 'Name',
						'icon' => 'House_(48)',
						'default' => 'Hub_(24)',
						'dbCheck' => '4',
						'formCheck' => true
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
		$this->zbxTestClick('clone');
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('iconmap_name', $data['name']);
		}

		if (array_key_exists('mappings', $data)) {
			$this->addExpressionRows($data);
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
			$this->assertEquals($data['dbCheck'], DBcount("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = ".zbx_dbstr($data['name'])));
		}

		// Check the results in form
		if (array_key_exists('formCheck', $data)) {
			$this->checkFormFields($data);
		}
	}

	/**
	 * Test cancel cloning of icon mapping.
	 */
	public function testFormAdministrationGeneralIconMapping_CancelCloning(){
		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.iconmapping.php');

		foreach (DBdata("SELECT name FROM icon_map LIMIT 2", false) as $iconmap) {
			$iconmap = $iconmap[0];
			$this->zbxTestClickLinkText($iconmap['name']);
			$this->zbxTestInputTypeOverwrite('iconmap_name', $iconmap['name'].' (cloned)');
			$this->zbxTestClickWait('clone');
			$this->zbxTestClick('cancel');

			// Check the results in frontend.
			$this->zbxTestCheckTitle('Configuration of icon mapping');
			$this->zbxTestCheckHeader('Icon mapping');
			$this->zbxTestCheckFatalErrors();
			$this->zbxTestTextNotPresent($iconmap['name'].' (updated)');
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
		$sql = "SELECT * FROM icon_map WHERE name='$name'";
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

		$sql_hash = 'SELECT * FROM icon_map ORDER BY iconmapid where name='.zbx_dbstr($name);
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
		$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', $data['default']);
	}

	private function addExpressionRows($data) {
		$amount = count($data['mappings']);
		$expression_count = 0;

		foreach ($data['mappings'] as $mapping_row) {
			if (array_key_exists('new', $data)) {
				$this->zbxTestInputTypeWait('iconmap_mappings_new'.$expression_count.'_expression', $mapping_row['expression']);
			}
			else {
				$this->zbxTestInputType('iconmap_mappings_'.$expression_count.'_expression', $mapping_row['expression']);
			}

			if (array_key_exists('remove', $mapping_row)) {
				$this->zbxTestClickXpathWait("//tr[@id='iconmapidRow_new". $expression_count ."']//button");
			}

			$expression_count++;
			if ($amount == $expression_count) {
				break;
			}
			else {
				$this->zbxTestClick('addMapping');
			}
		}
	}
}
