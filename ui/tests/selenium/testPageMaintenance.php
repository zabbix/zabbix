<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageMaintenance extends CLegacyWebTest {
	// Returns all maintenances
	public static function allMaintenances() {
		return CDBHelper::getDataProvider('select * from maintenances');
	}

	/**
	* @dataProvider allMaintenances
	*/
	public function testPageMaintenance_CheckLayout($maintenance) {
		$this->zbxTestLogin('maintenance.php');
		$this->query('button:Reset')->one()->click();
		$this->zbxTestCheckTitle('Configuration of maintenance periods');

		$this->zbxTestCheckHeader('Maintenance periods');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextNotPresent('Displaying 0');
		$this->zbxTestTextPresent(['Name', 'Type', 'Active since', 'Active till', 'State', 'Description']);
		$this->zbxTestTextPresent($maintenance['name']);
		if ($maintenance['maintenance_type'] == MAINTENANCE_TYPE_NORMAL)	$this->zbxTestTextPresent('With data collection');
		if ($maintenance['maintenance_type'] == MAINTENANCE_TYPE_NODATA)	$this->zbxTestTextPresent('No data collection');
	}

	/**
	* @dataProvider allMaintenances
	*/
	public function testPageMaintenance_SimpleUpdate($maintenance) {
		$name = $maintenance['name'];
		$maintenanceid = $maintenance['maintenanceid'];

		$sqlMaintenance = "select * from maintenances where name='$name' order by maintenanceid";
		$oldHashMaintenance = CDBHelper::getHash($sqlMaintenance);
		$sqlHosts = "select * from maintenances_hosts where maintenanceid=$maintenanceid order by maintenance_hostid";
		$oldHashHosts = CDBHelper::getHash($sqlHosts);
		$sqlGroups = "select * from maintenances_groups where maintenanceid=$maintenanceid order by maintenance_groupid";
		$oldHashGroups = CDBHelper::getHash($sqlGroups);
		$sqlWindows = "select * from maintenances_windows where maintenanceid=$maintenanceid order by maintenance_timeperiodid";
		$oldHashWindows = CDBHelper::getHash($sqlWindows);
		$sqlTimeperiods = "select * from timeperiods where timeperiodid in (select timeperiodid from maintenances_windows where maintenanceid=$maintenanceid) order by timeperiodid";
		$oldHashTimeperiods = CDBHelper::getHash($sqlTimeperiods);

		$this->zbxTestLogin('maintenance.php');
		$this->query('button:Reset')->one()->click();
		$this->zbxTestCheckTitle('Configuration of maintenance periods');
		$this->zbxTestClickLinkText($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of maintenance periods');
		$this->zbxTestTextPresent('Maintenance updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('Maintenance periods');

		$this->assertEquals($oldHashMaintenance, CDBHelper::getHash($sqlMaintenance), "Chuck Norris: Maintenance update changed data in table 'maintenances'");
		$this->assertEquals($oldHashHosts, CDBHelper::getHash($sqlHosts), "Chuck Norris: Maintenance update changed data in table 'maintenances_hosts'");
		$this->assertEquals($oldHashGroups, CDBHelper::getHash($sqlGroups), "Chuck Norris: Maintenance update changed data in table 'maintenances_groups'");
		$this->assertEquals($oldHashWindows, CDBHelper::getHash($sqlWindows), "Chuck Norris: Maintenance update changed data in table 'maintenances_windows'");
		$this->assertEquals($oldHashTimeperiods, CDBHelper::getHash($sqlTimeperiods), "Chuck Norris: Maintenance update changed data in table 'timeperiods'");
	}

	/**
	 * @dataProvider allMaintenances
	 * @backup maintenances
	 */
	public function testPageMaintenance_MassDelete($maintenance) {
		$maintenanceid = $maintenance['maintenanceid'];

		$this->zbxTestLogin('maintenance.php');
		$this->query('button:Reset')->one()->click();
		$this->zbxTestCheckTitle('Configuration of maintenance periods');
		$this->zbxTestCheckboxSelect('maintenanceids_'.$maintenanceid);
		$this->zbxTestClickButton('maintenance.massdelete');

		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of maintenance periods');
		$this->zbxTestTextPresent('Maintenance deleted');

		$sql = "select * from maintenances where maintenanceid=$maintenanceid";
		$this->assertEquals(0, CDBHelper::getCount($sql));
		$sql = "select * from maintenances_hosts where maintenanceid=$maintenanceid";
		$this->assertEquals(0, CDBHelper::getCount($sql));
		$sql = "select * from maintenances_groups where maintenanceid=$maintenanceid";
		$this->assertEquals(0, CDBHelper::getCount($sql));
		$sql = "select * from maintenances_windows where maintenanceid=$maintenanceid";
		$this->assertEquals(0, CDBHelper::getCount($sql));
		$sql = "select * from timeperiods where timeperiodid in (select timeperiodid from maintenances_windows where maintenanceid=$maintenanceid)";
		$this->assertEquals(0, CDBHelper::getCount($sql));
		$this->page->logout();
	}
}
