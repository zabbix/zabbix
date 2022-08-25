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


require_once dirname(__FILE__).'/testAuditlogCommon.php';

/**
 * @backup correlation, ids
 */
class testAuditlogEventCorrelation extends testAuditlogCommon {
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

		$created = "{\"correlation.name\":[\"add\",\"New event correlation for audit\"],".
			"\"correlation.filter\":[\"add\"],".
			"\"correlation.filter.conditions[".$resourceid."]\":[\"add\"],".
			"\"correlation.filter.conditions[".$resourceid."].type\":[\"add\",\"1\"],".
			"\"correlation.filter.conditions[".$resourceid."].tag\":[\"add\",\"ok\"],".
			"\"correlation.filter.conditions[".$resourceid."].corr_conditionid\":[\"add\",\"".$resourceid."\"],".
			"\"correlation.operations[".$resourceid."]\":[\"add\"],".
			"\"correlation.operations[".$resourceid."].type\":[\"add\",\"1\"],".
			"\"correlation.operations[".$resourceid."].corr_operationid\":[\"add\",\"".$resourceid."\"],".
			"\"correlation.correlationid\":[\"add\",\"".$resourceid."\"]}";

		$this->sendGetRequest('details', 0, $created, $resourceid);
	}

	public function testAuditlogEventCorrelation_Update() {
		$this->call('correlation.update', [
			[
				'correlationid' => 99001,
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

		$condition = CDBHelper::getAll('SELECT corr_conditionid FROM corr_condition WHERE correlationid = 99001');

		$updated = "{\"correlation.filter.conditions[99001]\":[\"delete\"],".
			"\"correlation.filter.conditions[".$condition[0]['corr_conditionid']."]\":[\"add\"],".
			"\"correlation.name\":[\"update\",\"Updated event correlation name\",\"Event correlation for update\"],".
			"\"correlation.filter\":[\"update\"],".
			"\"correlation.filter.evaltype\":[\"update\",\"2\",\"0\"],".
			"\"correlation.filter.conditions[".$condition[0]['corr_conditionid']."].tag\":[\"add\",\"not ok\"],".
			"\"correlation.filter.conditions[".$condition[0]['corr_conditionid'].
					"].corr_conditionid\":[\"add\",\"".$condition[0]['corr_conditionid']."\"]}";

		$this->sendGetRequest('details', 1, $updated, 99001);
	}

	public function testAuditlogEventCorrelation_Delete() {
		$this->call('correlation.delete', [99001]);
		$this->sendGetRequest('resourcename', 2, 'Updated event correlation name', 99001);
	}
}
