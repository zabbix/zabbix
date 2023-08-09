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


class NetworkDiscovery {

	/**
	 * Create data for Network Discovery tests.
	 *
	 * @return array
	 */
	public static function load() {
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
				'status' => 1,
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
				'status' => 1,
				'dchecks' => [
					[
						// SNMPv1 agent.
						'type' => SVC_SNMPv1,
						'ports' => 161,
						'key_' => '.1.3.6.1.2.1.9.9.9',
						'snmp_community'=> 'test SNMP community'
					],
					[
						'type' => SVC_SNMPv3,
						'ports' => 162,
						'key_' => '.1.3.6.1.2.1.1.1.0',
						'snmpv3_contextname name' => 'test_context_name',
						'snmpv3_securityname' => 'test_security_name',
						'snmpv3_securitylevel' => 0
					],
					[
						'type' => SVC_TELNET,
						'ports' => 23
					]
				]
			],
			[
				'name' => 'Discovery rule for clone',
				'iprange' => '192.168.2.3-255',
				'dchecks' => [
					[
						'type' => SVC_LDAP,
						'ports' => 555
					],
					[
						'type' => SVC_TCP,
						'ports' => 9988
					],
					[
						'type' => SVC_SNMPv1,
						'ports' => 165,
						'key_' => '.1.9.6.1.10.1.9.9.9',
						'snmp_community'=> 'original SNMP community',
						'uniq' => 1
					],
					[
						'type' => SVC_SNMPv3,
						'ports' => 130,
						'key_' => '.1.3.6.1.2.1.1.1.999',
						'snmpv3_contextname name' => 'original_context_name',
						'snmpv3_securityname' => 'original_security_name',
						'snmpv3_securitylevel' => 2,
						'snmpv3_authprotocol' => 4,
						'snmpv3_authpassphrase' => 'original_authpassphrase',
						'snmpv3_privprotocol' => 5,
						'snmpv3_privpassphrase' => 'original_privpassphrase'
					]
				]
			],
			[
				'name' => 'Discovery rule for successful deleting',
				'iprange' => '192.168.1.1-255',
				'status' => 1,
				'dchecks' => [
					[
						'type' => SVC_ICMPPING
					]
				]
			],
			[
				'name' => 'Discovery rule for deleting, used in Action',
				'iprange' => '192.168.2.2-255',
				'status' => 1,
				'dchecks' => [
					[
						'type' => SVC_IMAP,
						'ports' => 2050
					],
				]
			],
			[
				'name' => 'Discovery rule for deleting, check used in Action',
				'iprange' => '192.168.2.2-255',
				'status' => 1,
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
			]
		]);
		$discovery_ruleids = CDataHelper::getIds('name');
		$check_id = CDBHelper::getValue('SELECT dcheckid FROM dchecks WHERE druleid='
				.zbx_dbstr($discovery_ruleids['Discovery rule for deleting, check used in Action'])
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
							'conditiontype' => 19,
							'operator' => 0,
							'value' => $check_id
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
