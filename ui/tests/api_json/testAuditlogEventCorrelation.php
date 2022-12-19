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
 * @backup correlation
 */
class testAuditlogEventCorrelation extends testAuditlogCommon {

	/**
	 * Existing Event correlation ID.
	 */
	private const CORRELATIONID = 99001;

	public function testAuditlogEventCorrelation_Create() {
		$create = $this->call('correlation.create', [
			[
				'name' => 'New event correlation for audit',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'type' => 1,
							'tag' => 'ok'
						]
					]
				],
				'operations' => [
					[
						'type' => 1
					]
				]
			]
		]);

		$resourceid = $create['result']['correlationids'][0];
		$conditionid = CDBHelper::getRow('SELECT corr_conditionid FROM corr_condition WHERE correlationid='.
				zbx_dbstr($resourceid)
		);

		$created = json_encode([
			'correlation.name' => ['add', 'New event correlation for audit'],
			'correlation.filter' => ['add'],
			'correlation.filter.conditions['.$conditionid['corr_conditionid'].']' => ['add'],
			'correlation.filter.conditions['.$conditionid['corr_conditionid'].'].type' => ['add', '1'],
			'correlation.filter.conditions['.$conditionid['corr_conditionid'].'].tag' => ['add', 'ok'],
			'correlation.filter.conditions['.$conditionid['corr_conditionid'].'].corr_conditionid'
				=> ['add', $conditionid['corr_conditionid']],
			'correlation.operations['.$resourceid.']' => ['add'],
			'correlation.operations['.$resourceid.'].type' => ['add', '1'],
			'correlation.operations['.$resourceid.'].corr_operationid' => ['add', $resourceid],
			'correlation.correlationid' => ['add', $resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, $resourceid);
	}

	public function testAuditlogEventCorrelation_Update() {
		// Correlation name before update
		$correlation_name = CDBHelper::getRow('SELECT name FROM correlation WHERE correlationid ='.
				zbx_dbstr(self::CORRELATIONID)
		);

		$this->call('correlation.update', [
			[
				'correlationid' => self::CORRELATIONID,
				'name' => 'Updated event correlation name',
				'filter' => [
					'evaltype' => 2,
					'conditions' => [
						[
							'type' => 0,
							'tag' => 'not ok'
						]
					]
				],
				'operations' => [
					[
						'type' => 0
					]
				]
			]
		]);

		$condition = CDBHelper::getRow('SELECT corr_conditionid FROM corr_condition WHERE correlationid ='.
				zbx_dbstr(self::CORRELATIONID)
		);

		$updated = json_encode([
			'correlation.filter.conditions[99001]' => ['delete'],
			'correlation.filter.conditions['.$condition['corr_conditionid'].']' => ['add'],
			'correlation.name' => ['update', 'Updated event correlation name', $correlation_name['name']],
			'correlation.filter' => ['update'],
			'correlation.filter.evaltype' => ['update', '2', '0'],
			'correlation.filter.conditions['.$condition['corr_conditionid'].'].tag' => ['add', 'not ok'],
			'correlation.filter.conditions['.$condition['corr_conditionid'].'].corr_conditionid'
				=> ['add', $condition['corr_conditionid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::CORRELATIONID);
	}

	public function testAuditlogEventCorrelation_Delete() {
		$this->call('correlation.delete', [self::CORRELATIONID]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated event correlation name', self::CORRELATIONID);
	}
}
