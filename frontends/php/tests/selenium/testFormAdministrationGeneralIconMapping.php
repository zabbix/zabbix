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

class testFormIconMapping extends CWebTest {

	public function testFormIconMapping_CheckLayout() {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Icon mapping');

		$this->zbxTestAssertElementPresentId('iconmap_name');
		$this->zbxTestAssertAttribute("//input[@id='iconmap_name']", 'maxlength', 64);

		$this->zbxTestAssertElementPresentId('iconmap_mappings_new0_expression');
		$this->zbxTestAssertAttribute("//input[@id='iconmap_mappings_new0_expression']", 'maxlength', 64);

		$this->zbxTestDropdownHasOptions('iconmap_mappings_new0_inventory_link', ['Type', 'Type (Full details)', 'Name',
			'Alias', 'OS', 'OS (Full details)', 'OS (Short)', 'Serial number A', 'Serial number B', 'Tag', 'Asset tag',
			'MAC address A', 'MAC address B', 'Hardware', 'Hardware (Full details)', 'Software', 'Software (Full details)',
			'Software application A', 'Software application B', 'Software application C', 'Software application D', 'Software application E',
			'Contact', 'Location', 'Location latitude', 'Location longitude', 'Notes', 'Chassis', 'Model', 'HW architecture',
			'Vendor', 'Contract number', 'Installer name', 'Deployment status', 'URL A', 'URL B', 'URL C', 'Host networks',
			'Host subnet mask', 'Host router', 'OOB IP address', 'OOB subnet mask', 'OOB router', 'Date HW purchased',
			'Date HW installed', 'Date HW maintenance expires', 'Date HW decommissioned', 'Site address A', 'Site address B',
			'Site address C', 'Site city', 'Site state / province', 'Site country', 'Site ZIP / postal', 'Site rack location',
			'Site notes', 'Primary POC name', 'Primary POC email', 'Primary POC phone A', 'Primary POC phone B', 'Primary POC cell',
			'Primary POC screen name', 'Primary POC notes', 'Secondary POC name', 'Secondary POC email', 'Secondary POC phone A',
			'Secondary POC phone B', 'Secondary POC cell', 'Secondary POC screen name', 'Secondary POC notes']);
		$this->zbxTestDropdownHasOptions('iconmap_mappings_new0_iconid', ['Cloud_(24)', 'Cloud_(48)', 'Cloud_(64)',
			'Cloud_(96)', 'Cloud_(128)', 'Crypto-router_(24)', 'Crypto-router_symbol_(24)', 'IP_PBX_(24)', 'Video_terminal_(24)']);
		$this->zbxTestDropdownHasOptions('iconmap_default_iconid', ['Cloud_(24)', 'Cloud_(48)', 'Cloud_(64)',
			'Cloud_(96)', 'Cloud_(128)', 'Crypto-router_(24)', 'Crypto-router_symbol_(24)', 'IP_PBX_(24)', 'Video_terminal_(24)']);

		$this->zbxTestTextPresent(['Name', 'Mappings', 'Default']);
		$this->zbxTestTextPresent(['Inventory field', 'Expression', 'Icon', 'Action']);
	}

