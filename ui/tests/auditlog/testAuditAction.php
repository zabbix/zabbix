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
 * @backup actions, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditAction extends testPageReportsAuditValues {

	/**
	 * Id of action.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "action.actionid: 7".
			"\naction.esc_period: 2m".
			"\naction.filter: Added".
			"\naction.filter.conditions[10]: Added".
			"\naction.filter.conditions[10].conditionid: 10".
			"\naction.filter.conditions[10].conditiontype: 3".
			"\naction.filter.conditions[10].operator: 2".
			"\naction.filter.conditions[10].value: memory".
			"\naction.filter.conditions[9]: Added".
			"\naction.filter.conditions[9].conditionid: 9".
			"\naction.filter.conditions[9].conditiontype: 1".
			"\naction.filter.conditions[9].value: 10084".
			"\naction.name: Audit action".
			"\naction.notify_if_canceled: 0".
			"\naction.operations[11]: Added".
			"\naction.operations[11].esc_period: 0s".
			"\naction.operations[11].esc_step_to: 2".
			"\naction.operations[11].operationid: 11".
			"\naction.operations[11].opmessage: Added".
			"\naction.operations[11].opmessage.mediatypeid: 1".
			"\naction.operations[11].opmessage_grp[5]: Added".
			"\naction.operations[11].opmessage_grp[5].opmessage_grpid: 5".
			"\naction.operations[11].opmessage_grp[5].usrgrpid: 7".
			"\naction.pause_suppressed: 0".
			"\naction.recovery_operations[12]: Added".
			"\naction.recovery_operations[12].operationid: 12".
			"\naction.recovery_operations[12].operationtype: 11".
			"\naction.recovery_operations[12].opmessage: Added".
			"\naction.recovery_operations[12].recovery: 1".
			"\naction.update_operations[13]: Added".
			"\naction.update_operations[13].operationid: 13".
			"\naction.update_operations[13].operationtype: 12".
			"\naction.update_operations[13].opmessage: Added".
			"\naction.update_operations[13].opmessage.default_msg: 0".
			"\naction.update_operations[13].opmessage.message: Custom update operation message body".
			"\naction.update_operations[13].opmessage.subject: Custom update operation message subject".
			"\naction.update_operations[13].recovery: 2";

	public $updated = "action.esc_period: 2m => 15m".
			"\naction.filter: Updated".
			"\naction.filter.conditions[10]: Deleted".
			"\naction.filter.conditions[11]: Added".
			"\naction.filter.conditions[11].conditionid: 11".
			"\naction.filter.conditions[11].conditiontype: 3".
			"\naction.filter.conditions[11].operator: 2".
			"\naction.filter.conditions[11].value: Trigger name".
			"\naction.filter.conditions[9]: Deleted".
			"\naction.filter.evaltype: 0 => 2".
			"\naction.name: Audit action => Updated action audit".
			"\naction.notify_if_canceled: 0 => 1".
			"\naction.operations[11]: Updated".
			"\naction.operations[11].esc_period: 0s => 1000".
			"\naction.operations[11].evaltype: 0 => 1".
			"\naction.operations[11].opmessage: Updated".
			"\naction.operations[11].opmessage.default_msg: 1 => 0".
			"\naction.operations[11].opmessage.message:  => Updated audit message".
			"\naction.operations[11].opmessage.subject:  => Updated audit message".
			"\naction.operations[11].opmessage_grp[5]: Deleted".
			"\naction.operations[11].opmessage_grp[6]: Added".
			"\naction.operations[11].opmessage_grp[6].opmessage_grpid: 6".
			"\naction.operations[11].opmessage_grp[6].usrgrpid: 9".
			"\naction.pause_suppressed: 0 => 1".
			"\naction.status: 0 => 1";

	public $deleted = 'Description: Updated action audit';

	public $resource_name = 'Action';

	public function prepareCreateData() {
		$ids = CDataHelper::call('action.create', [
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
		$this->assertArrayHasKey('actionids', $ids);
		self::$ids = $ids['actionids'][0];
	}

	/**
	 * Check audit of created action.
	 */
	public function testAuditAction_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

	/**
	 * Check audit of updated action.
	 */
	public function testAuditAction_Update() {
		CDataHelper::call('action.update', [
			[
				'actionid' => self::$ids,
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

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of deleted action.
	 */
	public function testAuditAction_Delete() {
		CDataHelper::call('action.delete', [self::$ids]);

		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
