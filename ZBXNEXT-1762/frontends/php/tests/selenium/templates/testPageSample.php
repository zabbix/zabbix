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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageHosts extends CWebTest {
	// Returns all hosts
	public static function allHosts() {
		return DBdata('select * from hosts where status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')');
	}

	/**
	* Test that all required elements present on the page
	* @dataProvider allHosts
	*/
	public function testPageHosts_CheckLayout($host) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestTextPresent('HOSTS');
		$this->zbxTestTextPresent('Displaying');

		// header
		$this->zbxTestTextPresent(['Name', 'Applications', 'Items', 'Triggers', 'Graphs', 'Discovery', 'Interface', 'Templates', 'Status', 'Availability']);

		// data
		$this->zbxTestTextPresent([$host['name']]);
		$this->zbxTestDropdownHasOptions('action',
				['Export selected', 'Mass update', 'Activate selected', 'Disable selected', 'Delete selected']);
	}

	/**
	* Test that after update object properties remains exactly the same in the database
	* @dataProvider allHosts
	*/
	public function testPageHosts_SimpleUpdate($host) {
		$hostid = $host['hostid'];
		$name = $host['name'];

		$sql1 = "select * from hosts where hostid=$hostid";
		$oldHashHosts = DBhash($sql1);
		$sql2 = "select * from items where hostid=$hostid order by itemid";
		$oldHashItems = DBhash($sql2);
		$sql3 = "select * from applications where hostid=$hostid order by applicationid";
		$oldHashApplications = DBhash($sql3);
		$sql4 = "select * from interface where hostid=$hostid order by interfaceid";
		$oldHashInterface = DBhash($sql4);
		$sql5 = "select * from hostmacro where hostid=$hostid order by hostmacroid";
		$oldHashHostmacro = DBhash($sql5);
		$sql6 = "select * from hosts_groups where hostid=$hostid order by hostgroupid";
		$oldHashHostsgroups = DBhash($sql6);
		$sql7 = "select * from hosts_templates where hostid=$hostid order by hosttemplateid";
		$oldHashHoststemplates = DBhash($sql7);
		$sql8 = "select * from maintenances_hosts where hostid=$hostid order by maintenance_hostid";
		$oldHashMaintenanceshosts = DBhash($sql8);
		$sql9 = "select * from host_inventory where hostid=$hostid";
		$oldHashHostinventory = DBhash($sql9);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestTextPresent('HOSTS');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextNotPresent('Displaying 0');
		// Header
		$this->zbxTestTextPresent(['Name', 'Applications', 'Items', 'Triggers', 'Graphs', 'Discovery', 'Interface', 'Templates', 'Status', 'Availability']);

		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestTextPresent('Host updated');

		$this->assertEquals($oldHashHosts, DBhash($sql1), "Chuck Norris: Host update changed data in table 'hosts'");
		$this->assertEquals($oldHashItems, DBhash($sql2), "Chuck Norris: Host update changed data in table 'items'");
		$this->assertEquals($oldHashApplications, DBhash($sql3), "Chuck Norris: Host update changed data in table 'applications'");
		$this->assertEquals($oldHashInterface, DBhash($sql4), "Chuck Norris: Host update changed data in table 'interface'");
		$this->assertEquals($oldHashHostmacro, DBhash($sql5), "Chuck Norris: Host update changed data in table 'host_macro'");
		$this->assertEquals($oldHashHostsgroups, DBhash($sql6), "Chuck Norris: Host update changed data in table 'hosts_groups'");
		$this->assertEquals($oldHashHoststemplates, DBhash($sql7), "Chuck Norris: Host update changed data in table 'hosts_templates'");
		$this->assertEquals($oldHashMaintenanceshosts, DBhash($sql8), "Chuck Norris: Host update changed data in table 'maintenances_hosts'");
		$this->assertEquals($oldHashHostinventory, DBhash($sql9), "Chuck Norris: Host update changed data in table 'host_inventory'");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_FilterHost($host) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClick('flicker_icon_l');
		$this->input_type('filter_host', $host['name']);
		$this->input_type('filter_ip', '');
		$this->input_type('filter_port', '');
		$this->zbxTestClickWait('filter');
		$this->zbxTestTextPresent($host['name']);
	}

	// Filter returns nothing
	public function testPageHosts_FilterNone() {
		$this->zbxTestLogin('hosts.php');

		// Reset filter
		$this->zbxTestClick('css=span.btn-link');

		$this->input_type('filter_host', '1928379128ksdhksdjfh');
		$this->zbxTestClickWait('filter');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterNone1() {
		$this->zbxTestLogin('hosts.php');

		// Reset filter
		$this->zbxTestClick('css=span.btn-link');

		$this->input_type('filter_host', '_');
		$this->zbxTestClickWait('filter');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterNone2() {
		$this->zbxTestLogin('hosts.php');

		// Reset filter
		$this->zbxTestClick('css=span.btn-link');

		$this->input_type('filter_host', '%');
		$this->zbxTestClickWait('filter');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
	}

	// Filter reset

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_FilterReset($host) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClick('css=span.btn-link');
		$this->zbxTestClickWait('filter');
		$this->zbxTestTextPresent($host['name']);
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_Items($host) {
		$hostid=$host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestTextPresent('HOSTS');
		$this->zbxTestTextPresent('Displaying');
		// Go to the list of items
		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();
		// We are in the list of items
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestTextPresent('Displaying');
		// Header
		$this->zbxTestTextPresent(['Wizard', 'Name', 'Triggers', 'Key', 'Interval', 'History', 'Trends', 'Type', 'Status', 'Applications', 'Error']);
	}

	public function testPageHosts_MassExportAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassExport() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassUpdateAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassUpdate() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassActivateAll() {
		DBexecute("update hosts set status=".HOST_STATUS_NOT_MONITORED." where status=".HOST_STATUS_MONITORED);

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestDropdownSelect('action', 'Activate selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestTextPresent('Host status updated');

		$sql="select * from hosts where status=".HOST_STATUS_NOT_MONITORED;
		$this->assertEquals(0, DBcount($sql), "Chuck Norris: all hosts activated but DB does not match");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_MassActivate($host) {
		DBexecute("update hosts set status=".HOST_STATUS_NOT_MONITORED." where status=".HOST_STATUS_MONITORED);

		$this->chooseOkOnNextConfirmation();

		$hostid = $host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('hosts_'.$hostid);
		$this->zbxTestDropdownSelect('action', 'Activate selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestTextPresent('Host status updated');

		$sql="select * from hosts where hostid=$hostid and status=".HOST_STATUS_MONITORED;
		$this->assertEquals(1, DBcount($sql), "Chuck Norris: host $hostid activated but status is wrong in the DB");
	}

	public function testPageHosts_MassDisableAll() {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestDropdownSelect('action', 'Disable selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestTextPresent('Host status updated');

		$sql="select * from hosts where status=".HOST_STATUS_MONITORED;
		$this->assertEquals(0, DBcount($sql), "Chuck Norris: all hosts disabled but DB does not match");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_MassDisable($host) {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$this->chooseOkOnNextConfirmation();

		$hostid = $host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('hosts_'.$hostid);
		$this->zbxTestDropdownSelect('action', 'Disable selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->zbxTestCheckTitle('Hosts');
		$this->zbxTestTextPresent('Host status updated');

		$sql="select * from hosts where hostid=$hostid and status=".HOST_STATUS_NOT_MONITORED;
		$this->assertEquals(1, DBcount($sql), "Chuck Norris: host $hostid disabled but status is wrong in the DB");
	}

	public function testPageHosts_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassDelete() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
