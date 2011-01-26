<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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

class testPageInventory extends CWebTest
{
	// Returns all host inventories
	public static function allInventory()
	{
		return DBdata("select * from hosts_profiles order by hostid");
	}

	/**
	* @dataProvider allInventory
	*/
	public function testPageInventory_SimpleTest($inventory)
	{
		$hostid=$inventory['hostid'];
		$host=DBfetch(DBselect("select host from hosts where hostid=$hostid"));
		$name=$host['host'];

		$this->login('hostprofiles.php?prof_type=0');
		$this->assertTitle('Host profiles');
		$this->ok('Inventory');
		$this->ok('Hosts');
		$this->ok('Host profiles');
		$this->ok('HOST PROFILES');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
// Header
		$this->ok(array('Host','Group','Name','OS','SerialNo','Tag','MAC Address'));
		// Data
		$this->ok(array($name,$inventory['name'],$inventory['os'],$inventory['serialno'],$inventory['tag'],$inventory['macaddress']));
	}

	/**
	* @dataProvider allInventory
	*/
	public function testPageUserProfile_ViewProfile($inventory)
	{
		$hostid=$inventory['hostid'];
		$host=DBfetch(DBselect("select host from hosts where hostid=$hostid"));
		$name=$host['host'];

		$this->login('hostprofiles.php?prof_type=0');
		$this->assertTitle('Host profiles');
		$this->ok('Inventory');
		$this->ok('Hosts');
		$this->ok('Host profiles');
		$this->ok('HOST PROFILES');
		$this->ok('Displaying');
		$this->nok('Displaying 0');

		$this->click("link=$name");
		$this->wait();
		// Host name must be displayed
		// TODO
//		$this->ok($name);
		$this->ok(array($inventory['name'],$inventory['os'],$inventory['serialno'],$inventory['tag'],$inventory['macaddress']));
		$this->ok(array($inventory['hardware'],$inventory['software'],$inventory['contact'],$inventory['location'],$inventory['notes']));

		$this->button_click('cancel');
		$this->wait();

		$this->assertTitle('Host profiles');
		$this->ok($name);
		$this->ok('HOST PROFILES');
	}

	public function testPageUserProfile_Sorting()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
