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


class NetworkDiscovery {

	/**
	 * Create data for Network Discovery tests.
	 *
	 * @return array
	 */
	public static function load() {
		// Create proxies for Discovery rule with proxy.
		CDataHelper::call('proxy.create',
			[
				[
					'name' => 'Test Proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				],
				[
					'name' => 'Proxy for Network discovery',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				],
				[
					'name' => 'Proxy for cloning Network discovery',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				]
			]
		);
		$proxyids = CDataHelper::getIds('name');

		CDataHelper::call('drule.create', [
			[
				'name' => 'Discovery rule for update',
				'iprange' => '192.168.1.1-255',
				'dchecks' => [
					[
						'type' => SVC_IMAP,
						'ports' => 10050
					],
					[
						'type' => SVC_POP,
						'ports' => 99
					],
					[
						'type' => SVC_AGENT,
						'key_' => 'system.uname',
						'ports' => 10050,
						'uniq' => 1
					]
				]
			],
			[
				'name' => 'Disabled discovery rule for update',
				'iprange' => '192.168.1.1-255',
				'status' => DRULE_STATUS_DISABLED,
				'dchecks' => [
					[
						'type' => SVC_IMAP,
						'ports' => 10050
					]
				]
			],
			[
				'name' => 'Discovery rule for changing checks',
				'iprange' => '192.168.1.1-255',
				'status' => DRULE_STATUS_DISABLED,
				'dchecks' => [
					[
						// SNMPv1 agent.
						'type' => SVC_SNMPv1,
						'ports' => 161,
						'key_' => '.1.3.6.1.2.1.9.9.9',
						'snmp_community'=> 'test SNMP community',
						'host_source' => 3,
						'name_source' => 2
					],
					[
						'type' => SVC_SNMPv3,
						'ports' => 162,
						'key_' => '.1.3.6.1.2.1.1.1.0',
						'snmpv3_contextname name' => 'test_context_name',
						'snmpv3_securityname' => 'test_security_name',
						'snmpv3_securitylevel' => 0,
						'name_source' => 2,
						'uniq' => 1
					],
					[
						'type' => SVC_TELNET,
						'ports' => 23,
						'name_source' => 2
					]
				]
			],
			[
				'name' => 'Discovery rule for clone',
				'iprange' => '192.168.2.3-255',
				'proxyid' => $proxyids['Proxy for Network discovery'],
				'delay' => '25h',
				'status' =>  1,
				'concurrency_max' => 0,
				'dchecks' => [
					[
						'type' => SVC_LDAP,
						'ports' => 555,
						'name_source' => 2
					],
					[
						'type' => SVC_TCP,
						'ports' => 9988,
						'name_source' => 2
					],
					[
						'type' => SVC_SNMPv1,
						'ports' => 165,
						'key_' => '.1.9.6.1.10.1.9.9.9',
						'snmp_community'=> 'original SNMP community',
						'uniq' => 1,
						'name_source' => 2
					],
					[
						'type' => SVC_SNMPv3,
						'ports' => 130,
						'key_' => '.1.3.6.1.2.1.1.1.999',
						'snmpv3_contextname' => 'original_context_name',
						'snmpv3_securityname' => 'original_security_name',
						'snmpv3_securitylevel' => 2,
						'snmpv3_authprotocol' => 4,
						'snmpv3_authpassphrase' => 'original_authpassphrase',
						'snmpv3_privprotocol' => 5,
						'snmpv3_privpassphrase' => 'original_privpassphrase',
						'host_source' => 3,
						'name_source' => 2
					]
				]
			],
			[
				'name' => 'Discovery rule for successful deleting',
				'iprange' => '192.168.1.1-255',
				'status' => DRULE_STATUS_DISABLED,
				'dchecks' => [
					[
						'type' => SVC_ICMPPING
					]
				]
			],
			[
				'name' => 'Discovery rule for deleting, used in Action',
				'iprange' => '192.168.2.2-255',
				'status' => DRULE_STATUS_DISABLED,
				'dchecks' => [
					[
						'type' => SVC_IMAP,
						'ports' => 2050
					]
				]
			],
			[
				'name' => 'Discovery rule for deleting, check used in Action',
				'iprange' => '192.168.2.2-255',
				'status' => DRULE_STATUS_DISABLED,
				'dchecks' => [
					[
						'type' => SVC_TELNET,
						'ports' => 15
					]
				]
			],
			[
				'name' => 'Discovery rule for cancelling scenario',
				'iprange' => '192.168.15.20-255',
				'dchecks' => [
					[
						'type' => SVC_SNMPv3,
						'ports' => 130,
						'key_' => '.1.3.6.1.2.1.1.1.999',
						'snmpv3_contextname name' => 'cancel_context_name',
						'snmpv3_securityname' => 'cancel_security_name',
						'snmpv3_securitylevel' => 2,
						'snmpv3_authprotocol' => 4,
						'snmpv3_authpassphrase' => 'cancel_authpassphrase',
						'snmpv3_privprotocol' => 5,
						'snmpv3_privpassphrase' => 'cancel_privpassphrase'
					]
				]
			],
			[
				'name' => 'External network',
				'iprange' => '192.168.3.1-255',
				'delay' => 600,
				'dchecks' => [
					[
						'type' => SVC_AGENT,
						'key_' => 'system.uname',
						'ports' => 10050,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_FTP,
						'ports' => '21,1021',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_HTTP,
						'ports' => '80,8080',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_ICMPPING,
						'ports' => 0,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_IMAP,
						'ports' => '143-145',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_LDAP,
						'ports' => 389,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_NNTP,
						'ports' => 119,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_POP,
						'ports' => 110,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SMTP,
						'ports' => 25,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SNMPv1,
						'key_' => 'ifIndex0',
						'snmp_community' => 'public',
						'ports' => 161,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SNMPv2c,
						'key_' => 'ifInOut0',
						'snmp_community' => 'private1',
						'ports' => 162,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SNMPv3,
						'key_' => 'ifIn0',
						'ports' => 161,
						'snmpv3_securityname' => 'private2',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_SSH,
						'ports' => 22,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_TCP,
						'ports' => '10000-20000',
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_TELNET,
						'ports' => 23,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					],
					[
						'type' => SVC_AGENT,
						'key_' => 'agent.uname',
						'ports' => 10050,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					]
				]
			],
			[
				'name' => 'Discovery rule to check delete',
				'iprange' => '192.168.3.1-255',
				'proxyid' => $proxyids['Test Proxy'],
				'delay' => 600,
				'status' => DRULE_STATUS_DISABLED,
				'dchecks' => [
					[
						'type' => SVC_ICMPPING,
						'ports' => 0,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					]
				]
			],
			[
				'name' => "<img src=\"x\" onerror=\"alert('UWAGA');\"/>",
				'iprange' => '192.168.3.1-255',
				'proxyid' => $proxyids['Test Proxy'],
				'delay' => 600,
				'status' => DRULE_STATUS_DISABLED,
				'dchecks' => [
					[
						'type' => SVC_ICMPPING,
						'ports' => 0,
						'snmpv3_securitylevel' => 0,
						'uniq' => 0
					]
				]
			]
		]);

		$discovery_ruleids = CDataHelper::getIds('name');
		$check_id_delete = CDBHelper::getValue('SELECT dcheckid FROM dchecks WHERE druleid='
				.zbx_dbstr($discovery_ruleids['Discovery rule for deleting, check used in Action'])
		);
		$check_id_cancel = CDBHelper::getValue('SELECT dcheckid FROM dchecks WHERE druleid='
				.zbx_dbstr($discovery_ruleids['Discovery rule for cancelling scenario'])
		);

		CDataHelper::call('action.create', [
			[
				'name' => 'Action with discovery rule',
				'eventsource' => 1,
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'conditiontype' => 18,
							'operator' => 0,
							'value' => $discovery_ruleids['Discovery rule for deleting, used in Action']
						]
					]
				],
				'operations' => [
					[
						'operationtype' => 0,
						'opmessage' => [
							'default_msg' => 1,
							'mediatypeid' => 0
						],
						'opmessage_usr' => [['userid' => 1]]
					]
				]
			],
			[
				'name' => 'Action with discovery check',
				'eventsource' => 1,
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_DCHECK,
							'operator' => 0,
							'value' => $check_id_delete
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_DCHECK,
							'operator' => 0,
							'value' => $check_id_cancel
						]
					]
				],
				'operations' => [
					[
						'operationtype' => 0,
						'opmessage' => [
							'default_msg' => 1,
							'mediatypeid' => 0
						],
						'opmessage_usr' => [['userid' => 1]]
					]
				]
			]
		]);
	}
}
