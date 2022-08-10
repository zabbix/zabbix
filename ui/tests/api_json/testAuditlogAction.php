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
 * @backup actions, ids
 */
class testAuditlogAction extends CAPITest {

	protected static $resourceid;

	public function testAuditlogAction_Create() {
		$created = "{\"action.name\":[\"add\",\"Audit action\"],\"action.esc_period\":[\"add\",\"2m\"],".
				"\"action.filter\":[\"add\"],\"action.filter.conditions[98]\":[\"add\"],".
				"\"action.filter.conditions[98].conditiontype\":[\"add\",\"1\"],\"action.filter.conditions[98].value".
				"\":[\"add\",\"10084\"],\"action.filter.conditions[98].conditionid\":[\"add\",\"98\"],".
				"\"action.filter.conditions[99]\":[\"add\"],\"action.filter.conditions[99].conditiontype\":[".
				"\"add\",\"3\"],\"action.filter.conditions[99].operator\":[\"add\",\"2\"],".
				"\"action.filter.conditions[99].value\":[\"add\",\"memory\"],\"action.filter.conditions[99].conditionid".
				"\":[\"add\",\"99\"],\"action.operations[97]\":[\"add\"],\"action.operations[97].esc_period".
				"\":[\"add\",\"0s\"],\"action.operations[97].esc_step_to\":[\"add\",\"2\"],".
				"\"action.operations[97].opmessage_grp[97]\":[\"add\"],\"action.operations[97].opmessage_grp[97].usrgrpid".
				"\":[\"add\",\"7\"],\"action.operations[97].opmessage_grp[97].opmessage_grpid\":[\"add\",\"97".
				"\"],\"action.operations[97].opmessage\":[\"add\"],\"action.operations[97].opmessage.mediatypeid".
				"\":[\"add\",\"1\"],\"action.operations[97].operationid\":[\"add\",\"97\"],".
				"\"action.recovery_operations[98]\":[\"add\"],\"action.recovery_operations[98].operationtype".
				"\":[\"add\",\"11\"],\"action.recovery_operations[98].opmessage\":[\"add\"],".
				"\"action.recovery_operations[98].recovery\":[\"add\",\"1\"],\"action.recovery_operations[98].operationid".
				"\":[\"add\",\"98\"],\"action.update_operations[99]\":[\"add\"],\"action.update_operations[99].operationtype".
				"\":[\"add\",\"12\"],\"action.update_operations[99].opmessage\":[\"add\"],".
				"\"action.update_operations[99].opmessage.default_msg\":[\"add\",\"0\"],".
				"\"action.update_operations[99].opmessage.message\":[\"add\",\"Custom update operation message body".
				"\"],\"action.update_operations[99].opmessage.subject\":[\"add\",".
				"\"Custom update operation message subject\"],\"action.update_operations[99].recovery\":[\"add\",".
				"\"2\"],\"action.update_operations[99].operationid\":[\"add\",\"99\"],\"action.pause_suppressed\":[".
				"\"add\",\"0\"],\"action.notify_if_canceled\":[\"add\",\"0\"],\"action.actionid\":[\"add\",\"97\"]}";

		$create = $this->call('action.create', [
			[
				'name' => 'Audit action',
				'eventsource' => 0,
				'status' => 0,
				'esc_period' => '2m',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'conditiontype' => 1,
							'operator' => 0,
							'value' => '10084'
						],
						[
							'conditiontype' => 3,
							'operator' => 2,
							'value' => 'memory'
						]
					]
				],
				'operations' => [
					[
						'operationtype' => 0,
						'esc_period' => '0s',
						'esc_step_from' => 1,
						'esc_step_to' => 2,
						'evaltype' => 0,
						'opmessage_grp' => [
							[
								'usrgrpid' => '7'
							]
						],
						'opmessage' => [
							'default_msg' => 1,
							'mediatypeid' => '1'
						]
					]
				],
				'recovery_operations' => [
					[
						'operationtype' => '11',
						'opmessage' => [
							'default_msg' => 1
						]
					]
				],
				'update_operations' => [
					[
						'operationtype' => '12',
						'opmessage' => [
							'default_msg' => 0,
							'message' => 'Custom update operation message body',
							'subject' => 'Custom update operation message subject'
						]
					]
				],
				'pause_suppressed' => '0',
				'notify_if_canceled' => '0'
			]
		]);
		self::$resourceid = $create['result']['actionids'][0];

		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogAction_Update() {
		$updated = "{\"action.filter.conditions[98]\":[\"delete\"],\"action.filter.conditions[99]\":[\"delete\"],".
				"\"action.operations[97].opmessage_grp[97]\":[\"delete\"],\"action.filter.conditions[100]\":[\"add\"],".
				"\"action.operations[97].opmessage_grp[98]\":[\"add\"],\"action.name\":[\"update\",".
				"\"Updated action audit\",\"Audit action\"],\"action.status\":[\"update\",\"1\",\"0\"],".
				"\"action.esc_period\":[\"update\",\"15m\",\"2m\"],\"action.filter\":[\"update\"],".
				"\"action.filter.evaltype\":[\"update\",\"2\",\"0\"],\"action.filter.conditions[100].conditiontype".
				"\":[\"add\",\"3\"],\"action.filter.conditions[100].operator\":[\"add\",\"2\"],".
				"\"action.filter.conditions[100].value\":[\"add\",\"Trigger name\"],".
				"\"action.filter.conditions[100].conditionid\":[\"add\",\"100\"],\"action.operations[97]".
				"\":[\"update\"],\"action.operations[97].esc_period\":[\"update\",\"1000\",\"0s\"],".
				"\"action.operations[97].evaltype\":[\"update\",\"1\",\"0\"],".
				"\"action.operations[97].opmessage_grp[98].usrgrpid\":[\"add\",\"9\"],".
				"\"action.operations[97].opmessage_grp[98].opmessage_grpid\":[\"add\",\"98\"],".
				"\"action.operations[97].opmessage\":[\"update\"],\"action.operations[97].opmessage.default_msg".
				"\":[\"update\",\"0\",\"1\"],\"action.operations[97].opmessage.message\":[\"update\",".
				"\"Updated audit message\",\"\"],\"action.operations[97].opmessage.subject\":[\"update\",".
				"\"Updated audit message\",\"\"],\"action.pause_suppressed\":[\"update\",\"1\",\"0\"],".
				"\"action.notify_if_canceled\":[\"update\",\"1\",\"0\"]}";

		$this->call('action.update', [
			[
				'actionid' => self::$resourceid,
				'name' => 'Updated action audit',
				'status' => 1,
				'esc_period' => '15m',
				'filter' => [
					'evaltype' => 2,
					'conditions' => [
						[
							'conditiontype' => 3,
							'operator' => 2,
							'value' => 'Trigger name'
						]
					]
				],
				'operations' => [
					[
						'operationtype' => 0,
						'esc_period' => '1000',
						'esc_step_from' => 1,
						'esc_step_to' => 2,
						'evaltype' => 1,
						'opmessage_grp' => [
							[
								'usrgrpid' => '9'
							]
						],
						'opmessage' => [
							'default_msg' => 0,
							'message' => 'Updated audit message',
							'subject' => 'Updated audit message'
						]
					]
				],
				'pause_suppressed' => '1',
				'notify_if_canceled' => '1'
			]
		]);

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogAction_Delete() {
		$this->call('action.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcetype', 2, 5);
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
