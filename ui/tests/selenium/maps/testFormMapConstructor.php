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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

/**
 * @dataSource Maps
 *
 * @backup sysmaps, globalmacro
 *
 * @onBefore prepareMapForMacrofunctions
 */

class testFormMapConstructor extends CLegacyWebTest {

	protected static $macro_map_id;
	const MAP_NAME = 'Map for form testing';
	const MAP_MACRO_FUNCTIONS = 'Map for testing macro functions';
	const HOST_MACRO_FUNCTIONS = 'Testing macro functions 12345';
	const ITEM_KEY = 'trapmacrofunctions';
	const ITEM_NAME = 'Item for testing macro functions with expression macros';

	public function prepareMapForMacrofunctions() {

		$host = CDataHelper::createHosts([
			[
				'host' => self::HOST_MACRO_FUNCTIONS,
				'groups' => ['groupid' => 4], // Zabbix servers.
				'items' => [
					[
						'name' => self::ITEM_NAME,
						'key_' => self::ITEM_KEY,
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			]
		]);

		$item_id = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.zbx_dbstr(self::ITEM_NAME));

		// Add value to item history table, to use expression macros.
		CDataHelper::addItemData($item_id, 123.33);

		$triggers = CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger testing macro functions',
				'expression' => 'last(/'.self::HOST_MACRO_FUNCTIONS.'/'.self::ITEM_KEY.')=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Trigger for testing incorrectly used macro functions',
				'expression' => 'last(/'.self::HOST_MACRO_FUNCTIONS.'/'.self::ITEM_KEY.')=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			]
		]);

		CDataHelper::call('map.create', [
			[
				'name' => self::MAP_MACRO_FUNCTIONS,
				'width' => 800,
				'height' => 1000,
				'expand_macros' => SYSMAP_EXPAND_MACROS_ON,
				'label_type' => MAP_LABEL_TYPE_LABEL,
				'selements' => [
					// For testing the built-in macros with macro functions.
					[
						'selementid' => 1,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 182,
						'label' => '{{HOST.HOST}.btoa()}, {{HOST.HOST}.htmldecode()}, {{HOST.HOST}.htmlencode()}',
						'x' => 351,
						'y' => 101,
						'elements' => [['hostid' => $host['hostids'][self::HOST_MACRO_FUNCTIONS]]]
					],
					[
						'selementid' => 1,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 182,
						'label' => '{{HOST.HOST}.lowercase()}, {{HOST.HOST}.uppercase()}, '.
								'{{HOST.HOST}.regrepl("([^a-z])", 0)}, {{HOST.HOST}.regsub(1, test)}',
						'x' => 351,
						'y' => 201,
						'elements' => [['hostid' => $host['hostids'][self::HOST_MACRO_FUNCTIONS]]]
					],
					[
						'selementid' => 1,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 182,
						'label' => '{{HOST.HOST}.tr(0-9abcA-L,*)}, {{HOST.HOST}.urlencode()}, {{HOST.HOST}.urldecode()}, '.
								'{{HOST.HOST}.iregsub(1, test)}',
						'x' => 351,
						'y' => 301,
						'elements' => [['hostid' => $host['hostids'][self::HOST_MACRO_FUNCTIONS]]]
					],
					// For testing the expression macros with macro functions.
					[
						'selementid' => 2,
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'iconid_off' => 152,
						'label' => '{{?last(//'.self::ITEM_KEY.')}.btoa()}, {{?last(//'.self::ITEM_KEY.')}.fmtnum(0)}, '.
								'{{?last(//'.self::ITEM_KEY.')}.htmldecode()}, {{?last(//'.self::ITEM_KEY.')}.htmlencode()}, '.
								'{{?last(//'.self::ITEM_KEY.')}.iregsub(2, test)}, {{?last(//'.self::ITEM_KEY.')}.lowercase()}',
						'x' => 351,
						'y' => 401,
						'elements' => [['triggerid' => $triggers['triggerids'][0]]]
					],
					[
						'selementid' => 2,
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'iconid_off' => 152,
						'label' => '{{?last(//'.self::ITEM_KEY.')}.uppercase()}, '.
								'{{?last(//'.self::ITEM_KEY.')}.regrepl([0-9], A)}, '.
								'{{?last(//'.self::ITEM_KEY.')}.regsub(2, test)}, '.
								'{{?last(//'.self::ITEM_KEY.')}.tr(0-9,a-z)}, {{?last(//'.self::ITEM_KEY.')}.urldecode()}, '.
								'{{?last(//'.self::ITEM_KEY.')}.urlencode()}',
						'x' => 351,
						'y' => 501,
						'elements' => [['triggerid' => $triggers['triggerids'][0]]]
					],
					// For testing incorrectly used arguments of macro functions.
					[
						'selementid' => 2,
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'iconid_off' => 152,
						'label' => '{{?last(//'.self::ITEM_KEY.')}.btoa(\)}, {{HOST.HOST}.htmldecode(/)}, '.
								'{{?last(//'.self::ITEM_KEY.')}.fmtnum()}, {{HOST.HOST}.htmlencode(test)}, '.
								'{{HOST.HOST}.fmttime()}, {{HOST.HOST}.iregsub(a-z)}',
						'x' => 351,
						'y' => 601,
						'elements' => [['triggerid' => $triggers['triggerids'][1]]]
					],
					[
						'selementid' => 2,
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'iconid_off' => 152,
						'label' => '{{?last(//'.self::ITEM_KEY.')}.regsub()}, {{HOST.HOST}.lowercase(///)}, '.
								'{{HOST.HOST}.uppercase(//\\)}, {{HOST.HOST}.regrepl(1, 2, 3)}, '.
								'{{?last(//'.self::ITEM_KEY.')}.tr()}, {{HOST.HOST}.urldecode(1, 2)}, '.
								'{{?last(//'.self::ITEM_KEY.')}.urlencode(\)}',
						'x' => 351,
						'y' => 701,
						'elements' => [['triggerid' => $triggers['triggerids'][1]]]
					],
					// Check that regsub and iregsub functions working correctly without match.
					[
						'selementid' => 2,
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'iconid_off' => 152,
						'label' => '{{?last(//'.self::ITEM_KEY.')}.regsub(0, test)}, {{HOST.HOST}.regsub(0, test)}, '.
								'{{HOST.HOST}.iregsub(0, test)}, {{?last(//'.self::ITEM_KEY.')}.iregsub(0, test)}',
						'x' => 351,
						'y' => 801,
						'elements' => [['triggerid' => $triggers['triggerids'][1]]]
					]
				]
			]
		]);

