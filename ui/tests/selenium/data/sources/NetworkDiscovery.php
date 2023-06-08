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
						// IMAP.
						'type' => 7,
						'ports' => 10050
					],
					[
						// POP.
						'type' => 5,
						'ports' => 99
					],
					[
						// Zabbix agent.
						'type' => 9,
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
						// IMAP.
						'type' => 7,
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
						'type' => 10,
						'ports' => 161,
						'key_' => '.1.3.6.1.2.1.9.9.9',
						'snmp_community'=> 'test SNMP community',
						'uniq' => 1
					],
					[
						// SNMPv3 agent.
						'type' => 13,
						'ports' => 162,
						'key_' => '.1.3.6.1.2.1.1.1.0',
						'snmpv3_contextname name' => 'test_context_name',
						'snmpv3_securityname' => 'test_security_name',
						'snmpv3_securitylevel' => 0
					],
					[
						// HTTPS.
						'type' => 15,
						'ports' => 23
					]
				]
			],
			[
				'name' => 'Discovery rule for clone',
				'iprange' => '192.168.2.3-255',
				'dchecks' => [
					[
						// LDAP.
						'type' => 1,
						'ports' => 555
					],
					[
						// TCP.
						'type' => 8,
						'ports' => 9988
					],
					[
						// SNMPv1 agent.
						'type' => 10,
						'ports' => 165,
						'key_' => '.1.9.6.1.10.1.9.9.9',
						'snmp_community'=> 'original SNMP community',
						'uniq' => 1
					],
					[
						// SNMPv3 agent.
						'type' => 13,
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
				'name' => 'Discovery rule to check delete',
				'iprange' => '192.168.1.1-255',
				'status' => 1,
				'dchecks' => [
					[
						'type' => 12
					]
				]
			],
			[
				'name' => 'Discovery rule for cancelling scenario',
				'iprange' => '192.168.15.20-255',
				'dchecks' => [
					[
						// SNMPv3 agent.
						'type' => 13,
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
	}
}

