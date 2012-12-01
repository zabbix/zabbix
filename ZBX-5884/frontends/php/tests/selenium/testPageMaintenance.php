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

class testPageMaintenance extends CWebTest {
	// Returns all maintenances
	public static function allMaintenances() {
		return DBdata('select * from maintenances');
	}

	/**
	* @dataProvider allMaintenances
	*/
	public function testPageMaintenance_CheckLayout($maintenance) {
		$this->login('maintenance.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->checkTitle('Configuration of maintenance');

		$this->ok('Maintenance');
		$this->ok('CONFIGURATION OF MAINTENANCE PERIODS');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
		$this->ok(array('Name', 'Type', 'State', 'Description'));
		$this->ok($maintenance['name']);
		if ($maintenance['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)	$this->ok('With data collection');
		if ($maintenance['maintenance_type'] == MAINTENANCE_TYPE_NODATA)	$this->ok('No data collection');
		$this->dropdown_select('go', 'Delete selected');
		// TODO
		// $this->dropdown_select('go', 'Enable selected');
		// $this->dropdown_select('go', 'Disable selected');
	}

	/**
	* @dataProvider allMaintenances
	*/
	public function testPageMaintenance_SimpleUpdate($maintenance) {
		$name = $maintenance['name'];
		$maintenanceid = $maintenance['maintenanceid'];

		$sqlMaintenance = "select * from maintenances where name='$name' order by maintenanceid";
		$oldHashMaintenance = DBhash($sqlMaintenance);
		$sqlHosts = "select * from maintenances_hosts where maintenanceid=$maintenanceid order by maintenance_hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlGroups = "select * from maintenances_groups where maintenanceid=$maintenanceid order by maintenance_groupid";
		$oldHashGroups = DBhash($sqlGroups);
		$sqlWindows = "select * from maintenances_windows where maintenanceid=$maintenanceid order by maintenance_timeperiodid";
		$oldHashWindows = DBhash($sqlWindows);
		$sqlTimeperiods = "select * from timeperiods where timeperiodid in (select timeperiodid from maintenances_windows where maintenanceid=$maintenanceid) order by timeperiodid";
		$oldHashTimeperiods = DBhash($sqlTimeperiods);

		$this->login('maintenance.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->checkTitle('Configuration of maintenance');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of maintenance');
		$this->ok('Maintenance updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF MAINTENANCE PERIODS');

		$this->assertEquals($oldHashMaintenance, DBhash($sqlMaintenance), "Chuck Norris: Maintenance update changed data in table 'maintenances'");
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts), "Chuck Norris: Maintenance update changed data in table 'maintenances_hosts'");
		$this->assertEquals($oldHashGroups, DBhash($sqlGroups), "Chuck Norris: Maintenance update changed data in table 'maintenances_groups'");
		$this->assertEquals($oldHashWindows, DBhash($sqlWindows), "Chuck Norris: Maintenance update changed data in table 'maintenances_windows'");
		$this->assertEquals($oldHashTimeperiods, DBhash($sqlTimeperiods), "Chuck Norris: Maintenance update changed data in table 'timeperiods'");
	}

	public function testPageMaintenance_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allMaintenances
	*/
	public function testPageMaintenance_MassDelete($maintenance) {
		$maintenanceid = $maintenance['maintenanceid'];

		DBsave_tables('maintenances');

		$this->chooseOkOnNextConfirmation();

		$this->login('maintenance.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->checkTitle('Configuration of maintenance');
		$this->checkbox_select("maintenanceids[$maintenanceid]");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of maintenance');
		$this->ok('Maintenance deleted');

		$sql = "select * from maintenances where maintenanceid=$maintenanceid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from maintenances_hosts where maintenanceid=$maintenanceid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from maintenances_groups where maintenanceid=$maintenanceid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from maintenances_windows where maintenanceid=$maintenanceid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from timeperiods where timeperiodid in (select timeperiodid from maintenances_windows where maintenanceid=$maintenanceid)";
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('maintenances');
	}

	public function testPageMaintenance_SingleEnable() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaintenance_SingleDisable() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaintenance_MassEnableAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaintenance_MassEnable() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaintenance_MassDisableAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaintenance_MassDisable() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaintenance_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
