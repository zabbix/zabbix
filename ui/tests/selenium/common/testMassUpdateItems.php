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

require_once dirname(__FILE__) .'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../traits/PreprocessingTrait.php';

/**
 * Test the mass update of items and item prototypes.
 *
 * @backup items, interface
 */
class testMassUpdateItems extends CWebTest{

	use PreprocessingTrait;

	const HOSTID = 40001;	// Simple form test host.
	const RULEID = 133800;	// testFormDiscoveryRule1 on Simple form test host.
	const HOST_NAME = 'Simple form test host';
	const AGENT_INTERFACE_ID = 40011;
	const SNMP2_INTERFACE_ID = 40012;
	const IPMI_INTERFACE_ID = 40013;

	const INTERVAL_MAPPING = [
		'Type' => [
			'name' => 'type',
			'class' => 'CSegmentedRadioElement',
			'selector' => 'xpath:./ul[contains(@class, "radio-list-control")]'.
					'|./ul/li/ul[contains(@class, "radio-list-control")]|./div/ul[contains(@class, "radio-list-control")]'
		],
		'Interval' => [
			'name' => 'delay',
			'class' => 'CElement',
			'selector' => 'xpath:./input[@name][not(@type) or @type="text" or @type="password"][not(@style) or '.
					'not(contains(@style,"display: none"))]|./textarea[@name]'
		],
		'Period' => [
			'name' => 'period',
			'class' => 'CElement',
			'selector' => 'xpath:./input[@name][not(@type) or @type="text" or @type="password"][not(@style) or '.
					'not(contains(@style,"display: none"))]|./textarea[@name]'
		]
	];

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * Add interface to host.
	 */
	public function prepareInterfaceData() {
		CDataHelper::call('hostinterface.create', [
			[
				'hostid' => self::HOSTID,
				'dns' => '',
				'ip' => '127.0.5.5',
				'main' => 0,
				'port' => '10055',
				'type' => 2,
				'useip' => 1,
				'details' => [
					'version' => 3,
					'bulk' => 1,
					'securityname' => 'zabbix',
					'securitylevel' => 0,
					'authprotocol' => 0,
					'privprotocol' => 0,
					'contextname' => 'zabbix'
				]
			]
		]);
	}

