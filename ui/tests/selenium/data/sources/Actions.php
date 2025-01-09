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


class Actions {

	const ZABBIX_ADMIN_GROUPID = 7;
	const ADMIN_USERID = 1;
	const EMAIL_MEDIATYPEID = 1;
	const CURRENT_HOST = 0;

	/**
	 * Create data for all Actions related tests (also used in Media types, Reports and Services tests, etc).
	 *
	 * @return array
	 */
	public static function load() {
		CDataHelper::call('proxy.create',
			[
				[
					'name' => 'Proxy for Actions 1',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				],
				[
					'name' => 'Proxy for Actions 2',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				]
			]
		);
		$proxyids = CDataHelper::getIds('name');

		$scripts = CDataHelper::call('script.create', [
			[
				'name' => 'Reboot',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'scope' => ZBX_SCRIPT_SCOPE_ACTION,
				'command' => '/sbin/shutdown -r',
				'groupid' => 4, // Zabbix servers.
				'description' => 'This command reboots server.'
			]
		]);
		$reboot_scriptid = $scripts['scriptids'][0];

		CDataHelper::call('action.create', [
			// Service action.
			[
				'name' => 'Service action',
				'eventsource' => EVENT_SOURCE_SERVICE,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_SERVICE_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'Service name'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'Service tag name'
						]
					]
				],
				'operations' => [
					[
						'operationtype' => ZBX_CONDITION_TYPE_HOST_GROUP,
						'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
						'opmessage_usr' => [['userid' => self::ADMIN_USERID]]
					]
				],
				'recovery_operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 0,
							'subject' => 'Subject',
							'message' => 'Message',
							'mediatypeid' => self::EMAIL_MEDIATYPEID
						],
						'opmessage_usr' => [['userid' => self::ADMIN_USERID]]
					]
				],
				'update_operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					]
				]
			],
			// Trigger actions.
			[
				'name' => 'Minimal trigger action',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => []
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_COMMAND,
						'opcommand' => ['scriptid' => $reboot_scriptid],
						'opcommand_hst' => [['hostid' => self::CURRENT_HOST]]
					]
				]
			],
			[
				'name' => 'All conditions trigger action',
				'esc_period' => '60s',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'value' => 'PostgreSQL',
							'value2' => 'Database'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'MYSQL',
							'value2' => 'Database'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TAG_VALUE,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => '',
							'value2' => 'MySQL'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_SUPPRESSED,
							'operator' => CONDITION_OPERATOR_YES
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 10081 // Windows by Zabbix agent.
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 10001 // Linux by Zabbix agent.
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TIME_PERIOD,
							'operator' => CONDITION_OPERATOR_NOT_IN,
							'value' => '6-7,08:00-18:00'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TIME_PERIOD,
							'operator' => CONDITION_OPERATOR_IN,
							'value' => '1-7,00:00-24:00'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_LESS_EQUAL,
							'value' => TRIGGER_SEVERITY_HIGH
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_MORE_EQUAL,
							'value' => TRIGGER_SEVERITY_AVERAGE
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => TRIGGER_SEVERITY_WARNING
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => TRIGGER_SEVERITY_DISASTER
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER_SEVERITY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => TRIGGER_SEVERITY_INFORMATION
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_EVENT_NAME,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'value' => 'DB2'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_EVENT_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'Oracle'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 13485 // Utilization of unreachable poller processes is high.
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 99252 // First test trigger with tag priority.
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 10084 // ЗАББИКС Сервер.
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 99134 // Available host.
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST_GROUP,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 4 // Zabbix servers.
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST_GROUP,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => 2 // Linux servers.
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
								'conditiontype' => ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => '0' // Acknowledged - NO.
							],
							[
								'conditiontype' => ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => '1' // Acknowledged - YES.
							]
						],
						'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					],
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'esc_step_from' => 5,
						'esc_step_to' => 6,
						'opconditions' => [
							[
								'conditiontype' => ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => '0' // Acknowledged - NO.
							]
						],
						'opmessage' => [
							'default_msg' => 0,
							'subject' => 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}',
							'message' => 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',
							'mediatypeid' => self::EMAIL_MEDIATYPEID
						],
						'opmessage_usr' => [['userid' => self::ADMIN_USERID]]
					]
				]
			],
			// Autoregistration actions.
			[
				'name' => 'Autoregistration action 1',
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'value' => 'DB2'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'MYSQL'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => $proxyids['Proxy for Actions 1']
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => $proxyids['Proxy for Actions 2']
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
							'mediatypeid' => self::EMAIL_MEDIATYPEID
						],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					],
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					],
					[
						'operationtype' => OPERATION_TYPE_COMMAND,
						'opcommand' => ['scriptid' => $reboot_scriptid],
						'opcommand_hst' => [['hostid' => self::CURRENT_HOST]]
					],
					[
						'operationtype' => OPERATION_TYPE_GROUP_ADD,
						'opgroup' => [['groupid' => 5]] // Discovered hosts.
					],
					[
						'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
						'optemplate' => [['templateid' => 10001]] // Linux by Zabbix agent.
					]
				]
			],
			[
				'name' => 'Autoregistration action 2',
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'status' => ACTION_STATUS_DISABLED,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'value' => 'DB2'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => 'MYSQL'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => $proxyids['Proxy for Actions 1']
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => $proxyids['Proxy for Actions 2']
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
							'mediatypeid' => self::EMAIL_MEDIATYPEID
						],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					],
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					],
					[
						'operationtype' => OPERATION_TYPE_COMMAND,
						'opcommand' => ['scriptid' => $reboot_scriptid],
						'opcommand_hst' => [['hostid' => self::CURRENT_HOST]]
					]
				]
			],
			[
				'name' => 'Simple action',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => []
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					]
				]
			],
			[
				'name' => 'Trigger action 2',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => []
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => self::EMAIL_MEDIATYPEID],
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
					]
				]
			],
			[
				'name' => 'Trigger action 3',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => []
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => 3], // SMS.
						'opmessage_grp' => [['usrgrpid' => self::ZABBIX_ADMIN_GROUPID]]
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

		// Add Actions to Action Log in database.
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged, ns) VALUES '.
				' (101, 0, 0, 13545, 1329724790, 1, 0, 0);'
		);

		DBexecute('INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject,'.
				' message, status, retries, error, esc_step, alerttype, parameters) VALUES '.
				'(1, '.zbx_dbstr($actionids['Trigger action 2']).', 101, 1, 1329724800, 1, \'igor.danoshaites@zabbix.com\','.
				'\'PROBLEM: Value of item key1 > 5\', \'Event at 2012.02.20 10:00:00 Hostname: H1 Value of item key1 > 5:'.
				' PROBLEM Last value: 6\', 1, 0, \'\', 1, 0, \'\'),'.
				'(2, '.zbx_dbstr($actionids['Trigger action 2']).', 101, 1, 1329724810, 1, \'igor.danoshaites@zabbix.com\', '.
				'\'PROBLEM: Value of item key1 > 6\',\'Event at 2012.02.20 10:00:10 Hostname: H1 Value of item key1 > 6:'.
				' PROBLEM\', 1, 0, \'\', 1, 0, \'\'),'.
				'(3, '.zbx_dbstr($actionids['Trigger action 2']).', 101, 1, 1329724820, 1, \'igor.danoshaites@zabbix.com\','.
				'\'PROBLEM: Value of item key1 > 7\', \'Event at 2012.02.20 10:00:20 Hostname: H1 Value of item key1 > 7:'.
				' PROBLEM\', 1, 0, \'\', 1, 0, \'\'),'.
				'(4, '.zbx_dbstr($actionids['Trigger action 2']).', 101, 1, 1329724830, 1, \'igor.danoshaites@zabbix.com\','.
				'\'PROBLEM: Value of item key1 > 10\', \'Event at 2012.02.20 10:00:30 Hostname: H1 Value of item key1 > 10: PROBLEM\','.
				' 2, 0, \'Get value from agent failed: cannot connect to [[127.0.0.1]:10050]: [111] Connection refused\', 1, 0, \'\'),'.
				'(5, '.zbx_dbstr($actionids['Trigger action 2']).', 101, 1, 1329724840, 1, \'igor.danoshaites@zabbix.com\', '.
				'\'PROBLEM: Value of item key1 > 20\', \'Event at 2012.02.20 10:00:40 Hostname: H1 Value of item key1 > 20:'.
				' PROBLEM\', 0, 0, \'Get value from agent failed: cannot connect to [[127.0.0.1]:10050]: '.
				'[111] Connection refused\', 1, 0, \'\'),'.
				'(6, '.zbx_dbstr($actionids['Trigger action 2']).', 101, NULL, 1329724850, NULL, \'\', \'\','.
				'\'Command: H1:ls -la\', 1, 0, \'\', 1, 1, \'\'),'.
				'(7, '.zbx_dbstr($actionids['Trigger action 2']).', 101, NULL, 1329724860, NULL, \'\', \'\','.
				'\'Command: H1:ls -la\', 1, 0, \'\', 1, 1, \'\'),'.
				'(130, '.zbx_dbstr($actionids['Trigger action 2']).', 101, 9, 1597440000, 3, \'igor.danoshaites@zabbix.com\','.
				'\'time_subject_2\', \'time_message_\', 1, 0, \'\', 1, 0, \'\'),'.
				'(131, '.zbx_dbstr($actionids['Trigger action 3']).', 101, 1, 1329724870, 10, \'test.test@zabbix.com\','.
				'\'subject here\', \'message here\', 1, 0, \'\', 1, 0, \'\'),'.
				'(132, '.zbx_dbstr($actionids['Trigger action 3']).', 101, 9, 1329724880, 3, \'77777777\', \'subject here\','.
				'\'message here\', 1, 0, \'\', 1, 0, \'\'),'.
				'(133, '.zbx_dbstr($actionids['Trigger action 3']).', 101, 9, 1329724890, 3, \'77777777\', \'subject_no_space\','.
				'\'message_no_space\', 1, 0, \'\', 1, 0, \'\'),'.
				'(134, '.zbx_dbstr($actionids['Trigger action 3']).', 101, 1, 1597439400, 3, \'igor.danoshaites@zabbix.com\','.
				'\'time_subject_1\', \'time_message_1\', 1, 0, \'\', 1, 0, \'\')'
		);

		return $actionids;
	}
}
