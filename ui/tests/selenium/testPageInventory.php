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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageInventory extends CLegacyWebTest {

	public static function allInventory() {
		return CDBHelper::getDataProvider(
			'SELECT hi.*,h.name AS hostname'.
			' FROM host_inventory hi,hosts h'.
			' WHERE hi.hostid=h.hostid AND NOT h.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE
		);
	}

	/**
	* @dataProvider allInventory
	*/
	public function testPageInventory_CheckLayout($data) {
		$this->zbxTestLogin('hostinventories.php');
		$this->query('button:Reset')->one()->click();

		$this->zbxTestCheckTitle('Host inventory');
		$this->zbxTestCheckHeader('Host inventory');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(
			['Host', 'Group', 'Name', 'Type', 'OS', 'Serial number A', 'Tag', 'MAC address A']
		);

		$this->zbxTestTextPresent([
			$data['hostname'],
			$data['name'],
			$data['type'],
			$data['os'],
			$data['serialno_a'],
			$data['tag'],
			$data['macaddress_a']
		]);
	}

	/**
	* @dataProvider allInventory
	*/
	public function testPageInventory_ViewInventory($data) {
		$this->zbxTestLogin('hostinventories.php?hostid='.$data['hostid']);
		$this->zbxTestCheckTitle('Host inventory');

		$this->zbxTestClick('tab_detailsTab');

		unset($data['hostid'], $data['hostname']);
		$this->zbxTestTextPresent($data);

		$this->zbxTestClickWait('cancel');

		$this->zbxTestCheckTitle('Host inventory');
		$this->zbxTestCheckHeader('Host inventory');
	}

}
