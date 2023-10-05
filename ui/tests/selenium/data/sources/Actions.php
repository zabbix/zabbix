<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class Actions {

	public static function load() {
		CDataHelper::call('action.create', [
			// Service action.
			[
				'name' => 'Service action',
				'eventsource' => EVENT_SOURCE_SERVICE,
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'conditiontype' => CONDITION_TYPE_SERVICE_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'Service name'
						],
						[
							'conditiontype' => CONDITION_TYPE_EVENT_TAG,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'Service tag name'
						]
					]
				],
				'operations' => [
					[
						'operationtype' => CONDITION_TYPE_HOST_GROUP,
						'opmessage' => ['mediatypeid' => 0],
						'opmessage_usr' => [['userid' => 1]]
					]
				],
				'recovery_operations' => [
					[
						'operationtype' => OPERATION_TYPE_RECOVERY_MESSAGE,
						'opmessage' => [
							'default_msg' => 0,
							'subject' => 'Subject',
							'message' => 'Message'
						]
					]
				],
				'update_operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => 1],
						'opmessage_grp' => [['usrgrpid' => 7]]
					]
				]
			],
			// Trigger actions.
			[
				'name' => 'Minimal trigger action',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => 0,
					'conditions' => []
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_COMMAND,
						'opcommand' => ['scriptid' => 4],
						'opcommand_hst' => [['hostid' => 0]]
					]
				]
			],
			[
				'name' => 'All conditions trigger action',
				'esc_period' => '60s',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'conditiontype' => CONDITION_TYPE_EVENT_TAG_VALUE,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'value' => 'PostgreSQL',
							'value2' => 'Database'
						],
						[
							'conditiontype' => CONDITION_TYPE_EVENT_TAG_VALUE,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'MYSQL',
							'value2' => 'Database'
						],
						[
							'conditiontype' => CONDITION_TYPE_EVENT_TAG_VALUE,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => '',
							'value2' => 'MySQL'
						],
						[
							'conditiontype' => CONDITION_TYPE_SUPPRESSED,
							'operator' => CONDITION_OPERATOR_YES
						],
						[
							'conditiontype' => CONDITION_TYPE_TEMPLATE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 10081
						],
						[
							'conditiontype' => CONDITION_TYPE_TEMPLATE,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 10001
						],
						[
							'conditiontype' => CONDITION_TYPE_TIME_PERIOD,
							'operator' => CONDITION_OPERATOR_NOT_IN,
							'value' => '6-7,08:00-18:00'
						],
						[
							'conditiontype' => CONDITION_TYPE_TIME_PERIOD,
							'operator' => CONDITION_OPERATOR_IN,
							'value' => '1-7,00:00-24:00'
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_LESS_EQUAL,
							'value' => 4
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_MORE_EQUAL,
							'value' => 3
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 2
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 5
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 1
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER_NAME,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'value' => 'DB2'
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'Oracle'
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 13485
						],
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 99252
						],
						[
							'conditiontype' => CONDITION_TYPE_HOST,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 10084
						],
						[
							'conditiontype' => CONDITION_TYPE_HOST,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 99134
						],
						[
							'conditiontype' => CONDITION_TYPE_HOST_GROUP,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 4
						],
						[
							'conditiontype' => CONDITION_TYPE_HOST_GROUP,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 2
						]
					]
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'esc_period' => 3600,
						'esc_step_from' => 2,
						'esc_step_to' => 2,
						'opconditions' => [
							[
								'conditiontype' => CONDITION_TYPE_EVENT_ACKNOWLEDGED,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => "0"
							],
							[
								'conditiontype' => CONDITION_TYPE_EVENT_ACKNOWLEDGED,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => "1"
							]
						],
						'opmessage' => ['mediatypeid' => 1],
						'opmessage_grp' => [['usrgrpid' => 7]]
					],
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'esc_step_from' => 5,
						'esc_step_to' => 6,
						'opconditions' => [
							[
								'conditiontype' => CONDITION_TYPE_EVENT_ACKNOWLEDGED,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => "0"
							]
						],
						'opmessage' => [
							'default_msg' => 0,
							'subject' => 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}',
							'message' => 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',
							'mediatypeid' => 1
						],
						'opmessage_usr' => [['userid' => 1]]
					]
				]
			],
			// Autoregistration actions.
			[
				'name' => 'Autoregistation action 1',
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'conditiontype' => CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'value' => 'DB2'
						],
						[
							'conditiontype' => CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'MYSQL'
						],
						[
							'conditiontype' => CONDITION_TYPE_PROXY,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 20001
						],
						[
							'conditiontype' => CONDITION_TYPE_PROXY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 20000
						]
					]
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 0,
							'subject' => 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}',
							'message' => 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',
							'mediatypeid' => 0
						],
						'opmessage_grp' => [['usrgrpid' => 7]]
					],
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => 0],
						'opmessage_grp' => [['usrgrpid' => 7]]
					],
					[
						'operationtype' => OPERATION_TYPE_COMMAND,
						'opcommand' => ['scriptid' => 4],
						'opcommand_hst' => [['hostid' => 0]]
					],
					[
						'operationtype' => OPERATION_TYPE_GROUP_ADD,
						'opgroup' => [['groupid' => 5]]
					],
					[
						'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
						'optemplate' => [['templateid' => 10001]]
					]
				]
			],
			[
				'name' => 'Autoregistation action 2',
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'status' => ACTION_STATUS_DISABLED,
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'conditiontype' => CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'value' => 'DB2'
						],
						[
							'conditiontype' => CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'MYSQL'
						],
						[
							'conditiontype' => CONDITION_TYPE_PROXY,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 20001
						],
						[
							'conditiontype' => CONDITION_TYPE_PROXY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 20000
						]
					]
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 0,
							'subject' => 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}',
							'message' => 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',
							'mediatypeid' => 0
						],
						'opmessage_grp' => [['usrgrpid' => 7]]
					],
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => 0],
						'opmessage_grp' => [['usrgrpid' => 7]]
					],
					[
						'operationtype' => OPERATION_TYPE_COMMAND,
						'opcommand' => ['scriptid' => 4],
						'opcommand_hst' => [['hostid' => 0]]
					]
				]
			]
		]);
		$actionids = CDataHelper::getIds('name');

		CDataHelper::call('service.create', [
			[
				'name' => 'Reference service',
				'algorithm' => 1,
				'sortorder' => 1
			]
		]);

		return $actionids;
	}
}
