<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup sla
 */
class testAuditlogSLA extends testAuditlogCommon {

	/**
	 * Created SLA ID.
	 */
	protected static $resourceid;

	/**
	 * Created SLA tags ID.
	 */
	protected static $created_tagid;

	/**
	 * Created SLA downtime ID.
	 */
	protected static $created_downtime;

	/**
	 * Created SLA schedule ID.
	 */
	protected static $created_schedule;

	public function testAuditlogSLA_Create() {
		$create = $this->call('sla.create', [
			[
				'name' => 'Created SLA',
				'slo' => '55.5555',
				'period' => '1',
				'timezone' => 'Europe/Riga',
				'description' => 'Created SLA.',
				'effective_date' => 1672444800,
				'status' => 1,
				'schedule' => [
					[
						'period_from' => 1235,
						'period_to' => 601200
					]
				],
				'service_tags' => [
					[
						'tag' => 'Created tag',
						'operator' => 2,
						'value' => 'Created value'
					]
				],
				'excluded_downtimes' => [
					[
						'name' => 'Created SLA exclude',
						'period_from' => '1648760400',
						'period_to' => '1648764900'
					]
				]
			]
		]);

		self::$resourceid = $create['result']['slaids'][0];
		self::$created_tagid = CDBHelper::getRow('SELECT sla_service_tagid FROM sla_service_tag WHERE slaid='.
				zbx_dbstr(self::$resourceid)
		);
		self::$created_downtime = CDBHelper::getRow('SELECT sla_excluded_downtimeid FROM sla_excluded_downtime WHERE slaid='.
				zbx_dbstr(self::$resourceid)
		);
		self::$created_schedule = CDBHelper::getRow('SELECT sla_scheduleid FROM sla_schedule WHERE slaid='.
				zbx_dbstr(self::$resourceid)
		);

		$created = json_encode([
			'sla.name' => ['add', 'Created SLA'],
			'sla.slo' => ['add', '55.5555'],
			'sla.period' => ['add', '1'],
			'sla.timezone' => ['add', 'Europe/Riga'],
			'sla.description' => ['add', 'Created SLA.'],
			'sla.effective_date' => ['add', '1672444800'],
			'sla.schedule['.self::$created_schedule['sla_scheduleid'].']' => ['add'],
			'sla.schedule['.self::$created_schedule['sla_scheduleid'].'].period_from' => ['add', '1235'],
			'sla.schedule['.self::$created_schedule['sla_scheduleid'].'].period_to' => ['add', '601200'],
			'sla.schedule['.self::$created_schedule['sla_scheduleid'].'].sla_scheduleid'
					=> ['add', self::$created_schedule['sla_scheduleid']],
			'sla.service_tags['.self::$created_tagid['sla_service_tagid'].']' => ['add'],
			'sla.service_tags['.self::$created_tagid['sla_service_tagid'].'].tag' => ['add', 'Created tag'],
			'sla.service_tags['.self::$created_tagid['sla_service_tagid'].'].operator' => ['add', '2'],
			'sla.service_tags['.self::$created_tagid['sla_service_tagid'].'].value' => ['add', 'Created value'],
			'sla.service_tags['.self::$created_tagid['sla_service_tagid'].'].sla_service_tagid'
					=> ['add', self::$created_tagid['sla_service_tagid']],
			'sla.excluded_downtimes['.self::$created_downtime['sla_excluded_downtimeid'].']' => ['add'],
			'sla.excluded_downtimes['.self::$created_downtime['sla_excluded_downtimeid'].'].name'
					=> ['add', 'Created SLA exclude'],
			'sla.excluded_downtimes['.self::$created_downtime['sla_excluded_downtimeid'].'].period_from'
					=> ['add', '1648760400'],
			'sla.excluded_downtimes['.self::$created_downtime['sla_excluded_downtimeid'].'].period_to'
					=> ['add', '1648764900'],
			'sla.excluded_downtimes['.self::$created_downtime['sla_excluded_downtimeid'].'].sla_excluded_downtimeid'
					=> ['add', self::$created_downtime['sla_excluded_downtimeid']],
			'sla.slaid' => ['add', self::$resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid);
	}

	/**
	 * @depends testAuditlogSLA_Create
	 */
	public function testAuditlogSLA_Update() {
		$this->call('sla.update', [
			[
				'slaid' => self::$resourceid,
				'name' => 'Updated SLA',
				'slo' => '66.6666',
				'period' => '2',
				'timezone' => 'Europe/Tallinn',
				'description' => 'Updated SLA.',
				'effective_date' => 1672333200,
				'status' => 0,
				'schedule' => [
					[
						'period_from' => 6789,
						'period_to' => 500300
					]
				],
				'service_tags' => [
					[
						'tag' => 'Updated tag',
						'operator' => 0,
						'value' => 'Updated value'
					]
				],
				'excluded_downtimes' => [
					[
						'name' => 'Updated SLA exclude',
						'period_from' => 1648660400,
						'period_to' => 1648664900
					]
				]
			]
		]);

		$updated_tagid = CDBHelper::getRow('SELECT sla_service_tagid FROM sla_service_tag WHERE slaid='.
				zbx_dbstr(self::$resourceid)
		);
		$updated_downtime = CDBHelper::getRow('SELECT sla_excluded_downtimeid FROM sla_excluded_downtime WHERE slaid='.
				zbx_dbstr(self::$resourceid)
		);
		$updated_schedule = CDBHelper::getRow('SELECT sla_scheduleid FROM sla_schedule WHERE slaid='.
				zbx_dbstr(self::$resourceid)
		);

		$updated = json_encode([
			'sla.service_tags['.self::$created_tagid['sla_service_tagid'].']' => ['delete'],
			'sla.schedule['.self::$created_schedule['sla_scheduleid'].']' => ['delete'],
			'sla.excluded_downtimes['.self::$created_downtime['sla_excluded_downtimeid'].']' => ['delete'],
			'sla.schedule['.$updated_schedule['sla_scheduleid'].']' => ['add'],
			'sla.service_tags['.$updated_tagid['sla_service_tagid'].']' => ['add'],
			'sla.excluded_downtimes['.$updated_downtime['sla_excluded_downtimeid'].']' => ['add'],
			'sla.name' => ['update', 'Updated SLA', 'Created SLA'],
			'sla.slo' => ['update', '66.6666', '55.5555'],
			'sla.period' => ['update', '2', '1'],
			'sla.timezone' => ['update', 'Europe/Tallinn', 'Europe/Riga'],
			'sla.description' => ['update', 'Updated SLA.', 'Created SLA.'],
			'sla.effective_date' => ['add', '1672333200'],
			'sla.status' => ['update', '0', '1'],
			'sla.schedule['.$updated_schedule['sla_scheduleid'].'].period_from' => ['add', '6789'],
			'sla.schedule['.$updated_schedule['sla_scheduleid'].'].period_to' => ['add', '500300'],
			'sla.schedule['.$updated_schedule['sla_scheduleid'].'].sla_scheduleid'
					=> ['add', $updated_schedule['sla_scheduleid']],
			'sla.service_tags['.$updated_tagid['sla_service_tagid'].'].tag' => ['add', 'Updated tag'],
			'sla.service_tags['.$updated_tagid['sla_service_tagid'].'].value' => ['add', 'Updated value'],
			'sla.service_tags['.$updated_tagid['sla_service_tagid'].'].sla_service_tagid'
					=> ['add', $updated_tagid['sla_service_tagid']],
			'sla.excluded_downtimes['.$updated_downtime['sla_excluded_downtimeid'].'].name' => ['add', 'Updated SLA exclude'],
			'sla.excluded_downtimes['.$updated_downtime['sla_excluded_downtimeid'].'].period_from' => ['add', '1648660400'],
			'sla.excluded_downtimes['.$updated_downtime['sla_excluded_downtimeid'].'].period_to' => ['add', '1648664900'],
			'sla.excluded_downtimes['.$updated_downtime['sla_excluded_downtimeid'].'].sla_excluded_downtimeid'
					=> ['add', $updated_downtime['sla_excluded_downtimeid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid);
	}

	/**
	 * @depends testAuditlogSLA_Create
	 */
	public function testAuditlogSLA_Delete() {
		$this->call('sla.delete', [self::$resourceid]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated SLA', self::$resourceid);
	}
}
