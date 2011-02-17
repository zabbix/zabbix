<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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

class testPageInventoryExtended extends CWebTest
{
	// Returns all extended inventories
	public static function allInventoryExtended()
	{
		return DBdata("select * from hosts_profiles_ext order by hostid");
	}

	/**
	* @dataProvider allInventoryExtended
	*/
	public function testPageInventoryExtended_SimpleTest($inventory)
	{
		$hostid=$inventory['hostid'];
		$host=DBfetch(DBselect("select host from hosts where hostid=$hostid"));
		$name=$host['host'];

		$this->login('hostprofiles.php?prof_type=1');
		$this->assertTitle('Host profiles');
		$this->ok('Inventory');
		$this->ok('Hosts');
		$this->ok('Host profiles');
		$this->ok('HOST PROFILES');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
// Header
		$this->ok(array('Host','Group','OS (Short)','HW Architecture','Device type','Device Deployment Status'));
		// Data
		$this->ok(array($name,$inventory['device_os_short'],$inventory['device_hw_arch'],$inventory['device_type'],$inventory['device_status']));
	}

	/**
	* @dataProvider allInventoryExtended
	*/
	public function testPageUserProfileExtended_ViewProfile($inventory)
	{
		$hostid=$inventory['hostid'];
		$host=DBfetch(DBselect("select host from hosts where hostid=$hostid"));
		$name=$host['host'];

		$this->login('hostprofiles.php?prof_type=1');
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
		$this->ok('Extended host profile');
		$this->ok(array($inventory['device_alias'],$inventory['device_type'],$inventory['device_chassis'],$inventory['device_os']));
		$this->ok(array($inventory['device_os_short'],$inventory['device_hw_arch'],$inventory['device_serial'],$inventory['device_model']));
		$this->ok(array($inventory['device_tag'],$inventory['device_vendor'],$inventory['device_contract'],$inventory['device_who']));
		$this->ok(array($inventory['device_status'],$inventory['device_app_01'],$inventory['device_app_02'],$inventory['device_app_03']));
		$this->ok(array($inventory['device_app_04'],$inventory['device_app_05'],$inventory['device_url_1'],$inventory['device_url_2']));
		$this->ok(array($inventory['device_url_3'],$inventory['device_networks'],$inventory['device_notes'],$inventory['device_hardware']));
		$this->ok(array($inventory['device_software'],$inventory['ip_subnet_mask'],$inventory['ip_router'],$inventory['ip_macaddress']));
		$this->ok(array($inventory['oob_ip'],$inventory['oob_subnet_mask'],$inventory['oob_router'],$inventory['date_hw_buy']));
		$this->ok(array($inventory['date_hw_install'],$inventory['date_hw_expiry'],$inventory['date_hw_decomm'],$inventory['site_street_1']));
		$this->ok(array($inventory['site_street_2'],$inventory['site_street_3'],$inventory['site_city'],$inventory['site_state']));
		$this->ok(array($inventory['site_country'],$inventory['site_zip'],$inventory['site_rack'],$inventory['site_notes']));
		$this->ok(array($inventory['poc_1_name'],$inventory['poc_1_email'],$inventory['poc_1_phone_1'],$inventory['poc_1_phone_2']));
		$this->ok(array($inventory['poc_1_cell'],$inventory['poc_1_screen'],$inventory['poc_1_notes'],$inventory['poc_2_name']));
		$this->ok(array($inventory['poc_2_email'],$inventory['poc_2_phone_1'],$inventory['poc_2_phone_2'],$inventory['poc_2_cell']));
		$this->ok(array($inventory['poc_2_screen'],$inventory['poc_2_notes']));

		$this->button_click('cancel');
		$this->wait();

		$this->assertTitle('Host profiles');
		$this->ok($name);
		$this->ok('HOST PROFILES');
	}

	public function testPageUserProfileExtended_Sorting()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