		self::$macro_map_id = CDBHelper::getValue('SELECT sysmapid FROM sysmaps WHERE name='.zbx_dbstr(self::MAP_MACRO_FUNCTIONS));
	}

	/**
	 * Possible combinations of grid settings
	 * @return array
	 */
	public function possibleGridOptions() {
		return [
			// Array value description: grid dimensions, show grid, auto align.
			['20x20', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_ON],
			['40x40', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_ON],
			['50x50', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_ON],
			['75x75', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_ON],
			['100x100', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_ON],

			['20x20', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_OFF],
			['40x40', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_OFF],
			['50x50', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_OFF],
			['75x75', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_OFF],
			['100x100', SYSMAP_GRID_SHOW_ON, SYSMAP_GRID_ALIGN_OFF],

			['20x20', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_ON],
			['40x40', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_ON],
			['50x50', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_ON],
			['75x75', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_ON],
			['100x100', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_ON],

			['20x20', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_OFF],
			['40x40', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_OFF],
			['50x50', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_OFF],
			['75x75', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_OFF],
			['100x100', SYSMAP_GRID_SHOW_OFF, SYSMAP_GRID_ALIGN_OFF]
		];
	}

	public static function getSimpleUpdateData() {
		return [
			[['name' => 'Local network']],
			[['name' => 'Map for form testing']],
			[['name' => 'Map with icon mapping']],
			[['name' => 'Map with links']],
			[['name' => 'Public map with image']],
			[['name' => 'Test map for Properties']]
		];
	}

	/**
	 * @dataProvider getSimpleUpdateData
	 *
	 * @browsers chrome
	 */
	public function testFormMapConstructor_SimpleUpdateConstructor($map) {
		$sql_maps_elements = 'SELECT * FROM sysmaps sm INNER JOIN sysmaps_elements sme ON'.
				' sme.sysmapid = sm.sysmapid ORDER BY sme.selementid';
		$sql_links_triggers = 'SELECT * FROM sysmaps_links sl INNER JOIN sysmaps_link_triggers slt ON'.
				' slt.linkid = sl.linkid ORDER BY slt.linktriggerid';
		$hash_maps_elements = CDBHelper::getHash($sql_maps_elements);
		$hash_links_triggers = CDBHelper::getHash($sql_links_triggers);

		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$this->query('link', $map['name'])->one()->click();
		$this->page->waitUntilReady();

		$element = $this->query('xpath://div[@id="flickerfreescreen_mapimg"]/div/*[name()="svg"]')
				->waitUntilPresent()->one();

		$exclude = [
			'query'	=> 'class:map-timestamp',
			'color'	=> '#ffffff'
		];
		$this->assertScreenshotExcept($element, $exclude, 'view_'.$map['name']);

		$this->query('button:Edit map')->one()->click();
		$this->page->waitUntilReady();
		$this->assertScreenshot($this->query('id:map-area')->waitUntilPresent()->one(), 'edit_'.$map['name']);
		$this->query('button:Update')->one()->click();

		$this->page->waitUntilAlertIsPresent();
		$this->assertEquals('Map is updated! Return to map list?', $this->page->getAlertText());
		$this->page->acceptAlert();

		$this->page->waitUntilReady();
		$this->assertStringContainsString('sysmaps.php', $this->page->getCurrentUrl());

		$hash_data = [
			$hash_maps_elements => $sql_maps_elements,
			$hash_links_triggers => $sql_links_triggers
		];

		foreach ($hash_data as $old => $new) {
			$this->assertEquals($old, CDBHelper::getHash($new));
		}
	}

	/**
	 * Test setting of grid options for map
	 *
	 * @dataProvider possibleGridOptions
	 */
	public function testFormMapConstructor_UpdateGridOptions($gridSize, $showGrid, $autoAlign) {

		$map_name = self::MAP_NAME;

		// getting map options from DB as they are at the beginning of the test
		$db_map = CDBHelper::getRow('SELECT * FROM sysmaps WHERE name='.zbx_dbstr($map_name));
		$this->assertTrue(isset($db_map['sysmapid']));

		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestClickLinkTextWait($map_name);
		$this->zbxTestContentControlButtonClickTextWait('Edit map');

		// checking if appropriate value for grid size is selected
		$this->assertTrue($this->zbxTestGetValue('//z-select[@id="gridsize"]') == $db_map['grid_size']);

		// grid should be shown by default
		if ($db_map['grid_show'] == SYSMAP_GRID_SHOW_ON) {
			$this->zbxTestTextPresent('Shown');
			$this->zbxTestTextNotPresent('Hidden');
		}
		else {
			$this->zbxTestTextPresent('Hidden');
			$this->zbxTestTextNotPresent('Shown');
		}

		// auto align should be on by default
		if ($db_map['grid_align'] == SYSMAP_GRID_ALIGN_ON) {
			$this->zbxTestAssertElementText("//button[@id='gridautoalign']", 'On');
		}
		else {
			$this->zbxTestAssertElementText("//button[@id='gridautoalign']", 'Off');
		}

		// selecting new grid size
		$this->zbxTestDropdownSelect('gridsize', $gridSize);
		$this->zbxTestAssertElementValue('gridsize', strstr($gridSize, 'x', true));

		// changing other two options if they are not already set as needed
		if (($db_map['grid_show'] == SYSMAP_GRID_SHOW_ON && $showGrid == 0) || ($db_map['grid_show'] == SYSMAP_GRID_SHOW_OFF && $showGrid == 1)) {
			$this->zbxTestClickWait('gridshow');
		}
		if (($db_map['grid_align'] == SYSMAP_GRID_ALIGN_ON && $autoAlign == 0) || ($db_map['grid_align'] == SYSMAP_GRID_ALIGN_OFF && $autoAlign == 1)) {
			$this->zbxTestClickWait('gridautoalign');
		}

		$this->zbxTestClickAndAcceptAlert('sysmap_update');

		$db_map = CDBHelper::getRow('SELECT * FROM sysmaps WHERE name='.zbx_dbstr($map_name));
		$this->assertTrue(isset($db_map['sysmapid']));

		$this->assertTrue(
			$db_map['grid_size'] == substr($gridSize, 0, strpos($gridSize, 'x'))
			&& $db_map['grid_show'] == $showGrid
			&& $db_map['grid_align'] == $autoAlign
		);

		$this->zbxTestClickLinkTextWait($map_name);
		$this->zbxTestContentControlButtonClickTextWait('Edit map');

		// checking if all options remain as they were set
		$this->zbxTestDropdownAssertSelected('gridsize', $gridSize);

		if ($showGrid) {
			$this->zbxTestTextPresent('Shown');
			$this->zbxTestTextNotPresent('Hidden');
		}
		else {
			$this->zbxTestTextPresent('Hidden');
			$this->zbxTestTextNotPresent('Shown');
		}

		if ($autoAlign) {
			$this->zbxTestAssertElementText("//button[@id='gridautoalign']", 'On');
		}
		else {
			$this->zbxTestAssertElementText("//button[@id='gridautoalign']", 'Off');
		}
	}

	/**
	 * Check that macro functions are resolved for map element labels.
	 */
	public function testFormMapConstructor_MacroFunctions() {
		$this->page->login()->open('sysmap.php?sysmapid='.self::$macro_map_id)->waitUntilReady();

		$objects = [
			[
				'label' => 'VGVzdGluZyBtYWNybyBmdW5jdGlvbnMgMTIzNDU=, Testing macro functions 12345, '.
					'Testing macro functions 12345',
				'id' => 2
			],
			[
				'label' => 'testing macro functions 12345, TESTING MACRO FUNCTIONS 12345, '.
					'0esting0macro0functions000000, test',
				'id' => 4
			],
			[
				'label' => 'Testing m**ro fun*tions *****, Testing%20macro%20functions%2012345, '.
					'Testing macro functions 12345, test',
				'id' => 6
			],
			[
				'label' => 'MTIzLjMz, 123, 123.33, 123.33, test, 123.33',
				'id' => 8
			],
			[
				'label' => '123.33, AAA.AA, test, bcd.dd, 123.33, 123.33',
				'id' => 10
			],
			[
				'label' => '*UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*',
				'id' => 12
			],
			[
				'label' => '*UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*',
				'id' => 14
			]
			// TODO: Uncomment and check the test case, after ZBX-25420 fix.
//			[
//				'label' => ', , ,',
//				'id' => 16
//			]
		];

		foreach ($objects as $object) {
			$this->assertEquals($object['label'], $this->query('xpath://*[@id="map-area"]/*[1]/*[2]/*[7]/*['.$object['id'].']')
					->waitUntilVisible()->one()->getText(), 'Object expected label does not match, id='.$object['id']
			);
		}
	}

	/**
	 * Check the screenshot of the trigger container in trigger map element.
	 */
	public function testFormMapConstructor_MapElementScreenshot() {
		// Open map in edit mode.
		$this->page->login()->open('sysmap.php?sysmapid='.CDataHelper::get('Maps.form_test_mapid'))->waitUntilReady();

		// Click on map element 'Trigger for map' (in prepareMapData this trigger has icon id = 146).
		$this->query('xpath://div[contains(@class, "sysmap_iconid_146")]')->waitUntilVisible()->one()->click();

		$form = $this->query('id:map-window')->asForm()->one()->waitUntilVisible();
		$form->getField('New triggers')->selectMultiple([
				'First test trigger with tag priority',
				'Fourth test trigger with tag priority',
				'Linux: Lack of available memory'
			], 'ЗАББИКС Сервер'
		);
		$form->query('button:Add')->one()->click();

		// Take a screenshot to test draggable object position for triggers of trigger type map element.
		$this->page->removeFocus();
		$this->assertScreenshot($this->query('id:triggerContainer')->waitUntilVisible()->one(), 'Map element');
	}
}
