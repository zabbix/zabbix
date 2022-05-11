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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items
 */
class testInheritanceDiscoveryRule extends CLegacyWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template  = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	// Returns list of discovery rules from a template.
	public static function update() {
		return CDBHelper::getDataProvider(
			'SELECT itemid'.
			' FROM items'.
			' WHERE hostid=15000'.	//	$this->templateid.
				' AND flags=1'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceDiscoveryRule_SimpleUpdate($data) {
		$sqlDiscovery = 'SELECT * FROM items ORDER BY itemid';
		$oldHashDiscovery = CDBHelper::getHash($sqlDiscovery);

		$this->zbxTestLogin('host_discovery.php?form=update&context=host&itemid='.$data['itemid']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule updated');

		$this->assertEquals($oldHashDiscovery, CDBHelper::getHash($sqlDiscovery));

	}

	// Returns create data.
	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceDiscoveryRule6',
					'key' => 'discovery-rule-inheritance6'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'testInheritanceDiscoveryRule5',
					'key' => 'discovery-rule-inheritance5',
					'errors' => [
						'Discovery rule "discovery-rule-inheritance5" already exists on "Template inheritance test host", inherited from another template'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceDiscoveryRuleWithLLDMacros',
					'key' => 'discovery-rule-inheritance-with-macros',
					'macros' => [
						['macro' => '{#MACRO1}', 'path'=>'$.path.1'],
						['macro' => '{#MACRO2}', 'path'=>'$.path.1']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceDiscoveryRule_SimpleCreate($data) {
		$this->zbxTestLogin('host_discovery.php?form=Create+discovery+rule&context=template&hostid='.$this->templateid);

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestInputType('key', $data['key']);

		if (array_key_exists('macros', $data)) {
			$this->zbxTestTabSwitch('LLD macros');
			$last = count($data['macros']) - 1;

			foreach ($data['macros'] as $i => $lld_macro) {
				$this->zbxTestInputType('lld_macro_paths_'.$i.'_lld_macro', $lld_macro['macro'] );
				$this->zbxTestInputType('lld_macro_paths_'.$i.'_path', $lld_macro['path'] );
				if ($i !== $last) {
					$this->zbxTestClick('lld_macro_add');
				}
			}
		}

		$this->zbxTestClickWait('add');
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of discovery rules');
				$this->zbxTestCheckHeader('Discovery rules');
				$this->zbxTestTextPresent('Discovery rule created');

				$itemId = 0;

				// Template DB check.
				$dbResult = DBselect(
					'SELECT itemid,name,templateid'.
					' FROM items'.
					' WHERE hostid='.$this->templateid.
						' AND key_='.zbx_dbstr($data['key']).
						' AND flags=1'
				);
				if ($dbRow = DBfetch($dbResult)) {
					$itemId = $dbRow['itemid'];
					$this->assertEquals($dbRow['name'], $data['name']);
					$this->assertEquals($dbRow['templateid'], 0);
				}

				$this->assertNotEquals($itemId, 0);

				// Host DB check.
				$dbResult = DBselect(
					'SELECT key_,name'.
					' FROM items'.
					' WHERE hostid='.$this->hostid.
						' AND templateid='.$itemId.
						' AND flags=1'
				);
				if ($dbRow = DBfetch($dbResult)) {
					$this->assertEquals($dbRow['key_'], $data['key']);
					$this->assertEquals($dbRow['name'], $data['name']);
				}

				// Host form check.
				$this->zbxTestLogin('host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$this->hostid);
				$this->zbxTestClickLinkText($data['name']);
				$this->zbxTestWaitForPageToLoad();
				$this->zbxTestAssertElementPresentXpath('//input[@id="name"][@value="'.$data['name'].'"][@readonly]');
				$this->zbxTestAssertElementPresentXpath('//input[@id="key"][@value="'.$data['key'].'"][@readonly]');
				if (array_key_exists('macros', $data)) {
					$this->zbxTestTabSwitch('LLD macros');
					foreach ($data['macros'] as $i => $lld_macro) {
						$this->zbxTestAssertElementPresentXpath('//textarea[@id="lld_macro_paths_'.$i.'_lld_macro"][text()="'.$lld_macro['macro'].'"][@readonly]');
						$this->zbxTestAssertElementPresentXpath('//textarea[@id="lld_macro_paths_'.$i.'_path"][text()="'.$lld_macro['path'].'"][@readonly]');
					}
				}
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of discovery rules');
				$this->zbxTestCheckHeader('Discovery rules');
				$this->zbxTestTextPresent('Cannot add discovery rule');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}
}
