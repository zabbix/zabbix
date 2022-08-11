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
 * @backup correlation, ids
 */
class testAuditlogEventCorrelation extends CAPITest {

	protected static $resourceid;

	public function testAuditlogEventCorrelation_Create() {
		$created = "{\"correlation.name\":[\"add\",\"New event correlation for audit\"],\"correlation.filter\":[".
				"\"add\"],\"correlation.filter.conditions[99004]\":[\"add\"],\"correlation.filter.conditions".
				"[99004].type\":[\"add\",\"1\"],\"correlation.filter.conditions[99004].tag\":[\"add\",\"ok\"],".
				"\"correlation.filter.conditions[99004].corr_conditionid\":[\"add\",\"99004\"],\"correlation.".
				"operations[99004]\":[\"add\"],\"correlation.operations[99004].type\":[\"add\",\"1\"],".
				"\"correlation.operations[99004].corr_operationid\":[\"add\",\"99004\"],\"correlation.correlationid".
				"\":[\"add\",\"99004\"]}";
		
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

		self::$resourceid = $create['result']['correlationids'][0];
		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogEventCorrelation_Update() {
		$updated = "{\"correlation.filter.conditions[99004]\":[\"delete\"],\"correlation.operations[99004]".
				"\":[\"delete\"],\"correlation.filter.conditions[99005]\":[\"add\"],\"correlation.operations[99005]".
				"\":[\"add\"],\"correlation.name\":[\"update\",\"Updated event correlation name\",".
				"\"New event correlation for audit\"],\"correlation.filter\":[\"update\"],\"correlation.filter.evaltype".
				"\":[\"update\",\"2\",\"0\"],\"correlation.filter.conditions[99005].tag\":[\"add\",\"not ok\"],".
				"\"correlation.filter.conditions[99005].corr_conditionid\":[\"add\",\"99005\"],".
				"\"correlation.operations[99005].corr_operationid\":[\"add\",\"99005\"]}";

		$this->call('correlation.update', [
			[
				'correlationid' => self::$resourceid,
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

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogEventCorrelation_Delete() {
		$this->call('correlation.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'Updated event correlation name');
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
