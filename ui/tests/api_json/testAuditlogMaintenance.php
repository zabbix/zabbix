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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup maintenances, ids
 */
class testAuditlogMaintenance extends CAPITest {

	protected static $resourceid;

	public function testAuditlogMaintenance_Create() {
		$created = "{\"maintenance.name\":[\"add\",\"audit_maintenance\"],\"maintenance.active_since\":[\"add\",".
				"\"1358844540\"],\"maintenance.active_till\":[\"add\",\"1390466940\"],\"maintenance.groups[5]\":[".
				"\"add\"],\"maintenance.groups[5].groupid\":[\"add\",\"2\"],\"maintenance.groups[5].maintenance_groupid".
				"\":[\"add\",\"5\"],\"maintenance.timeperiods[6]\":[\"add\"],\"maintenance.timeperiods[6].period\":[".
				"\"add\",\"3600\"],\"maintenance.timeperiods[6].timeperiod_type\":[\"add\",\"3\"],".
				"\"maintenance.timeperiods[6].start_time\":[\"add\",\"64800\"],\"maintenance.timeperiods[6].dayofweek".
				"\":[\"add\",\"64\"],\"maintenance.timeperiods[6].timeperiodid\":[\"add\",\"6\"],\"maintenance.tags[1]".
				"\":[\"add\"],\"maintenance.tags[1].tag\":[\"add\",\"audit\"],\"maintenance.tags[1].operator\":[\"add".
				"\",\"0\"],\"maintenance.tags[1].value\":[\"add\",\"details\"],\"maintenance.tags[1].maintenancetagid".
				"\":[\"add\",\"1\"],\"maintenance.maintenanceid\":[\"add\",\"60006\"]}";

		$create = $this->call('maintenance.create', [
			[
				'name' => 'audit_maintenance',
				'active_since' => 1358844540,
				'active_till' => 1390466940,
				'tags_evaltype' => 0,
				'groups' => [
					'groupid' => '2'
				],
				'timeperiods' => [
					[
						'period' => 3600,
						'timeperiod_type' => 3,
						'start_time' => 64800,
						'every' => 1,
						'dayofweek' => 64
					]
				],
				'tags' => [
					[
						'tag' => 'audit',
						'operator' => '0',
						'value' => 'details'
					]
				]
			]
		]);

		self::$resourceid = $create['result']['maintenanceids'][0];
		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogMaintenance_Update() {
		$updated = "{\"maintenance.tags[1]\":[\"delete\"],\"maintenance.timeperiods[6]\":[\"delete\"],".
				"\"maintenance.timeperiods[7]\":[\"add\"],\"maintenance.tags[2]\":[\"add\"],\"maintenance.name".
				"\":[\"update\",\"updated_maintenance\",\"audit_maintenance\"],\"maintenance.active_since\":[".
				"\"update\",\"1458844500\",\"1358844540\"],\"maintenance.active_till\":[\"update\",\"1490466900\",".
				"\"1390466940\"],\"maintenance.timeperiods[7].period\":[\"add\",\"7200\"],\"maintenance.timeperiods".
				"[7].timeperiod_type\":[\"add\",\"4\"],\"maintenance.timeperiods[7].start_time\":[\"add\",\"68760\"],".
				"\"maintenance.timeperiods[7].every\":[\"add\",\"3\"],\"maintenance.timeperiods[7].day\":[\"add\",".
				"\"4\"],\"maintenance.timeperiods[7].month\":[\"add\",\"5\"],\"maintenance.timeperiods[7].timeperiodid".
				"\":[\"add\",\"7\"],\"maintenance.tags[2].tag\":[\"add\",\"updated_audit\"],\"maintenance.tags".
				"[2].operator\":[\"add\",\"0\"],\"maintenance.tags[2].value\":[\"add\",\"updated_details\"],".
				"\"maintenance.tags[2].maintenancetagid\":[\"add\",\"2\"]}";

		$this->call('maintenance.update', [
			[
				'maintenanceid' => self::$resourceid,
				'name' => 'updated_maintenance',
				'active_since' => 1458844540,
				'active_till' => 1490466940,
				'tags_evaltype' => 0,
				'groups' => [
					'groupid' => '2'
				],
				'timeperiods' => [
					[
						'period' => 7200,
						'timeperiod_type' => 4,
						'start_time' => 68800,
						'every' => 3,
						'day' => 4,
						'month' => 5
					]
				],
				'tags' => [
					[
						'tag' => 'updated_audit',
						'operator' => '0',
						'value' => 'updated_details'
					]
				]
			]
		]);

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogMaintenance_Delete() {
		$this->call('maintenance.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'updated_maintenance');
	}

	private function sendGetRequest($output, $action, $result) {
		$get = $this->call('auditlog.get', [
			'output' => [$output],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'filter' => [
				'resourceid' => self::$resourceid,
				'action' => $action
			]
		]);

		$this->assertEquals($result, $get['result'][0][$output]);
	}
}
