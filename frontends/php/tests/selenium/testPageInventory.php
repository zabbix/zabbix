<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testPageInventory extends CWebTest{
	// Returns all host inventories
	public static function allInventory(){
		return DBdata("select * from host_profile order by hostid");
	}

	/**
	* @dataProvider allInventory
	*/
	public function testPageInventory_SimpleTest($inventory){
		$hostid = $inventory['hostid'];
		$host = DBfetch(DBselect("select name from hosts where hostid=$hostid"));
		$name = $host['name'];

		$this->login('hostprofiles.php');

		$this->dropdown_select_wait('groupid','all');

		$this->assertTitle('Host profiles');
		$this->ok('HOST PROFILES');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
// Header
		$this->ok(array('Host', 'Group', 'Name', 'Type', 'OS', 'Serial number A', 'Tag', 'MAC address A'));

// Data
		$this->ok(array(
			$name,
			$inventory['name'],
			$inventory['type'],
			$inventory['os'],
			$inventory['serialno_a'],
			$inventory['tag'],
			$inventory['macaddress_a'],
		));
	}

	/**
	* @dataProvider allInventory
	*/
	public function testPageHostProfile_ViewProfile($inventory){
		$this->login('hostprofiles.php?hostid='.$inventory['hostid']);
		$this->assertTitle('Host profiles');

		unset($inventory['hostid']);
		$this->ok($inventory);

		$this->button_click('cancel');
		$this->wait();

		$this->assertTitle('Host profiles');
		$this->ok('HOST PROFILES');
	}

	public function testPageHostProfile_Sorting(){
// TODO
		$this->markTestIncomplete();
	}
}
?>