	public function CreateValidation() {
		return[
			[
				[
					'name' => ' ',
					'expression' => 'Create with empty name',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Icon mapping one for testPage',
					'expression' => 'Create with existing name',
					'error' => 'Icon map "Icon mapping one for testPage" already exists.',
					'NoDbCheck' => 'true'
				]
			],
			[
				[
					'name' => 'Icon mapping create with slash',
					'expression' => '/',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'name' => 'Icon mapping create with backslash',
					'expression' => '\\',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			[
				[
					'name' => 'Icon mapping create with double slash',
					'expression' => '//',
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
					'name' => 'Icon mapping without expressions',
					'mappings' => [
						['expression' => 'first expression', 'remove' => true]
					],
					'error' => 'Invalid parameter "/1/mappings": cannot be empty.'
				]
			],
		];
	}

	/**
	 * @dataProvider CreateValidation
	 * Test validate icon mapping creation.
	 */
	public function testFormIconMapping_CreateValidation($data) {
		$this->zbxTestLogin('adm.iconmapping.php?form=create');
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeWait('iconmap_name', $data['name']);
		}

		if (array_key_exists('expression', $data)) {
			$this->zbxTestInputTypeWait('iconmap_mappings_new0_expression', $data['expression']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_inventory_link', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_iconid', $data['icon']);
		}

		if (array_key_exists('default', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default']);
		}

		// Input new row for Icon mapping
		if (array_key_exists('mappings', $data)) {
			$expressionCount = 0;

			foreach ($data['mappings'] as $mappingRow) {
				$this->zbxTestInputTypeWait('iconmap_mappings_new'.$expressionCount.'_expression', $mappingRow['expression']);

				if (array_key_exists('remove', $mappingRow)) {
					$this->zbxTestClickXpathWait("//tr[@id='iconmapidRow_new". $expressionCount ."']//button");
				}

				$expressionCount++;
				if (count($data['mappings']) == $expressionCount) {
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

	public function Create() {
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
					'expression' => 'Create with long name',
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
	 * @dataProvider Create
	 * Test creation of icon mapping.
	 */
	public function testFormIconMapping_Create($data) {
		$this->zbxTestLogin('adm.iconmapping.php?form=create');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeWait('iconmap_name', $data['name']);
		}

		if (array_key_exists('expression', $data)) {
			$this->zbxTestInputTypeWait('iconmap_mappings_new0_expression', $data['expression']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_inventory_link', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_iconid', $data['icon']);
		}

		if (array_key_exists('default', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default']);
		}

		// Input new row for Icon mapping
		if (array_key_exists('mappings', $data)) {
			$expressionCount = 0;

			foreach ($data['mappings'] as $mappingRow) {
				$this->zbxTestInputTypeWait('iconmap_mappings_new'.$expressionCount.'_expression', $mappingRow['expression']);

				if (array_key_exists('remove', $mappingRow)) {
					$this->zbxTestClickXpathWait("//tr[@id='iconmapidRow_new". $expressionCount ."']//button");
				}

				$expressionCount++;
				if (count($data['mappings']) == $expressionCount) {
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

		// Check the results in DB
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
	public function testFormIconMapping_CancelCreation(){
		$this->zbxTestLogin('adm.iconmapping.php?form=create');
		$cancel_name='Cancel Icon mapping creation';
		$cancel_expression='Cancel Icon mapping creation';
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
	 * Test update icon mapping without changes.
	 */
	public function testFormIconMapping_SimpleUpdate(){
		$this->zbxTestLogin('adm.iconmapping.php');
		$name='Icon mapping two for testPage';
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$before_update = DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$name."'");
		$this->zbxTestClick('update');
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$after_update = DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$name."'");
		$this->assertEquals($after_update, $before_update);
	}

	public function UpdateValidation(){
		return [
			[
				[
					'name' => 'Icon mapping two for testPage',
					'new_name' => '',
					'expression' => 'Update with empty name',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Icon mapping one for testPage',
					'new_name' => 'Icon mapping two for testPage',
					'expression' => 'Update with existing name',
					'error' => 'Icon map "Icon mapping two for testPage" already exists.'
				]
			],
			[
				[
					'comment' => 'Icon mapping update expression with slash',
					'name' => 'Icon mapping two for testPage',
					'expression' => '/',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'comment' => 'Icon mapping update with backslash',
					'name' => 'Icon mapping two for testPage',
					'expression' => '\\',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			[
				[
					'comment' => 'Icon mapping update with double slash',
					'name' => 'Icon mapping two for testPage',
					'expression' => '//',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'comment' => 'Update with empty expression',
					'name' => 'Icon mapping two for testPage',
					'expression' => '',
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider UpdateValidation
	 * Test validate icon mapping updating
	 */
	public function testFormIconMapping_ValidateChangeAndUpdate($data) {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['name'], 'iconmap_name');
		$before_update=DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$data['name']."'");
		if (array_key_exists('new_name', $data)) {
			$this->zbxTestInputType('iconmap_name', $data['new_name']);
		}

		if (array_key_exists('expression', $data)) {
			$this->zbxTestInputType('iconmap_mappings_0_expression', $data['expression']);
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
		$after_update=DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$data['name']."'");

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot update icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		$before_update = $after_update;
	}

	public function update(){
		return [
			[
				[
					'name' => 'Icon mapping testForm update expression',
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
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Icon mapping one for testPage'
				]
			],

		];
	}

	/**
	 * @dataProvider update
	 * Test updating of icon mapping.
	 */
	public function testFormIconMapping_ChangeAndUpdate($data) {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['name'], 'iconmap_name');

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

		$this->zbxTestClick('update');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map updated');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		if (array_key_exists('dbCheck', $data)) {
			$result = DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$data['name']."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $data['name']);
				$dbExpression[] = $row['expression'];
			}

		}
		// Check the results in form
		if (array_key_exists('formCheck', $data)) {
			if (array_key_exists('new_name', $data)) {
				$this->zbxTestClickLinkText($data['new_name'], 'iconmap_name');
				$this->zbxTestAssertElementValue('iconmap_name', $data['new_name']);
			}
			else {
				$this->zbxTestClickLinkText($data['name'], 'iconmap_name');
				$this->zbxTestAssertElementValue('iconmap_name', $data['name']);
			}

			$this->zbxTestAssertElementValue('iconmap_mappings_0_expression', $data['expression']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][inventory_link]', $data['inventory']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][iconid]', $data['icon']);
			$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', $data['default']);
		}
	}

	/**
	 * Test cancel creation of icon mapping.
	 */
	public function testFormIconMapping_CancelUpdating(){
		$this->zbxTestLogin('adm.iconmapping.php');
		$name='Icon mapping one for testPage';
		$new_name='Cancel Icon mapping updating';
		$expression='Cancel Icon mapping updating';
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestInputTypeOverwrite('iconmap_name', $new_name);
		$this->zbxTestInputTypeOverwrite('iconmap_mappings_0_expression', $expression);
		$this->zbxTestClick('cancel');
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestTextNotPresent($new_name);
		$this->assertEquals(0, DBcount("SELECT NULL FROM icon_map WHERE name='".$new_name."'"));
	}

	public function ValidateCloning(){
		return [
			[
				[
					'name' => 'Icon mapping one for testPage',
					'new_name' => 'Icon mapping one for testPage',
					'expression' => 'Make Clone with existing name',
					'error' => 'Icon map "Icon mapping one for testPage" already exists.',
					'NoDbCheck' => 'true'
				]
			],
			[
				[
					'name' => 'Icon mapping one for testPage',
					'new_name' => '',
					'expression' => 'Clone with empty name',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Icon mapping one for testPage',
					'new_name' => 'CLONE: Icon mapping update expression with slash',
					'expression' => '/',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'comment' => 'Icon mapping update with backslash',
					'name' => 'Icon mapping one for testPage',
					'new_name' => 'CLONE: Icon mapping one for testPage',
					'expression' => '\\',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			[
				[
					'comment' => 'Icon mapping update with double slash',
					'name' => 'Icon mapping one for testPage',
					'new_name' => 'CLONE: Icon mapping one for testPage',
					'expression' => '//',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'comment' => 'Update with empty expression',
					'name' => 'Icon mapping one for testPage',
					'new_name' => 'CLONE: Icon mapping one for testPage',
					'expression' => '',
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
				]
			],
		];
	}

	/**
	 * @dataProvider ValidateCloning
	 * Test cloning of icon mapping.
	 */
	public function testFormIconMapping_ValidateClone($data) {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['name'], 'iconmap_name');
		$this->zbxTestClick('clone');

		if (array_key_exists('new_name', $data)) {
			$this->zbxTestInputType('iconmap_name', $data['new_name']);
		}

		if (array_key_exists('expression', $data)) {
			$this->zbxTestInputType('iconmap_mappings_0_expression', $data['expression']);
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
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot create icon map');
		$this->zbxTestTextPresent($data['error']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB
		if (!array_key_exists('NoDbCheck', $data)) {
			$this->assertEquals(0, DBcount("SELECT NULL FROM icon_map WHERE name='".$data['new_name']."'"));
		}
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
			],
		];
	}

	/**
	 * @dataProvider cloning
	 * Test cloning of icon mapping.
	 */
	public function testFormIconMapping_Clone($data) {
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
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$data['name']."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $data['name']);
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
	public function testFormIconMapping_CancelCloning(){
		$this->zbxTestLogin('adm.iconmapping.php');
		$name='Icon mapping one for testPage';
		$new_name='CANCEL CLONING: Icon mapping one for testPage';
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
	public function testFormIconMapping_Delete() {
		$this->zbxTestLogin('adm.iconmapping.php');
		$name='Icon mapping to check delete functionality';
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClickWait('delete');
		$this->webDriver->switchTo()->alert()->accept();

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map deleted');

		// Check the results in DB.
		$sql = "SELECT * FROM icon_map WHERE name='$name'";
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Test deleting of icon mapping.
	 */
	public function testFormIconMapping_CancelDelete() {
		$this->zbxTestLogin('adm.iconmapping.php');
		$name='Икона карты утф-8';
		$this->zbxTestClickLinkTextWait($name, 'iconmap_name');
		$this->zbxTestClick('delete');
		$this->webDriver->switchTo()->alert()->dismiss();

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestTextNotPresent($name);

		// Check the results in DB
		$this->assertEquals(1, DBcount("SELECT NULL FROM icon_map WHERE name='".$name."'"));
	}
}
