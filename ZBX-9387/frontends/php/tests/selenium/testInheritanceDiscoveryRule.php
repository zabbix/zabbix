<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceDiscoveryRule extends CWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template  = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	public function testInheritanceDiscoveryRule_backup() {
		DBsave_tables('items');
	}

	// returns list of discovery rules from a template
	public static function update() {
		return DBdata(
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
		$oldHashDiscovery = DBhash($sqlDiscovery);

		$this->zbxTestLogin('host_discovery.php?form=update&itemid='.$data['itemid']);
		$this->zbxTestClickWait('save');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule updated');

		$this->assertEquals($oldHashDiscovery, DBhash($sqlDiscovery));

	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceDiscoveryRule6',
					'key' => 'discovery-rule-inheritance6'
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'testInheritanceDiscoveryRule5',
					'key' => 'discovery-rule-inheritance5',
					'errors' => array(
						'Discovery rule "discovery-rule-inheritance5" already exists on "Template inheritance test host", inherited from another template'
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceDiscoveryRule_SimpleCreate($data) {
		$this->zbxTestLogin('host_discovery.php?form=Create+discovery+rule&hostid='.$this->templateid);

		$this->input_type('name', $data['name']);
		$this->input_type('key', $data['key']);

		$this->zbxTestClickWait('save');
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of discovery rules');
				$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
				$this->zbxTestTextPresent('Discovery rule created');

				$itemId = 0;

				// template
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

				// host
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
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of discovery rules');
				$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
				$this->zbxTestTextPresent('ERROR: Cannot add discovery rule');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	public function testInheritanceDiscoveryRule_restore() {
		DBrestore_tables('items');
	}
}
