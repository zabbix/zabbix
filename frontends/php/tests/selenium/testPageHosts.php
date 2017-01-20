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

class testPageHosts extends CWebTest {

	public static function allHosts() {
		return DBdata(
			'SELECT h.name,h.hostid,g.name AS group_name'.
			' FROM hosts h'.
				' LEFT JOIN hosts_groups hg'.
					' ON hg.hostid=h.hostid'.
				' LEFT JOIN groups g'.
					' ON g.groupid=hg.groupid'.
			' WHERE h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
			" AND h.name NOT LIKE '%{#%'"
		);
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_CheckLayout($host) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', $host['group_name']);
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestCheckHeader('Hosts');
		$this->zbxTestTextPresent('Displaying');

		$this->zbxTestTextPresent(
			[
				'Name',
				'Applications',
				'Items',
				'Triggers',
				'Graphs',
				'Discovery',
				'Interface',
				'Templates',
				'Status',
				'Availability',
				'Agent encryption',
				'Info'
			]
		);

		$this->zbxTestTextPresent([$host['name']]);
		$this->zbxTestTextPresent(['Export', 'Mass update', 'Enable', 'Disable', 'Delete']);
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_SimpleUpdate($host) {
		$hostid = $host['hostid'];
		$name = $host['name'];

		$sqlHosts =
			'SELECT hostid,proxy_hostid,host,status,error,available,ipmi_authtype,ipmi_privilege,ipmi_username,'.
			'ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,'.
			'maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,'.
			'snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error,'.
			'name,flags,templateid,description,tls_connect,tls_accept'.
			' FROM hosts'.
			' WHERE hostid='.$hostid;
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

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestCheckHeader('Hosts');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextNotPresent('Displaying 0');

		$this->zbxTestTextPresent(
			[
				'Name',
				'Applications',
				'Items',
				'Triggers',
				'Graphs',
				'Discovery',
				'Interface',
				'Templates',
				'Status',
				'Availability',
				'Agent encryption',
				'Info'
			]
		);

		$this->zbxTestClickLinkText($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');

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
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestInputTypeWait('filter_host', $host['name']);
		$this->zbxTestClickWait('filter_set');
		$this->zbxTestTextPresent($host['name']);
	}

	public function testPageHosts_FilterNone() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestClickButtonText('Reset');

		$this->zbxTestInputTypeWait('filter_host', '1928379128ksdhksdjfh');
		$this->zbxTestClickWait('filter_set');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
		$this->zbxTestClickButtonText('Reset');

		$this->zbxTestInputTypeWait('filter_host', '%');
		$this->zbxTestClickWait('filter_set');
		$this->zbxTestTextPresent('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterReset() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickWait('filter_set');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_Items($host) {
		$hostid = $host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestCheckHeader('Hosts');
		$this->zbxTestTextPresent('Displaying');

		$this->zbxTestHrefClickWait("items.php?filter_set=1&hostid=$hostid");

		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');
		$this->zbxTestTextPresent('Displaying');

		$this->zbxTestTextPresent(
			[
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
				'Info'
			]
		);
	}

	public function testPageHosts_MassActivateAll() {
		DBexecute("update hosts set status=".HOST_STATUS_NOT_MONITORED." where status=".HOST_STATUS_MONITORED);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestClickButton('host.massenable');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Hosts enabled');

		$sql = "select host from hosts where status=".HOST_STATUS_NOT_MONITORED.
			" and name NOT LIKE '%{#%'";
		$this->assertEquals(0, DBcount($sql), "Chuck Norris: all hosts activated but DB does not match");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_MassActivate($host) {
		DBexecute("update hosts set status=".HOST_STATUS_NOT_MONITORED." where status=".HOST_STATUS_MONITORED);

		$hostid = $host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('hosts_'.$hostid);
		$this->zbxTestClickButton('host.massenable');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host enabled');

		$sql = "select * from hosts where hostid=$hostid and status=".HOST_STATUS_MONITORED;
		$this->assertEquals(1, DBcount($sql), "Chuck Norris: host $hostid activated but status is wrong in the DB");
	}

	public function testPageHosts_MassDisableAll() {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestClickButton('host.massdisable');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Hosts disabled');

		$sql = "select * from hosts where status=".HOST_STATUS_MONITORED.
			" and name NOT LIKE '%{#%'";
		$this->assertEquals(0, DBcount($sql), "Chuck Norris: all hosts disabled but DB does not match");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_MassDisable($host) {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$hostid = $host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('hosts_'.$hostid);
		$this->zbxTestClickButton('host.massdisable');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host disabled');

		$sql = "select * from hosts where hostid=$hostid and status=".HOST_STATUS_NOT_MONITORED;
		$this->assertEquals(1, DBcount($sql), "Chuck Norris: host $hostid disabled but status is wrong in the DB");
	}

}
