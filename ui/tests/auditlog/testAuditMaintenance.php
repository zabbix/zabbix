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

require_once dirname(__FILE__).'/testPageReportsAuditValues.php';

/**
 * @backup maintenances, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditMaintenance extends testPageReportsAuditValues {

	/**
	 * Id of maintenance.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "maintenance.active_since: 1358844540".
			"\nmaintenance.active_till: 1390466940".
			"\nmaintenance.groups[1]: Added".
			"\nmaintenance.groups[1].groupid: 2".
			"\nmaintenance.groups[1].maintenance_groupid: 1".
			"\nmaintenance.maintenanceid: 1".
			"\nmaintenance.name: audit_maintenance".
			"\nmaintenance.tags[1]: Added".
			"\nmaintenance.tags[1].maintenancetagid: 1".
			"\nmaintenance.tags[1].operator: 0".
			"\nmaintenance.tags[1].tag: audit".
			"\nmaintenance.tags[1].value: details".
			"\nmaintenance.timeperiods[1]: Added".
			"\nmaintenance.timeperiods[1].dayofweek: 64".
			"\nmaintenance.timeperiods[1].period: 3600".
			"\nmaintenance.timeperiods[1].start_time: 64800".
			"\nmaintenance.timeperiods[1].timeperiod_type: 3".
			"\nmaintenance.timeperiods[1].timeperiodid: 1";

	public $updated = "maintenance.active_since: 1358844540 => 1458844500".
			"\nmaintenance.active_till: 1390466940 => 1490466900".
			"\nmaintenance.name: audit_maintenance => updated_maintenance".
			"\nmaintenance.tags[1]: Deleted".
			"\nmaintenance.tags[2]: Added".
			"\nmaintenance.tags[2].maintenancetagid: 2".
			"\nmaintenance.tags[2].operator: 0".
			"\nmaintenance.tags[2].tag: updated_audit".
			"\nmaintenance.tags[2].value: updated_details".
			"\nmaintenance.timeperiods[1]: Deleted".
			"\nmaintenance.timeperiods[2]: Added".
			"\nmaintenance.timeperiods[2].day: 4".
			"\nmaintenance.timeperiods[2].every: 3".
			"\nmaintenance.timeperiods[2].month: 5".
			"\nmaintenance.timeperiods[2].period: 7200".
			"\nmaintenance.timeperiods[2].start_time: 68760".
			"\nmaintenance.timeperiods[2].timeperiod_type: 4".
			"\nmaintenance.timeperiods[2].timeperiodid: 2";

	public $deleted = 'Description: updated_maintenance';

	public $resource_name = 'Maintenance';

	public function prepareCreateData() {
		$ids = CDataHelper::call('maintenance.create', [
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
		$this->assertArrayHasKey('maintenanceids', $ids);
		self::$ids = $ids['maintenanceids'][0];
	}

	/**
	 * Check audit of created maintenance.
	 */
	public function testAuditMaintenance_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

	/**
	 * Check audit of updated maintenance.
	 */
	public function testAuditMaintenance_Update() {
		CDataHelper::call('maintenance.update', [
			[
				'maintenanceid' => self::$ids,
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

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of deleted maintenance.
	 */
	public function testAuditMaintenance_Delete() {
		CDataHelper::call('maintenance.delete', [self::$ids]);

		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
