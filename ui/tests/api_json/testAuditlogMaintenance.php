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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup maintenances
 */
class testAuditlogMaintenance extends testAuditlogCommon {

	/**
	 * Existing Maintenance ID.
	 */
	private const MAINTENANCEID = 60002;

	public function testAuditlogMaintenance_Create() {
		$create = $this->call('maintenance.create', [
			[
				'name' => 'audit_maintenance',
				'active_since' => 1358844540,
				'active_till' => 1390466940,
				'tags_evaltype' => 0,
				'groups' => [
					'groupid' => 2
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
						'operator' => 0,
						'value' => 'details'
					]
				]
			]
		]);

		$resourceid = $create['result']['maintenanceids'][0];
		$groupid = CDBHelper::getRow('SELECT maintenance_groupid FROM maintenances_groups WHERE maintenanceid='.
				zbx_dbstr($resourceid)
		);
		$timeperiod = CDBHelper::getRow('SELECT timeperiodid FROM timeperiods ORDER BY timeperiodid DESC');
		$tags = CDBHelper::getRow('SELECT maintenancetagid FROM maintenance_tag WHERE maintenanceid='.zbx_dbstr($resourceid));

		$created = json_encode([
			'maintenance.name' => ['add', 'audit_maintenance'],
			'maintenance.active_since' => ['add', '1358844540'],
			'maintenance.active_till' => ['add', '1390466940'],
			'maintenance.groups['.$groupid['maintenance_groupid'].']' => ['add'],
			'maintenance.groups['.$groupid['maintenance_groupid'].'].groupid' => ['add', '2'],
			'maintenance.groups['.$groupid['maintenance_groupid'].'].maintenance_groupid'
				=> ['add', $groupid['maintenance_groupid']],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].']' => ['add'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].period' => ['add', '3600'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].timeperiod_type' => ['add', '3'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].start_time' => ['add', '64800'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].dayofweek' => ['add', '64'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].timeperiodid' => ['add', $timeperiod['timeperiodid']],
			'maintenance.tags['.$tags['maintenancetagid'].']' => ['add'],
			'maintenance.tags['.$tags['maintenancetagid'].'].tag' => ['add', 'audit'],
			'maintenance.tags['.$tags['maintenancetagid'].'].operator' => ['add', '0'],
			'maintenance.tags['.$tags['maintenancetagid'].'].value' => ['add', 'details'],
			'maintenance.tags['.$tags['maintenancetagid'].'].maintenancetagid' => ['add', $tags['maintenancetagid']],
			'maintenance.maintenanceid' => ['add', $resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, $resourceid);
	}

	public function testAuditlogMaintenance_Update() {
		$this->call('maintenance.update', [
			[
				'maintenanceid' => self::MAINTENANCEID,
				'name' => 'updated_maintenance',
				'active_since' => 1458844540,
				'active_till' => 1490466940,
				'tags_evaltype' => 0,
				'groups' => [
					'groupid' => 2
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
						'operator' => 0,
						'value' => 'updated_details'
					]
				]
			]
		]);

		$groupid = CDBHelper::getRow('SELECT maintenance_groupid FROM maintenances_groups WHERE maintenanceid='.
				zbx_dbstr(self::MAINTENANCEID)
		);
		$timeperiod = CDBHelper::getRow('SELECT timeperiodid FROM timeperiods ORDER BY timeperiodid DESC');
		$tags = CDBHelper::getRow('SELECT maintenancetagid FROM maintenance_tag WHERE maintenanceid='.
				zbx_dbstr(self::MAINTENANCEID)
		);

		$updated = json_encode([
			'maintenance.groups[1]' => ['delete'],
			'maintenance.timeperiods[2]' => ['delete'],
			'maintenance.groups['.$groupid['maintenance_groupid'].']' => ['add'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].']' => ['add'],
			'maintenance.tags['.$tags['maintenancetagid'].']' => ['add'],
			'maintenance.name' => ['update', 'updated_maintenance', 'maintenance_has_only_group'],
			'maintenance.active_since' => ['update', '1458844500', '1539723600'],
			'maintenance.active_till' => ['update', '1490466900', '1539810000'],
			'maintenance.groups['.$groupid['maintenance_groupid'].'].groupid' => ['add', '2'],
			'maintenance.groups['.$groupid['maintenance_groupid'].'].maintenance_groupid'
				=> ['add', $groupid['maintenance_groupid']],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].period' => ['add', '7200'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].timeperiod_type' => ['add', '4'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].start_time' => ['add', '68760'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].every' => ['add', '3'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].day' => ['add', '4'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].month' => ['add', '5'],
			'maintenance.timeperiods['.$timeperiod['timeperiodid'].'].timeperiodid' => ['add', $timeperiod['timeperiodid']],
			'maintenance.tags['.$tags['maintenancetagid'].'].tag' => ['add', 'updated_audit'],
			'maintenance.tags['.$tags['maintenancetagid'].'].operator' => ['add', '0'],
			'maintenance.tags['.$tags['maintenancetagid'].'].value' => ['add', 'updated_details'],
			'maintenance.tags['.$tags['maintenancetagid'].'].maintenancetagid' => ['add', $tags['maintenancetagid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::MAINTENANCEID);
	}

	public function testAuditlogMaintenance_Delete() {
		$this->call('maintenance.delete', [self::MAINTENANCEID]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'updated_maintenance', self::MAINTENANCEID);
	}
}