	/**
	 * Data for mass updating of items and item prototypes.
	 */
	public function getCommonChangeData() {
		return [
				[
				[
					'expected' => TEST_BAD,
					'names' => [
						'7_IPMI',
						'8_IPMI'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SNMP agent']
					],
					'details' => 'Item uses incorrect interface type.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'13_DB_Monitor',
						'14_DB_Monitor'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'TELNET agent']
					],
					'details' => 'No interface found.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'TELNET agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051']
					],
					'details' => 'No authentication user name specified.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SSH agent'],
						'Authentication method' => ['id' => 'authtype', 'value' => 'Password'],
						'User name' => ['id' => 'username', 'value' => '']
					],
					'details' => 'No authentication user name specified.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SSH agent'],
						'Authentication method' => ['id' => 'authtype', 'value' => 'Public key'],
						'User name' => ['id' => 'username', 'value' => ''],
						'Public key file' => ['id' => 'publickey', 'value' => '/path/file1'],
						'Private key file' => ['id' => 'privatekey', 'value' => '/path/file2']
					],
					'details' => 'No authentication user name specified.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SSH agent'],
						'Authentication method' => ['id' => 'authtype', 'value' => 'Public key'],
						'User name' => ['id' => 'username', 'value' => 'new_test_name'],
						'Public key file' => ['id' => 'publickey', 'value' => ''],
						'Private key file' => ['id' => 'privatekey', 'value' => '/path/file2']
					],
					'details' => 'No public key file specified.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SSH agent'],
						'Authentication method' => ['id' => 'authtype', 'value' => 'Public key'],
						'User name' => ['id' => 'username', 'value' => 'new_test_name'],
						'Public key file' => ['id' => 'publickey', 'value' => '/path/file1'],
						'Private key file' => ['id' => 'privatekey', 'value' => '']
					],
					'details' => 'No private key file specified.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '3599']
						]
					],
					'details' => 'Incorrect value for field "history": value must be one of 0, 3600-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '1']
						]
					],
					'details' => 'Incorrect value for field "history": value must be one of 0, 3600-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '']
						]
					],
					'details' => 'Incorrect value for field "history": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '25y']
						]
					],
					'details' => 'Incorrect value for field "history": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '']
						]
					],
					'details' => 'Incorrect value for field "trends": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '86399']
						]
					],
					'details' => 'Incorrect value for field "trends": value must be one of 0, 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '1']
						]
					],
					'details' => 'Incorrect value for field "trends": value must be one of 0, 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '25y']
						]
					],
					'details' => 'Incorrect value for field "trends": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix trapper'],
						'Allowed hosts' => ['id' => 'trapper_hosts', 'value' => 'Zabbix server']
					],
					'details' => 'Incorrect value for field "trapper_hosts": invalid address range "Zabbix server".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Update interval' => ['Delay' => '']
					],
					'details' => 'Incorrect value for field "delay": invalid delay.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Update interval' => ['Delay' => '0']
					],
					'details' => 'Item will not be refreshed. Specified update interval requires having at least '.
							'one either flexible or scheduling interval.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Update interval' => ['Delay' => '86401']
					],
					'details' => 'Item will not be refreshed. Update interval should be between 1s and 1d. '.
							'Also Scheduled/Flexible intervals can be used.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Update interval' => [
							'Delay' => '1d',
							'Custom intervals' => [
								[
									'type' => 'Flexible',
									'delay' => '99s',
									'period' => ''
								]
							]
						]
					],
					'details' => 'Invalid interval "".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Update interval' => [
							'Delay' => '1s',
							'Custom intervals' => [
								[
									'type' => 'Flexible',
									'delay' => '',
									'period' => '1-5,00:00-1:00'
								]
							]
						]
					],
					'details' => 'Invalid interval "".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Update interval' => [
							'Delay' => '24h',
							'Custom intervals' => [
								[
									'type' => 'Scheduling',
									'delay' => 'test'
								]
							]
						]
					],
					'details' => 'Invalid interval "test".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'HTTP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'URL' => ['id' => 'url', 'value' => 'https//:zabbix.com'],
						'Headers' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'name' => '',
								'value' => 'header value 1'
							]
						]
					],
					'details' => 'Invalid parameter "headers": nonempty key and value pair expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'HTTP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'URL' => ['id' => 'url', 'value' => 'https//:zabbix.com'],
						'Headers' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'name' => 'header name 1',
								'value' => ''
							]
						]
					],
					'details' => 'Invalid parameter "headers": nonempty key and value pair expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'HTTP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'URL' => ['id' => 'url', 'value' => 'https//:zabbix.com'],
						'Request body type' => ['id' => 'post_type', 'value' => 'JSON data'],
						'Request body' => ['id' => 'posts', 'value' => '"request": "active checks", "host": "host"']
					],
					'details' => 'Invalid parameter "posts": JSON is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'HTTP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'URL' => ['id' => 'url', 'value' => 'https//:zabbix.com'],
						'Request body type' => ['id' => 'post_type', 'value' => 'XML data'],
						'Request body' => ['id' => 'posts', 'value' => 'xml version="1.0" encoding="UTF-8"?<zabbix_export></zabbix_export>']
					],
					'details' => 'Invalid parameter "posts": (4) Start tag expected, \'<\' not found [Line: 1 | Column: 1].'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'HTTP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'URL' => ['id' => 'url', 'value' => 'https//:zabbix.com'],
						'Request body type' => ['id' => 'post_type', 'value' => 'XML data'],
						'Request body' => ['id' => 'posts', 'value' => '']
					],
					'details' => 'Invalid parameter "posts": XML is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'HTTP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'URL' => ['id' => 'url', 'value' => '']
					],
					'details' => 'Invalid parameter "/url": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'JMX agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.4:10054'],
						'JMX endpoint' => ['id' => 'jmx_endpoint', 'value' => '']
					],
					'details' => 'Incorrect value for field "jmx_endpoint": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'JMX agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.4:10054'],
						'JMX endpoint' => [
							'id' => 'jmx_endpoint',
							'value' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi'
						],
						'User name' => ['id' => 'username', 'value' => 'new_test_name'],
						'Password' => ['id' => 'password', 'value' => '']
					],
					'details' => 'Incorrect value for field "username": both username and password should be either present or empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'JMX agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.4:10054'],
						'JMX endpoint' => [
							'id' => 'jmx_endpoint',
							'value' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi'
						],
						'User name' => ['id' => 'username', 'value' => ''],
						'Password' => ['id' => 'password', 'value' => 'new_test_password']
					],
					'details' => 'Incorrect value for field "username": both username and password should be either present or empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SNMP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.5:10055']
					],
					'details' => 'No SNMP OID specified.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Dependent item']
					],
					'details' => 'Incorrect value for field "master_itemid": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'17_Script',
						'18_Script'
					],
					'change' => [
						'Timeout' => ['id' => 'timeout', 'value' => '0']
					],
					'details' => 'Invalid parameter "/1/timeout": value must be one of 1-60.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'17_Script',
						'18_Script'
					],
					'change' => [
						'Timeout' => ['id' => 'timeout', 'value' => '61']
					],
					'details' => 'Invalid parameter "/1/timeout": value must be one of 1-60.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'17_Script',
						'18_Script'
					],
					'change' => [
						'Timeout' => ['id' => 'timeout', 'value' => '']
					],
					'details' => 'Invalid parameter "/1/timeout": cannot be empty.'
				]
			],
			[
				[
					'names' => [
						'17_Script',
						'18_Script'
					],
					'change' => [
						'Timeout' => ['id' => 'timeout', 'value' => '60s']
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent (active)'],
						'Type of information' => ['id' => 'value_type', 'value' => 'Numeric (float)'],
						'Units' => ['id' => 'units', 'value' => '$'],
						'Update interval' => [
							'Delay' => '99s',
							'Custom intervals' => [
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 0,
									'type' => 'Flexible',
									'delay' => '60s',
									'period' => '2-5,3:00-17:00'
								],
								[
									'type' => 'Scheduling',
									'delay' => 'wd3-4h1-15'
								]
							]
						],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '400d']
						],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Do not keep trends']
						],
						'Value mapping' => ['id' => 'valuemapid', 'value' => 'Reference valuemap'],
						'Description' => ['id' => 'description', 'value' => 'New mass updated description']
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '0']
						],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '0']
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '3600']
						],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '86400']
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '9125d']
						],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '9125d']
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '219000h']
						],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '219000h']
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'history', 'value' => '13140000m']
						],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '13140000m']
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Simple check'],
						'Type of information' => ['id' => 'value_type', 'value' => 'Log'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.2:10052'],
						'User name' => ['id' => 'username', 'value' => 'test_username'],
						'Password' => ['id' => 'password', 'value' => 'test_password'],
						'Log time format' => ['id' => 'logtimefmt', 'value' => 'PPPPPP:YYYYMMDD:HHMMSS.mmm']
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix internal'],
						'Type of information' => ['id' => 'value_type', 'value' => 'Text'],
						'Update interval' => [
							'Delay' => '1d',
							'Custom intervals' => [
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 0,
									'type' => 'Scheduling',
									'delay' => 'wd3-4h1-15'
								],
								[
									'type' => 'Flexible',
									'delay' => '99s',
									'period' => '1-2,7:00-8:00'
								]
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix trapper'],
						'Type of information' => ['id' => 'value_type', 'value' => 'Numeric (unsigned)'],
						'Allowed hosts' => ['id' => 'trapper_hosts', 'value' => '127.0.0.1']
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix trapper'],
						'Allowed hosts' => ['id' => 'trapper_hosts', 'value' => '{HOST.HOST}']
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix trapper'],
						'Allowed hosts' => [
							'id' => 'trapper_hosts',
							'value' => '192.168.1.0/24, 192.168.3.1-255, 192.168.1-10.1-255, ::1,2001:db8::/32, zabbix.domain'
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'External check'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'Update interval' => ['Delay' => '1440m']
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'HTTP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'URL' => ['id' => 'url', 'value' => 'https//:zabbix.com'],
						'Request body type' => ['id' => 'post_type', 'value' => 'JSON data'],
						'Request body' => ['id' => 'posts', 'value' => '{"request": "active checks", "host": "host"}'],
						'Headers' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'name' => 'header name 1',
								'value' => 'header value 1'
							],
							[
								'name' => 'header name 2',
								'value' => 'header value 2'
							]
						],
						'Type of information' => ['id' => 'value_type', 'value' => 'Numeric (float)'],
						'Units' => ['id' => 'units', 'value' => 'kB'],
						'Update interval' => ['Delay' => '86400'],
						'Enable trapping' => ['id' => 'allow_traps', 'value' => 'Yes']
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'JMX agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.4:10054'],
						'JMX endpoint' => [
							'id' => 'jmx_endpoint',
							'value' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi'
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051']
					]
				]
			],
			[
				[
					'names' => [
						'7_IPMI',
						'8_IPMI'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'IPMI agent'],
						'Type of information' => ['id' => 'value_type', 'value' => 'Numeric (float)'],
						'Units' => ['id' => 'units', 'value' => 'kB'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.3:10053']
					]
				]
			],
			[
				[
					'names' => [
						'3_SNMP_trap',
						'4_SNMP_trap'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SNMP trap'],
						'Type of information' => ['id' => 'value_type', 'value' => 'Numeric (float)'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.5:10055'],
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Do not keep history']
						],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '99d']
						]
					]
				]
			],
			[
				[
					'names' => [
						'9_SNMP_Agent',
						'10_SNMP_Agent'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SNMP agent'],
						'Type of information' => ['id' => 'value_type', 'value' => 'Character'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.5:10055']
					]
				]
			],
			[
				[
					'names' => [
						'11_SSH_Agent',
						'12_SSH_Agent'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SSH agent'],
						'Authentication method' => ['id' => 'authtype', 'value' => 'Public key'],
						'User name' => ['id' => 'username', 'value' => 'new_name'],
						'Public key file' => ['id' => 'publickey', 'value' => '/path/file1'],
						'Private key file' => ['id' => 'privatekey', 'value' => '/path/file2']
					]
				]
			],
			[
				[
					'names' => [
						'11_SSH_Agent',
						'12_SSH_Agent'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SSH agent'],
						'Authentication method' => ['id' => 'authtype', 'value' => 'Password'],
						'User name' => ['id' => 'username', 'value' => 'New_user_name'],
						'Password' => ['id' => 'password', 'value' => 'New_password']
					]
				]
			],
			[
				[
					'names' => [
						'13_DB_Monitor',
						'14_DB_Monitor'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Database monitor'],
						'User name' => ['id' => 'username', 'value' => 'db_monitor_name'],
						'Password' => ['id' => 'password', 'value' => 'db_monitor_password']
					],
					'expected_preprocessing' => [
						'13_DB_Monitor' => [
							[
								'type' => 'Regular expression',
								'parameter_1' => 'regular expression pattern',
								'parameter_2' => 'output template',
								'error_handler' => 'Set value to',
								'error_handler_params' => 'Error custom value'
							]
						],
						'14_DB_Monitor' => [
									[
								'type' => 'Custom multiplier',
								'parameter_1' => '2'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'TELNET agent'],
						'User name' => ['id' => 'username', 'value' => 'telnet_name'],
						'Password' => ['id' => 'password', 'value' => 'telnet_password']
					]
				]
			],
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Calculated'],
						'Type of information' => ['id' => 'value_type', 'value' => 'Numeric (float)']
					]
				]
			],
			[
				[
					'names' => [
						'15_Calculated',
						'16_Calculated'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Dependent item'],
						'Master item' => ['id' => 'master_item', 'value' => '7_IPMI']
					],
					'expected_tags' => [
						'15_Calculated' => [
							[
								'tag' => 'Item_tag_name',
								'value' => 'Item_tag_value'
							]
						],
						'16_Calculated' =>[
							[
								'tag' => 'Item_tag_name_1',
								'value' => 'Item_tag_value_1'
							],
							[
								'tag' => 'Item_tag_name_2',
								'value' => 'Item_tag_value_2'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Mass update of form fields for items or item prototypes.
	 *
	 * @param    array      $data	      data provider
	 * @param    boolean    $prototypes   true if item prototype, false if item
	 */
	public function executeItemsMassUpdate($data, $prototypes = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM items ORDER BY itemid');
		}

		$form = $this->openMassUpdateForm($prototypes, $data['names'])->asForm();

		// Set field value.
		foreach ($data['change'] as $field => $value) {
			// Click on a label to show input control.
			$form->getLabel($field)->click();
			// Set field value.
			switch ($field) {
				case 'Type':
				case 'Host interface':
				case 'Type of information':
				case 'Authentication method':
					$form->query('id', $value['id'])->asZDropdown()->one()->select($value['value']);
					break;

				case 'Units':
				case 'Description':
				case 'User name':
				case 'Password':
				case 'Log time format':
				case 'Allowed hosts':
				case 'Request body' :
				case 'URL':
				case 'JMX endpoint':
				case 'Public key file':
				case 'Private key file':
				case 'Timeout':
					$form->query('id', $value['id'])->one()->fill($value['value']);
					break;

				case 'Request body type':
				case 'Discover':
				case 'Enable trapping':
				case 'Create enabled':
				case 'Status':
					$form->query('id', $value['id'])->one()->asSegmentedRadio()->fill($value['value']);
					break;

				case 'Update interval':
					$container_table = $form->query('id:update_interval')->asTable()->one();
					$container_table->getRow(0)->getColumn(1)->query('id:delay')->one()->fill($value['Delay']);

					if (array_key_exists('Custom intervals', $value)) {
						$container_table->getRow(1)->getColumn(1)->query('id:custom_intervals')->asMultifieldTable(
								['mapping' => self::INTERVAL_MAPPING])->one()->fill($value['Custom intervals']);
					}
					break;

				case 'History storage period':
				case 'Trend storage period':
					$form->query('id', $value['radio']['id'])->one()->asSegmentedRadio()->fill($value['radio']['value']);

					if (array_key_exists('input', $value)) {
						$form->query('id', $value['input']['id'])->one()->fill($value['input']['value']);
					}

					if ($value['radio']['value'] === 'Do not keep history') {
						$this->assertFalse($form->query('id:history')->one()->isVisible());
					}
					break;

				case 'Headers':
					$form->query('xpath:.//div[@id="headers_pairs"]/table')->asMultifieldTable()->one()->fill($value);
					break;

				case 'Master item':
					if ($prototypes) {
						$form->query('button:Select prototype')->one()->click();
						$master_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
						$master_dialog->query('link', $value['value'])->one()->waitUntilClickable()->click();
					}
					else {
						$form->query('id', $value['id'])->one()->asMultiselect()
								->setFillMode(CMultiselectElement::MODE_SELECT)->fill($value['value']);
					}
					break;

				case 'Value mapping':
					$form->getField('Value mapping')->edit();
					COverlayDialogElement::find()->one()->waitUntilReady()->query('xpath://a[text()="'.$value['value'].'"]')
							->one()->waitUntilClickable()->click();
					break;
			}
		}
		$this->query('button:Update')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, ($prototypes ? 'Cannot update item prototypes' : 'Cannot update items'),
					$data['details']
			);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM items ORDER BY itemid'));
		}
		else {
			$this->assertMessage(TEST_GOOD, ($prototypes ? 'Item prototypes updated' : 'Items updated'));

			// Check changed fields in saved item form.
			foreach ($data['names'] as $name) {
				$table = $this->query('xpath://form[@name="items"]/table[@class="list-table"]')->asTable()->one();
				$table->query('link', $name)->one()->waitUntilClickable()->click();
				$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();

				foreach ($data['change'] as $field => $value) {
					switch ($field) {
						case 'Type':
						case 'Host interface':
						case 'Type of information':
						case 'Show value':
						case 'Units':
						case 'Description':
						case 'Allowed hosts':
						case 'Request body':
						case 'URL':
						case 'JMX endpoint':
						case 'Authentication method':
						case 'Public key file':
						case 'Private key file':
						case 'Request body type':
						case 'User name':
						case 'Password':
						case 'Log time format':
						case 'Timeout':
							$this->assertEquals($value['value'], $form->getField($field)->getValue());
							break;

						case 'History storage period':
						case 'Trend storage period':
							if (CTestArrayHelper::get($value, 'input.value', 'null') === '0') {
								$this->assertEquals('Do not keep '.$value['input']['id'],
										$form->query('id',$value['radio']['id'])->one()->asSegmentedRadio()->getValue()
								);
							}
							else {
								$this->assertEquals($value['radio']['value'],
										$form->query('id', $value['radio']['id'])->one()->asSegmentedRadio()->getValue()
								);

								if ($value['radio']['value'] === 'Do not keep history') {
									$this->assertFalse($form->query('id:history')->one()->isVisible());
								}

								if (array_key_exists('input', $value)) {
									$this->assertEquals($value['input']['value'],
											$form->query('id', $value['input']['id'])->one()->getValue()
									);
								}
							}
							break;

						case 'Status':
						case 'Discover':
						case 'Create enabled':
						case 'Enable trapping':
							$status = ($value['value'] === 'Enabled' || $value['value'] === 'Yes') ? true : false;
							$label = ($field === 'Status') ? 'Enabled' : $field;
							$this->assertEquals($status, $form->getField($label)->getValue());
							break;

						case 'Update interval':
							$this->assertEquals($value['Delay'], $form->getField($field)->getValue());
							if (array_key_exists('Custom intervals', $value)) {
								// Remove action and index fields.
								foreach($value['Custom intervals'] as &$interval) {
									unset($interval['action'], $interval['index']);
								}
								unset($interval);
								$this->assertEquals($value['Custom intervals'], $form->query('id:delayFlexTable')
											->asMultifieldTable(['mapping' => self::INTERVAL_MAPPING])->one()->getValue()
								);
							}
							break;

						case 'Headers':
							// Remove action and index fields.
							foreach ($value as &$header) {
								unset($header['action'], $header['index']);
							}
							unset($header);

							$this->assertEquals($value, $form->query('xpath:.//div[@id="headers_pairs"]/table')
										->asMultifieldTable()->one()->getValue()
							);
							break;

						case 'Master item':
							if ($prototypes) {
								$this->assertEquals(self::HOST_NAME.': '.$value['value'],
										$form->query('xpath://input[@id="master_itemname"]')->one()->getValue()
								);
							}
							else {
								$this->assertEquals([self::HOST_NAME.': '.$value['value']],
										$form->query('xpath://*[@id="master_itemid"]/..')->asMultiselect()->one()->getValue()
								);
							}
							break;
						case 'Value mapping':
							$this->assertEquals([$value['value']],
									$form->query('xpath://*[@id="'.$value['id'].'"]/..')->asMultiselect()->one()->getValue()
							);
							break;
					}
				}

				// Check that preprocessing is not changed after other fields are mass updated.
				if (CTestArrayHelper::get($data, 'expected_preprocessing')) {
					$form->selectTab('Preprocessing');
					$this->assertPreprocessingSteps($data['expected_preprocessing'][$name]);
				}

				// Check that tags are not changed after other fields are mass updated.
				if (CTestArrayHelper::get($data, 'expected_tags')) {
					$form->selectTab('Tags');
					$this->query('id:tags-table')->asMultifieldTable()->one()->checkValue($data['expected_tags'][$name]);
				}

				$form->query('button:Cancel')->one()->waitUntilClickable()->click();
				$this->page->waitUntilReady();
			}
		}
	}

	/**
	 * Add items with preprocessing for mass updating.
	 */
	public function prepareItemPreprocessingData() {
		CDataHelper::call('item.create', [
			[
				'hostid' => self::HOSTID,
				'name' => '1_Item_Preprocessing',
				'key_' => '1agent.preproc',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m',
				'preprocessing' => [
					[
						'type' => '4',
						'params' => '123',
						'error_handler' => 0,
						'error_handler_params' => ''
					],
					[
						'type' => '25',
						'params' => "error\nmistake",
						'error_handler' => 0,
						'error_handler_params' => ''
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item_Preprocessing',
				'key_' => '2agent.preproc',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'preprocessing' => [
					[
						'type' => '5',
						'params' => "pattern\noutput",
						'error_handler' => 2,
						'error_handler_params' => 'custom_value'
					],
					[
						'type' => '16',
						'params' => '$path',
						'error_handler' => 3,
						'error_handler_params' => 'custom_error'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '1_Item_No_Preprocessing',
				'key_' => '1agent.no.preproc',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item_No_Preprocessing',
				'key_' => '2agent.no.preproc',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m'
			]
		]);
	}

	public function getCommonPreprocessingChangeData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item_Tags_Preprocessing',
						'1_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'Custom multiplier', 'parameter_1' => 'abc']
					],
					'details' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item_Tags_Preprocessing',
						'1_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'Simple change'],
						['type' => 'Simple change']
					],
					'details' => 'Only one change step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item_Tags_Preprocessing',
						'1_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'In range', 'parameter_1' => '8', 'parameter_2' => '-8']
					],
					'details' => 'Incorrect value for field "params": "min" value must be less than or equal to "max" value.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item_Tags_Preprocessing',
						'1_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'Check for error using regular expression', 'parameter_1' => 'test']
					],
					'details' => 'Incorrect value for field "params": second parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item_Tags_Preprocessing',
						'1_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'details' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item_Tags_Preprocessing',
						'1_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'Regular expression']
					],
					'details' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'2_Item_Tags_Preprocessing',
						'2_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						[
							'type' => 'XML XPath',
							'parameter_1' => "//path/one",
							'on_fail' => true,
							'error_handler' => 'Set error to'
						]
					],
					'details' => 'Incorrect value for field "error_handler_params": cannot be empty.'
				]
			],
			[
				[
					'names' => [
						'1_Item_Tags_Preprocessing',
						'2_Item_Tags_Preprocessing'
					],
					'Preprocessing steps' => []
				]
			],
			[
				[
					'names' => [
						'1_Item_Tags_Preprocessing',
						'1_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						[
							'type' => 'Custom multiplier',
							'parameter_1' => '3',
							'on_fail' => true,
							'error_handler' => 'Set error to',
							'error_handler_params' => 'New_error1'
						],
						[
							'type' => 'In range',
							'parameter_1' => '10',
							'parameter_2' => '20',
							'on_fail' => true,
							'error_handler' => 'Set value to',
							'error_handler_params' => 'New_value_2'
						],
						[
							'type' => 'JSONPath',
							'parameter_1' => '$path',
							'on_fail' => true,
							'error_handler' => 'Discard value'
						],
						[
							'type' => 'Discard unchanged'
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item_Tags_Preprocessing',
						'2_Item_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'Replace', 'parameter_1' => 'text', 'parameter_2' => 'REPLACEMENT'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Simple change'],
						['type' => 'In range', 'parameter_1' => '-5', 'parameter_2' => '9.5'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '5'],
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => 'label',
								'parameter_3' => 'label_name']
					]
				]
			]
		];
	}

	/**
	 * Mass update of preprocessing steps for items or item prototypes.
	 *
	 * @param    array      $data	      data provider
	 * @param    boolean    $prototypes   true if item prototype, false if item
	 */
	public function executeItemsPreprocessingMassUpdate($data, $prototypes = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM items ORDER BY itemid');
		}

		$dialog = $this->openMassUpdateForm($prototypes, $data['names']);
		$form = $dialog->asForm();
		$form->selectTab('Preprocessing');
		$form->getLabel('Preprocessing steps')->click();

		if ($data['Preprocessing steps'] !== []) {
			$this->addPreprocessingSteps($data['Preprocessing steps']);
		}

		$dialog->query('button:Update')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$error = $prototypes ? 'Cannot update item prototypes' : 'Cannot update items';
			$this->assertMessage(TEST_BAD, $error, $data['details']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM items ORDER BY itemid'));
		}
		else {
			$this->assertMessage(TEST_GOOD, ($prototypes ? 'Item prototypes updated' : 'Items updated'));

			// Check changed fields in saved item form.
			foreach ($data['names'] as $name) {
				$table = $this->query('xpath://form[@name="items"]/table[@class="list-table"]')->asTable()->one();
				$table->query('link', $name)->one()->waitUntilClickable()->click();
				$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
				$form->selectTab('Preprocessing');
				$this->assertPreprocessingSteps($data['Preprocessing steps']);
				$form->query('button:Cancel')->one()->waitUntilClickable()->click();
				$this->page->waitUntilReady();
			}
		}
	}

	public function getCommonTagsChangeData() {
		return [
			// Empty tag name.
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item_Tags_Preprocessing',
						'1_Item_No_Tags_Preprocessing'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'value' => 'value1'
							]
						]
					],
					'details' => 'Invalid parameter "/1/tags/2/tag": cannot be empty.'
				]
			],
			// TODO: Uncomment this case when ZBX-19263 is fixed.
			// Equal tags.
			/*
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'2_Item_Tags_Preprocessing',
						'2_Item_No_Tags_Preprocessing'
					],
					'Tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'tag',
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'value' => 'value'
						]
					],
					'details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(tag, value) already exists.'
				]
			],
			*/
			[
				[
					'names' => [
						'1_Item_Tags_Preprocessing',
						'2_Item_Tags_Preprocessing'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => []
					],
					'expected_tags' => [
						'1_Item_Tags_Preprocessing' => [
							[
								'tag' => 'old_tag_1',
								'value' => 'old_value_1'
							]
						],
						'2_Item_Tags_Preprocessing' => [
							[
								'tag' => 'old_tag_2',
								'value' => 'old_value_2'
							],
							[
								'tag' => 'old_tag_3',
								'value' => 'old_value_3'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item_Tags_Preprocessing',
						'2_Item_Tags_Preprocessing'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'added_tag_1',
								'value' => 'added_value_1'
							]
						]
					],
					'expected_tags' => [
						'1_Item_Tags_Preprocessing' => [
							[
								'tag' => 'added_tag_1',
								'value' => 'added_value_1'
							],
							[
								'tag' => 'old_tag_1',
								'value' => 'old_value_1'
							]
						],
						'2_Item_Tags_Preprocessing' => [
							[
								'tag' => 'added_tag_1',
								'value' => 'added_value_1'
							],
							[
								'tag' => 'old_tag_2',
								'value' => 'old_value_2'
							],
							[
								'tag' => 'old_tag_3',
								'value' => 'old_value_3'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item_Tags_replace',
						'2_Item_Tags_replace'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => []
					],
					'expected_tags' => [
						'1_Item_Tags_replace' => [
							[
								'tag' => '',
								'value' => ''
							]
						],
						'2_Item_Tags_replace' => [
							[
								'tag' => '',
								'value' => ''
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item_Tags_replace',
						'2_Item_Tags_replace'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'replaced_tag',
								'value' => 'replaced_value'
							]
						]
					],
					'expected_tags' => [
						'1_Item_Tags_replace' => [
							[
								'tag' => 'replaced_tag',
								'value' => 'replaced_value'
							]
						],
						'2_Item_Tags_replace' => [
							[
								'tag' => 'replaced_tag',
								'value' => 'replaced_value'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item_Tags_remove',
						'2_Item_Tags_remove'
					],
					'Tags' => [
						'action' => 'Remove',
						'tags' => [
							[
								'tag' => '',
								'value' => ''
							]
						]
					],
					'expected_tags' => [
						'1_Item_Tags_remove' => [
							[
								'tag' => 'remove_tag_1',
								'value' => 'remove_value_1'
							],
							[
								'tag' => 'remove_tag_2',
								'value' => 'remove_value_2'
							]
						],
						'2_Item_Tags_remove' => [
							[
								'tag' => 'remove_tag_2',
								'value' => 'remove_value_2'
							]
						]
					]
				]
			],
			[
				[
					'names' => [
						'1_Item_Tags_remove',
						'2_Item_Tags_remove',
						'3_Item_Tags_remove'
					],
					'Tags' => [
						'action' => 'Remove',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'remove_tag_2',
								'value' => 'remove_value_2'
							]
						]
					],
					'expected_tags' => [
						'1_Item_Tags_remove' => [
							[
								'tag' => 'remove_tag_1',
								'value' => 'remove_value_1'
							]
						],
						'2_Item_Tags_remove' => [
							[
								'tag' => '',
								'value' => ''
							]
						],
						'3_Item_Tags_remove' => [
							[
								'tag' => 'remove_tag_3',
								'value' => 'remove_value_3'
							]
						]
					]
				]
			],
			// Different symbols in tag names and values.
			[
				[
					'names' => [
						'1_Item_No_Tags_Preprocessing',
						'2_Item_No_Tags_Preprocessing'
					],
					'Tags' => [
						'action' => 'Add',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => '!@#$%^&*()_+<>,.\/',
								'value' => '!@#$%^&*()_+<>,.\/'
							],
							[
								'tag' => 'tag1',
								'value' => 'value1'
							],
							[
								'tag' => 'tag2'
							],
							[
								'tag' => '{$MACRO:A}',
								'value' => '{$MACRO:A}'
							],
							[
								'tag' => '{$MACRO}',
								'value' => '{$MACRO}'
							],
							[
								'tag' => 'Таг',
								'value' => 'Значение'
							]
						]
					]
				]
			],
			// Two tags with equal tag names.
			[
				[
					'names' => [
						'1_Item_No_Tags_Preprocessing',
						'2_Item_No_Tags_Preprocessing'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'tag3',
								'value' => '3'
							],
							[
								'tag' => 'tag3',
								'value' => '4'
							]
						]
					]
				]
			],
			// Two tags with equal tag values.
			[
				[
					'names' => [
						'1_Item_No_Tags_Preprocessing',
						'2_Item_No_Tags_Preprocessing'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'tag4',
								'value' => '5'
							],
							[
								'tag' => 'tag5',
								'value' => '5'
							]
						]
					]
				]
			],
			// Tag with trailing spaces.
			[
				[
					'names' => [
						'1_Item_No_Tags_Preprocessing',
						'2_Item_No_Tags_Preprocessing'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => '    trimmed tag    ',
								'value' => '   trimmed value    '
							]
						]
					],
					'trim' => true
				]
			],
			// Tag with long name and value.
			[
				[
					'names' => [
						'1_Item_No_Tags_Preprocessing',
						'2_Item_No_Tags_Preprocessing'
					],
					'Tags' => [
						'action' => 'Replace',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'Long tag name. Long tag name. Long tag name. Long tag name. Long tag name.'.
										' Long tag name. Long tag name. Long tag name.',
								'value' => 'Long tag value. Long tag value. Long tag value. Long tag value. Long tag value.'.
										' Long tag value. Long tag value. Long tag value. Long tag value.'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Mass update of items or item prototypes tags.
	 *
	 * @param    array      $data	      data provider
	 * @param    boolean    $prototypes   true if item prototype, false if item
	 */
	public function executeItemsTagsMassUpdate($data, $prototypes = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM items ORDER BY itemid');
		}

		$dialog = $this->openMassUpdateForm($prototypes, $data['names']);
		$form = $dialog->asForm();
		$form->selectTab('Tags');
		$form->getLabel('Tags')->click();

		$form->query('id:mass_update_tags')->asSegmentedRadio()->one()->fill($data['Tags']['action']);

		if ($data['Tags']['tags'] !== []) {
			$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['Tags']['tags']);
		}

		$dialog->query('button:Update')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$error = $prototypes ? 'Cannot update item prototypes' : 'Cannot update items';
			$this->assertMessage(TEST_BAD, $error, $data['details']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM items ORDER BY itemid'));
		}
		else {
			$this->assertMessage(TEST_GOOD, ($prototypes ? 'Item prototypes updated' : 'Items updated'));

			// Check changed fields in saved item form.
			foreach ($data['names'] as $name) {
				$table = $this->query('xpath://form[@name="items"]/table[@class="list-table"]')->asTable()->one();
				$table->query('link', $name)->one()->waitUntilClickable()->click();
				$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
				$form->selectTab('Tags');

				$expected = $data['Tags']['tags'];
				if (!array_key_exists('expected_tags', $data)) {
					// Remove action and index fields for asserting expected result.
					foreach ($expected as &$tag) {
						unset($tag['action'], $tag['index']);

						if (CTestArrayHelper::get($data, 'trim', false) === false) {
							continue;
						}

						// Remove trailing spaces from tag and value for asserting expected result.
						foreach ($expected as $i => &$options) {
							foreach (['tag', 'value'] as $parameter) {
								if (array_key_exists($parameter, $options)) {
									$options[$parameter] = trim($options[$parameter]);
								}
							}
						}
						unset($options);
					}
					unset($tag);
				}

				$expected_tags = array_key_exists('expected_tags', $data) ? $data['expected_tags'][$name] : $expected;
				$this->query('id:tags-table')->asMultifieldTable()->one()->checkValue($expected_tags);

				$form->query('button:Cancel')->one()->waitUntilClickable()->click();
				$this->page->waitUntilReady();
			}
		}
	}

	/**
	 * Cancel Mass updating of items or item prototypes.
	 *
	 * @param    boolean    $prototypes   true if item prototype, false if item
	 */
	public function executeMassUpdateCancel($prototypes = false) {
		$old_hash = CDBHelper::getHash('SELECT * FROM items ORDER BY itemid');

		$items  = [
			'1_Item_Tags_Preprocessing',
			'2_Item_Tags_Preprocessing',
			'1_Item_No_Tags_Preprocessing',
			'2_Item_No_Tags_Preprocessing'
		];
		$dialog = $this->openMassUpdateForm($prototypes, $items);
		$dialog->query('button:Cancel')->one()->waitUntilClickable()->click();

		// Check that UI returned to previous page and hash remained unchanged.
		$this->page->waitUntilReady();
		$this->page->assertHeader($prototypes ? 'Item prototypes' : 'Items');
		$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM items ORDER BY itemid'));
	}

	/**
	 * Select items or item prototypes in list and open Mass update form.
	 *
	 * @param    boolean    $prototypes   true if item prototype, false if item
	 * @param    array      $data	      items to be mass updated
	 *
	 * @return CElement
	 */
	private function openMassUpdateForm($prototypes, $data) {
		$link = ($prototypes)
			? 'disc_prototypes.php?parent_discoveryid='.self::RULEID.'&context=host'
			: 'items.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host';
		$this->page->login()->open($link);

		// Get item table.
		$table = $this->query('xpath://form[@name="items"]/table[@class="list-table"]')->asTable()->one();
		$table->findRows('Name', $data)->select();

		// Open mass update form.
		$this->query('button:Mass update')->one()->click();

		return COverlayDialogElement::find()->one()->waitUntilReady();
	}
}
