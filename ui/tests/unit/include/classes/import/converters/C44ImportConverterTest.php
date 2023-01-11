<?php declare(strict_types=1);
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


class C44ImportConverterTest extends CImportConverterTest {

	public function dataProviderConvert() {
		return [
			[
				[],
				[]
			],
			[
				[
					'templates' => [
						[
							'items' => [
								[
									'type' => 'SNMPV1',
									'snmp_community' => 'public',
									'snmp_oid' => 'test',
									'port' => '',
									'snmpv3_contextname' => '',
									'snmpv3_securityname' => 'test',
									'snmpv3_securitylevel' => '0',
									'snmpv3_authprotocol' => '0',
									'snmpv3_authpassphrase' => '',
									'snmpv3_privprotocol' => '0',
									'snmpv3_privpassphrase' => ''
								]
							],
							'discovery_rules' => [
								[
									'type' => 'SNMPV2',
									'snmp_community' => 'public',
									'snmp_oid' => 'test',
									'port' => '',
									'snmpv3_contextname' => '',
									'snmpv3_securityname' => 'test',
									'snmpv3_securitylevel' => '0',
									'snmpv3_authprotocol' => '0',
									'snmpv3_authpassphrase' => '',
									'snmpv3_privprotocol' => '0',
									'snmpv3_privpassphrase' => '',
									'item_prototypes' => [
										[
											'type' => 'SNMPV3',
											'snmp_community' => '',
											'snmp_oid' => 'test',
											'port' => '162',
											'snmpv3_contextname' => 'test',
											'snmpv3_securityname' => 'test',
											'snmpv3_securitylevel' => '0',
											'snmpv3_authprotocol' => '1',
											'snmpv3_authpassphrase' => 'test',
											'snmpv3_privprotocol' => '0',
											'snmpv3_privpassphrase' => 'test'
										]
									]
								]
							]
						]
					]
				],
				[
					'templates' => [
						[
							'items' => [
								[
									'type' => 'SNMP_AGENT',
									'snmp_oid' => 'test'
								]
							],
							'discovery_rules' => [
								[
									'type' => 'SNMP_AGENT',
									'snmp_oid' => 'test',
									'item_prototypes' => [
										[
											'type' => 'SNMP_AGENT',
											'snmp_oid' => 'test'
										]
									]
								]
							]
						]
					]
				]
			],
			[
				[
					'hosts' => [
						[
							'interfaces' => [
								[
									'interface_ref' => 'if1',
									'ip' => '127.0.0.1',
									'type' => 'SNMP',
									'dns' => '',
									'port' => '161',
									'useip' => 'YES',
									'default' => 'YES',
									'bulk' => 'NO'
								]
							],
							'items' => [
								[
									'interface_ref' => 'if1',
									'key' => 'test1',
									'type' => 'SNMPV3',
									'snmp_community' => 'test',
									'snmp_oid' => 'test',
									'port' => '162',
									'snmpv3_contextname' => 'test',
									'snmpv3_securityname' => 'test',
									'snmpv3_securitylevel' => 'AUTHPRIV',
									'snmpv3_authprotocol' => 'SHA',
									'snmpv3_authpassphrase' => 'test',
									'snmpv3_privprotocol' => 'AES',
									'snmpv3_privpassphrase' => 'test'
								]
							],
							'discovery_rules' => [
								[
									'interface_ref' => 'if1',
									'key' => 'test2',
									'type' => 'SNMPV2',
									'snmp_community' => 'test',
									'snmp_oid' => 'test',
									'port' => '163',
									'snmpv3_contextname' => 'test',
									'snmpv3_securityname' => 'test',
									'snmpv3_securitylevel' => 'AUTHPRIV',
									'snmpv3_authprotocol' => 'SHA',
									'snmpv3_authpassphrase' => 'test',
									'snmpv3_privprotocol' => 'AES',
									'snmpv3_privpassphrase' => 'test',
									'item_prototypes' => [
										[
											'interface_ref' => 'if1',
											'key' => 'test3',
											'type' => 'SNMPV1',
											'snmp_community' => 'test',
											'snmp_oid' => 'test',
											'port' => '',
											'snmpv3_contextname' => 'test',
											'snmpv3_securityname' => 'test',
											'snmpv3_securitylevel' => 'AUTHPRIV',
											'snmpv3_authprotocol' => 'SHA',
											'snmpv3_authpassphrase' => 'test',
											'snmpv3_privprotocol' => 'AES',
											'snmpv3_privpassphrase' => 'test'
										]
									]
								]
							]
						]
					]
				],
				[
					'hosts' => [
						[
							'interfaces' => [
								1 => [
									'interface_ref' => 'if2',
									'ip' => '127.0.0.1',
									'type' => 'SNMP',
									'dns' => '',
									'port' => '161',
									'useip' => 'YES',
									'default' => 'YES',
									'details' => [
										'bulk' => 'NO',
										'version' => 'SNMPV3',
										'contextname' => 'test',
										'securityname' => 'test',
										'securitylevel' => 'AUTHPRIV',
										'authprotocol' => 'SHA',
										'authpassphrase' => 'test',
										'privprotocol' => 'AES',
										'privpassphrase' => 'test'
									]
								],
								2 => [
									'interface_ref' => 'if3',
									'ip' => '127.0.0.1',
									'type' => 'SNMP',
									'dns' => '',
									'port' => '162',
									'useip' => 'YES',
									'default' => 'NO',
									'details' => [
										'bulk' => 'NO',
										'version' => 'SNMPV3',
										'contextname' => 'test',
										'securityname' => 'test',
										'securitylevel' => 'AUTHPRIV',
										'authprotocol' => 'SHA',
										'authpassphrase' => 'test',
										'privprotocol' => 'AES',
										'privpassphrase' => 'test'
									]
								],
								3 => [
									'interface_ref' => 'if4',
									'ip' => '127.0.0.1',
									'type' => 'SNMP',
									'dns' => '',
									'port' => '163',
									'useip' => 'YES',
									'default' => 'NO',
									'details' => [
										'bulk' => 'NO',
										'version' => 'SNMPV2',
										'community' => 'test'
									]
								],
								4 => [
									'interface_ref' => 'if5',
									'ip' => '127.0.0.1',
									'type' => 'SNMP',
									'dns' => '',
									'port' => '161',
									'useip' => 'YES',
									'default' => 'NO',
									'details' => [
										'bulk' => 'NO',
										'version' => 'SNMPV1',
										'community' => 'test'
									]
								]
							],
							'items' => [
								[
									'interface_ref' => 'if3',
									'key' => 'test1',
									'type' => 'SNMP_AGENT',
									'snmp_oid' => 'test'
								]
							],
							'discovery_rules' => [
								[
									'interface_ref' => 'if4',
									'key' => 'test2',
									'type' => 'SNMP_AGENT',
									'snmp_oid' => 'test',
									'item_prototypes' => [
										[
											'interface_ref' => 'if5',
											'key' => 'test3',
											'type' => 'SNMP_AGENT',
											'snmp_oid' => 'test'
										]
									]
								]
							]
						]
					]
				]
			],
			[
				[
					'hosts' => [
						[
							'interfaces' => [
								[
									'interface_ref' => 'if1'
								],
								[
									'interface_ref' => 'if2',
									'type' => 'SNMP',
									'port' => '161'
								]
							],
							'items' => [
								[
									'interface_ref' => 'if2',
									'key' => 'snmptrap.fallback',
									'type' => 'SNMP_TRAP',
									'name' => 'SNMp trap',
									'delay' => 0
								],
								[
									'interface_ref' => 'if2',
									'key' => 'snmptrap[asd]',
									'type' => 'SNMP_TRAP',
									'name' => 'Snmp trap item',
									'delay' => 0
								]
							]
						]
					]
				],
				[
					'hosts' => [
						[
							'interfaces' => [
								0 => [
									'interface_ref' => 'if1'
								],
								2 => [
									'interface_ref' => 'if3',
									'type' => 'SNMP',
									'port' => '161',
									'details' => [
										'bulk' => 'YES',
										'version' => 'SNMPV1',
										'community' => 'public'
									],
									'default' => 'YES',
									'useip' => 'YES',
									'ip' => '127.0.0.1',
									'dns' => ''
								]
							],
							'items' => [
								[
									'interface_ref' => 'if3',
									'key' => 'snmptrap.fallback',
									'type' => 'SNMP_TRAP',
									'name' => 'SNMp trap',
									'delay' => 0
								],
								[
									'interface_ref' => 'if3',
									'key' => 'snmptrap[asd]',
									'type' => 'SNMP_TRAP',
									'name' => 'Snmp trap item',
									'delay' => 0
								]
							]
						]
					]
				]
			],
			[
				[
					'hosts' => [
						[
							'interfaces' => [
								[
									'interface_ref' => 'if1'
								],
								[
									'interface_ref' => 'if2',
									'type' => 'SNMP',
									'port' => '161'
								]
							],
							'items' => [
								[
									'interface_ref' => 'if2',
									'key' => 'snmptrap.fallback',
									'type' => 'SNMP_TRAP',
									'name' => 'SNMp trap',
									'delay' => 0
								],
								[
									'interface_ref' => 'if2',
									'key' => 'snmptrap[asd]',
									'type' => 'SNMP_TRAP',
									'name' => 'Snmp trap item',
									'delay' => 0
								]
							],
							'discovery_rules' => [
								[
									'interface_ref' => 'if1',
									'name' => 'test',
									'type' => 0,
									'key' => 'vfs.file.contents[/tmp/discovery2.txt]</',
									'item_prototypes' => [
										[
											'name' => 'snmp empty port',
											'interface_ref' => 'if2',
											'key' => 'test9'
										]
									]
								]
							]
						]
					]
				],
				[
					'hosts' => [
						[
							'interfaces' => [
								0 => [
									'interface_ref' => 'if1'
								],
								2 => [
									'interface_ref' => 'if3',
									'type' => 'SNMP',
									'port' => '161',
									'details' => [
										'bulk' => 'YES',
										'version' => 'SNMPV1',
										'community' => 'public'
									],
									'default' => 'YES',
									'useip' => 'YES',
									'ip' => '127.0.0.1',
									'dns' => ''
								]
							],
							'items' => [
								[
									'interface_ref' => 'if3',
									'key' => 'snmptrap.fallback',
									'type' => 'SNMP_TRAP',
									'name' => 'SNMp trap',
									'delay' => 0
								],
								[
									'interface_ref' => 'if3',
									'key' => 'snmptrap[asd]',
									'type' => 'SNMP_TRAP',
									'name' => 'Snmp trap item',
									'delay' => 0
								]
							],
							'discovery_rules' => [
								[
									'interface_ref' => 'if1',
									'name' => 'test',
									'type' => 'SNMP_AGENT',
									'key' => 'vfs.file.contents[/tmp/discovery2.txt]</',
									'item_prototypes' => [
										[
											'name' => 'snmp empty port',
											'interface_ref' => 'if2',
											'key' => 'test9'
										]
									]
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderConvert
	 *
	 * @param array $data
	 * @param array $expected
	 */
	public function testConvert(array $data, array $expected) {
		$this->assertConvert($this->createExpectedResult($expected), $this->createSource($data));
	}

	protected function createSource(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '4.4',
				'date' => '2020-01-01T00:00:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '5.0',
				'date' => '2020-01-01T00:00:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertSame($expected, $result);
	}

	protected function createConverter() {
		return new C44ImportConverter();
	}
}
