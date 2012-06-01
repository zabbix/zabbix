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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageHosts extends CWebTest {
	// Returns all hosts
	public static function allHosts() {
		return DBdata('select * from hosts where status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')');
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_CheckLayout($host) {
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'Zabbix servers');
		$this->checkTitle('Configuration of hosts');
		$this->ok('HOSTS');
		$this->ok('Displaying');
		// Header
		$this->ok(
			array(
				'Name',
				'Applications',
				'Items',
				'Triggers',
				'Graphs',
				'Discovery',
				'Interface',
				'Templates',
				'Status',
				'Availability'
			)
		);
		// Data
		$this->ok(array($host['name']));
		$this->dropdown_select('go', 'Export selected');
		$this->dropdown_select('go', 'Mass update');
		$this->dropdown_select('go', 'Enable selected');
		$this->dropdown_select('go', 'Disable selected');
		$this->dropdown_select('go', 'Delete selected');
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_SimpleUpdate($host) {
		$hostid = $host['hostid'];
		$name = $host['name'];

		$sqlHosts = "select * from hosts where hostid=$hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items where hostid=$hostid order by itemid";
		$oldHashItems = DBhash($sqlItems);
		$sqlApplications = "select * from applications where hostid=$hostid order by applicationid";
		$oldHashApplications = DBhash($sqlApplications);
		$sqlInteraface = "select * from interface where hostid=$hostid order by interfaceid";
		$oldHashInterface = DBhash($sqlInteraface);
		$sqlHostmacro = "select * from hostmacro where hostid=$hostid order by hostmacroid";
		$oldHashHostmacro = DBhash($sqlHostmacro);
		$sqlHostsgroups = "select * from hosts_groups where hostid=$hostid order by hostgroupid";
		$oldHashHostsgroups = DBhash($sqlHostsgroups);
		$sqlHoststemplates = "select * from hosts_templates where hostid=$hostid order by hosttemplateid";
		$oldHashHoststemplates = DBhash($sqlHoststemplates);
		$sqlMaintenanceshosts = "select * from maintenances_hosts where hostid=$hostid order by maintenance_hostid";
		$oldHashMaintenanceshosts = DBhash($sqlMaintenanceshosts);
		$sqlHostinventory = "select * from host_inventory where hostid=$hostid";
		$oldHashHostinventory = DBhash($sqlHostinventory);

		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->checkTitle('Configuration of hosts');
		$this->ok('HOSTS');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
		// Header
		$this->ok(
			array(
				'Name',
				'Applications',
				'Items',
				'Triggers',
				'Graphs',
				'Discovery',
				'Interface',
				'Templates',
				'Status',
				'Availability'
			)
		);

		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host updated');

		$this->assertEquals($oldHashHosts, DBhash($sqlHosts), "Chuck Norris: Host update changed data in table 'hosts'");
		$this->assertEquals($oldHashItems, DBhash($sqlItems), "Chuck Norris: Host update changed data in table 'items'");
		$this->assertEquals($oldHashApplications, DBhash($sqlApplications), "Chuck Norris: Host update changed data in table 'applications'");
		$this->assertEquals($oldHashInterface, DBhash($sqlInteraface), "Chuck Norris: Host update changed data in table 'interface'");
		$this->assertEquals($oldHashHostmacro, DBhash($sqlHostmacro), "Chuck Norris: Host update changed data in table 'host_macro'");
		$this->assertEquals($oldHashHostsgroups, DBhash($sqlHostsgroups), "Chuck Norris: Host update changed data in table 'hosts_groups'");
		$this->assertEquals($oldHashHoststemplates, DBhash($sqlHoststemplates), "Chuck Norris: Host update changed data in table 'hosts_templates'");
		$this->assertEquals($oldHashMaintenanceshosts, DBhash($sqlMaintenanceshosts), "Chuck Norris: Host update changed data in table 'maintenances_hosts'");
		$this->assertEquals($oldHashHostinventory, DBhash($sqlHostinventory), "Chuck Norris: Host update changed data in table 'host_inventory'");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_FilterHost($host) {
		$this->login('hosts.php');
		$this->click('flicker_icon_l');
		$this->input_type('filter_host', $host['name']);
		$this->input_type('filter_ip', '');
		$this->input_type('filter_port', '');
		$this->click('filter');
		$this->wait();
		$this->ok($host['name']);
	}

	// Filter returns nothing
	public function testPageHosts_FilterNone() {
		$this->login('hosts.php');

		// Reset filter
		$this->click('reset');

		$this->input_type('filter_host', '1928379128ksdhksdjfh');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterNone1() {
		$this->login('hosts.php');

		// Reset filter
		$this->click('reset');

		$this->input_type('filter_host', '_');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterNone2() {
		$this->login('hosts.php');

		// Reset filter
		$this->click('reset');

		$this->input_type('filter_host', '%');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	// Filter reset

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_FilterReset($host) {
		$this->login('hosts.php');
		$this->click('reset');
		$this->click('filter');
		$this->wait();
		$this->ok($host['name']);
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_Items($host) {
		$hostid = $host['hostid'];

		$this->login('hosts.php');
		$this->checkTitle('Configuration of hosts');
		$this->dropdown_select_wait('groupid', 'all');
		$this->checkTitle('Configuration of hosts');
		$this->ok('HOSTS');
		$this->ok('Displaying');
		// Go to the list of items
		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();
		// We are in the list of items
		$this->checkTitle('Configuration of items');
		$this->ok('Displaying');
		// Header
		$this->ok(
			array(
				'Wizard',
				'Name',
				'Triggers',
				'Key',
				'Interval',
				'History',
				'Trends',
				'Type',
				'Status',
				'Applications',
				'Error'
			)
		);
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

		$this->login('hosts.php');
		$this->checkTitle('Configuration of hosts');
		$this->dropdown_select_wait('groupid', 'all');

		$this->checkbox_select("all_hosts");
		$this->dropdown_select('go', 'Enable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host status updated');

		$sql = "select * from hosts where status=".HOST_STATUS_NOT_MONITORED;
		$this->assertEquals(0, DBcount($sql), "Chuck Norris: all hosts activated but DB does not match");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_MassActivate($host) {
		DBexecute("update hosts set status=".HOST_STATUS_NOT_MONITORED." where status=".HOST_STATUS_MONITORED);

		$this->chooseOkOnNextConfirmation();

		$hostid = $host['hostid'];

		$this->login('hosts.php');
		$this->checkTitle('Configuration of hosts');
		$this->dropdown_select_wait('groupid', 'all');

		$this->checkbox_select("hosts_$hostid");
		$this->dropdown_select('go', 'Enable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host status updated');

		$sql = "select * from hosts where hostid=$hostid and status=".HOST_STATUS_MONITORED;
		$this->assertEquals(1, DBcount($sql), "Chuck Norris: host $hostid activated but status is wrong in the DB");
	}

	public function testPageHosts_MassDisableAll() {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$this->chooseOkOnNextConfirmation();

		$this->login('hosts.php');
		$this->checkTitle('Configuration of hosts');
		$this->dropdown_select_wait('groupid', 'all');

		$this->checkbox_select("all_hosts");
		$this->dropdown_select('go', 'Disable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host status updated');

		$sql = "select * from hosts where status=".HOST_STATUS_MONITORED;
		$this->assertEquals(0, DBcount($sql), "Chuck Norris: all hosts disabled but DB does not match");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_MassDisable($host) {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$this->chooseOkOnNextConfirmation();

		$hostid = $host['hostid'];

		$this->login('hosts.php');
		$this->checkTitle('Configuration of hosts');
		$this->dropdown_select_wait('groupid', 'all');

		$this->checkbox_select("hosts_$hostid");
		$this->dropdown_select('go', 'Disable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host status updated');

		$sql = "select * from hosts where hostid=$hostid and status=".HOST_STATUS_NOT_MONITORED;
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
?>
