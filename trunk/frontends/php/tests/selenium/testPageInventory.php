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

class testPageInventory extends CWebTest {

	public static function allInventory() {
		return DBdata(
			'SELECT hi.*,h.name AS hostname'.
			' FROM host_inventory hi,hosts h'.
			' WHERE hi.hostid=h.hostid'
		);
	}

	/**
	* @dataProvider allInventory
	*/
	public function testPageInventory_CheckLayout($data) {
		$this->zbxTestLogin('hostinventories.php');

		$this->zbxTestDropdownSelectWait('groupid', 'all');

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
			$data['macaddress_a'],
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
