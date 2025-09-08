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


require_once __DIR__ .'/../../include/CWebTest.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CPreprocessingBehavior.php';
use Facebook\WebDriver\Exception\ElementClickInterceptedException;

/**
 * Test the mass update of items and item prototypes.
 *
 * @backup items, interface
 */
class testMassUpdateItems extends CWebTest{

	/**
	 * Attach PreprocessingBehavior and MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CPreprocessingBehavior::class
		];
	}

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
			'selector' => 'xpath:./input[@name][not(@type) or @type="text" or @type="password"][not(@class) or '.
					'not(contains(@class, "display-none"))]|./textarea[@name]'
		],
		'Period' => [
			'name' => 'period',
			'class' => 'CElement',
			'selector' => 'xpath:./input[@name][not(@type) or @type="text" or @type="password"][not(@class) or '.
					'not(contains(@class, "display-none"))]|./textarea[@name]'
		]
	];

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
					'max_repetitions' => 10,
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
			// #0.
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
					'details' => 'Invalid parameter "/1/snmp_oid": cannot be empty.'
				]
			],
			// #1.
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
					'details' => 'Invalid parameter "/2/username": cannot be empty.'
				]
			],
			// #2.
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
					'details' => 'Invalid parameter "/1/username": cannot be empty.'
				]
			],
			// #3.
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
					'details' => 'Invalid parameter "/1/username": cannot be empty.'
				]
			],
			// #4.
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
					'details' => 'Invalid parameter "/1/username": cannot be empty.'
				]
			],
			// #5.
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
					'details' => 'Invalid parameter "/1/publickey": cannot be empty.'
				]
			],
			// #6.
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
					'details' => 'Invalid parameter "/1/privatekey": cannot be empty.'
				]
			],
			// #7.
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
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '3599']
						]
					],
					'details' => 'Invalid parameter "/1/history": value must be one of 0, 3600-788400000.'
				]
			],
			// #8.
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
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '1']
						]
					],
					'details' => 'Invalid parameter "/1/history": value must be one of 0, 3600-788400000.'
				]
			],
			// #9.
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
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '']
						]
					],
					'details' => 'Invalid parameter "/1/history": cannot be empty.'
				]
			],
			// #10.
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
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '25y']
						]
					],
					'details' => 'Invalid parameter "/1/history": a time unit is expected.'
				]
			],
			// #11.
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
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '']
						]
					],
					'details' => 'Invalid parameter "/1/trends": cannot be empty.'
				]
			],
			// #12.
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
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '86399']
						]
					],
					'details' => 'Invalid parameter "/1/trends": value must be one of 0, 86400-788400000.'
				]
			],
			// #13.
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
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '1']
						]
					],
					'details' => 'Invalid parameter "/1/trends": value must be one of 0, 86400-788400000.'
				]
			],
			// #14.
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
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '25y']
						]
					],
					'details' => 'Invalid parameter "/1/trends": a time unit is expected.'
				]
			],
			// #15.
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
					'details' => 'Invalid parameter "/1/trapper_hosts": incorrect address starting from "server".'
				]
			],
			// #16.
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
					'details' => 'Invalid parameter "/1/delay": cannot be empty.'
				]
			],
			// #17.
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
					'details' => 'Invalid parameter "/1/delay": cannot be equal to zero without custom intervals.'
				]
			],
			// #18.
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
					'details' => 'Invalid parameter "/1/delay": value must be one of 0-86400.'
				]
			],
			// #19.
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
			// #20.
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
			// #21.
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
			// #22.
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
					'details' => 'Invalid parameter "/1/headers/1/name": cannot be empty.'
				]
			],
			// #23.
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
						'Request body type' => ['id' => 'post_type_container', 'value' => 'JSON data'],
						'Request body' => ['id' => 'posts', 'value' => '"request": "active checks", "host": "host"']
					],
					'details' => 'Invalid parameter "/1/posts": JSON is expected.'
				]
			],
			// #24.
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
						'Request body type' => ['id' => 'post_type_container', 'value' => 'XML data'],
						'Request body' => ['id' => 'posts', 'value' => 'xml version="1.0" encoding="UTF-8"?<zabbix_export></zabbix_export>']
					],
					'details' => 'Invalid parameter "/1/posts": (4) Start tag expected, \'<\' not found [Line: 1 | Column: 1].'
				]
			],
			// #25.
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
						'Request body type' => ['id' => 'post_type_container', 'value' => 'XML data'],
						'Request body' => ['id' => 'posts', 'value' => '']
					],
					'details' => 'Invalid parameter "/1/posts": cannot be empty.'
				]
			],
			// #26.
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
					'details' => 'Invalid parameter "/1/url": cannot be empty.'
				]
			],
			// #27.
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
					'details' => 'Invalid parameter "/1/jmx_endpoint": cannot be empty.'
				]
			],
			// #28.
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
					'details' => 'Invalid parameter "/1": both username and password should be either present or empty.'
				]
			],
			// #29.
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
					'details' => 'Invalid parameter "/1": both username and password should be either present or empty.'
				]
			],
			// #30.
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
					'details' => 'Invalid parameter "/1/snmp_oid": cannot be empty.',
					'interface_text_part' => 'SNMPv3, Context name: zabbix'
				]
			],
			// #31.
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
					'details' => 'Invalid parameter "/1/timeout": value must be one of 1-600.'
				]
			],
			// #32.
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'17_Script',
						'18_Script'
					],
					'change' => [
						'Timeout' => ['id' => 'timeout', 'value' => '601']
					],
					'details' => 'Invalid parameter "/1/timeout": value must be one of 1-600.'
				]
			],
			// #33.
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
					// TODO: change details error message after ZBX-23467 fix (if necessary).
					'details' => 'Incorrect value for field "timeout": cannot be empty.'
				]
			],
			// #34.
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
			// #35.
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
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '400d']
						],
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Do not store']
						],
						'Value mapping' => ['id' => 'valuemapid', 'value' => 'Reference valuemap'],
						'Description' => ['id' => 'description', 'value' => 'New mass updated description']
					]
				]
			],
			// #36.
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '0']
						],
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '0']
						]
					]
				]
			],
			// #37.
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '3600']
						],
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '86400']
						]
					]
				]
			],
			// #38.
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '9125d']
						],
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '9125d']
						]
					]
				]
			],
			// #39.
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '219000h']
						],
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '219000h']
						]
					]
				]
			],
			// #40.
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'history', 'value' => '13140000m']
						],
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '13140000m']
						]
					]
				]
			],
			// #41.
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
					],
					'interface_text_part' => 'SNMPv2, Community: {$SNMP_COMMUNITY}'

				]
			],
			// #42.
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
			// #43.
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
			// #44.
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
			// #45.
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
			// #46.
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
			// #47.
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
						'Request body type' => ['id' => 'post_type_container', 'value' => 'JSON data'],
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
				],
				'screenshot' => true
			],
			// #48.
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
			// #49.
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
			// #50.
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
			// #51.
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
						'History' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Do not store']
						],
						'Trends' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Store up to'],
							'input' => ['id' => 'trends', 'value' => '99d']
						]
					],
					'interface_text_part' => 'SNMPv3, Context name: zabbix'
				]
			],
			// #52.
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
					],
					'interface_text_part' => 'SNMPv3, Context name: zabbix'
				]
			],
			// #53.
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
			// #54.
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
			// #55.
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
			// TODO: uncomment or delete after discussion
//			[
//				[
//					'names' => [
//						'1_Item',
//						'2_Item'
//					],
//					'change' => [
//						'Type' => ['id' => 'type', 'value' => 'TELNET agent'],
//						'User name' => ['id' => 'username', 'value' => 'telnet_name'],
//						'Password' => ['id' => 'password', 'value' => 'telnet_password']
//					]
//				]
//			],
//			[
//				[
//					'names' => [
//						'1_Item',
//						'2_Item'
//					],
//					'change' => [
//						'Type' => ['id' => 'type', 'value' => 'Calculated'],
//						'Type of information' => ['id' => 'value_type', 'value' => 'Numeric (float)']
//					]
//				]
//			],
			// #56.
			[
				[
					'names' => [
						'15_Calculated',
						'16_Calculated'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Dependent item'],
						'Master item' => ['id' => 'master-item-field', 'value' => '7_IPMI']
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
				case 'Type of information':
				case 'Authentication method':
					$form->query('id', $value['id'])->asDropdown()->one()->select($value['value']);
					break;

				case 'Host interface':
					/**
					 * The value of an SNMP interface option element contains not only the IP and port, but also the
					 * interface type and context name or community. In this case the address and details must be merged.
					 */
					$interface = $value['value'].CTestArrayHelper::get($data, 'interface_text_part', '');

					$form->query('id', $value['id'])->asDropdown()->one()->select($interface);
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
					$update_interval_field = $form->getField('Update interval');
					$update_interval_field->query('id:delay')->waitUntilVisible()->one()->fill($value['Delay']);

					if (array_key_exists('Custom intervals', $value)) {
						$update_interval_field->query('id:custom_intervals')
								->asMultifieldTable(['mapping' => self::INTERVAL_MAPPING])->one()
								->fill($value['Custom intervals']);
					}
					break;

				case 'History':
				case 'Trends':
					$form->query('id', $value['radio']['id'])->one()->asSegmentedRadio()->fill($value['radio']['value']);

					if (array_key_exists('input', $value)) {
						$form->query('id', $value['input']['id'])->one()->fill($value['input']['value']);
					}

					if ($value['radio']['value'] === 'Do not store' && $value['radio']['id'] === 'history_mode') {
						$this->assertFalse($form->query('id:history')->one()->isVisible());
					}
					break;

				case 'Headers':
					$form->query('xpath:.//div[@id="headers_pairs"]/table')->asMultifieldTable()->one()->fill($value);

					// Take a screenshot to test draggable object position of headers in mass update.
					if (array_key_exists('screenshot', $data)) {
						$this->page->removeFocus();
						$this->assertScreenshot($form->query('id:headers_pairs')->waitUntilPresent()->one(), 'Item mass update headers'.$prototypes);
					}

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
			/**
			 * TODO: after ZBX-23467 remove if/else and leave only
			 * $this->assertMessage(TEST_BAD, ($prototypes ? 'Cannot update item prototypes' : 'Cannot update items'),
			 *      $data['details']);
			 */
			if ($field === 'Timeout') {
				$this->assertMessage(TEST_BAD, null, $data['details']);
			}
			else {
				$this->assertMessage(TEST_BAD, ($prototypes ? 'Cannot update item prototypes' : 'Cannot update items'),
					$data['details']
				);
			}

			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM items ORDER BY itemid'));
		}
		else {
			$this->assertMessage(TEST_GOOD, ($prototypes ? 'Item prototypes updated' : 'Items updated'));

			// Check changed fields in saved item form.
			foreach ($data['names'] as $name) {
				$table = $this->query('xpath://form[@name='.
						CXPathHelper::escapeQuotes($prototypes ? 'itemprototype' : 'item_list').
						']/table')->asTable()->one();
				$table->query('link', $name)->one()->waitUntilClickable()->click();
				$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
				$form = $overlay->asForm();

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
						case 'id:timeout':
							$this->assertEquals($value['value'], $form->getField($field)->getValue());
							break;

						case 'History':
						case 'Trends':
							if (CTestArrayHelper::get($value, 'input.value', 'null') === '0') {
								$this->assertEquals('Do not store',
										$form->query('id',$value['radio']['id'])->one()->asSegmentedRadio()->getValue()
								);
							}
							else {
								$this->assertEquals($value['radio']['value'],
										$form->query('id', $value['radio']['id'])->one()->asSegmentedRadio()->getValue()
								);

								if ($value['radio']['value'] === 'Do not store' && $value['radio']['id'] === 'history_mode') {
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
								$this->assertEquals($value['Custom intervals'], $form->getField('Custom intervals')
										->asMultifieldTable(['mapping' => self::INTERVAL_MAPPING])->getValue()
								);
							}
							break;

						case 'Headers':
							// Remove action and index fields.
							foreach ($value as &$header) {
								unset($header['action'], $header['index']);
							}
							unset($header);

							$this->assertEquals($value, $form->query('xpath:.//div[@id="js-item-headers-field"]//table')
									->asMultifieldTable()->one()->getValue()
							);
							break;

						case 'Master item':
							$this->assertEquals([self::HOST_NAME.': '.$value['value']],
									$form->query('xpath://*[@id="master_itemid"]/..')->asMultiselect()->one()->getValue()
							);
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
					$this->query('class:tags-table')->asMultifieldTable()->one()->checkValue($data['expected_tags'][$name]);
				}

				$overlay->getFooter()->query('button:Cancel')->one()->waitUntilClickable()->click();
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
			// #0.
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
					'details' => 'Invalid parameter "/1/preprocessing/1/params/1": a floating point value is expected.'
				]
			],
			// #1.
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
					'details' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within the '.
							'combinations of (type)=((9, 10)).'
				]
			],
			// #2.
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
					'details' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be less than or equal to '.
							'the value of parameter "/1/preprocessing/1/params/1".'
				]
			],
			// #3.
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
					'details' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
				]
			],
			// #4.
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
					'details' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within the '.
							'combinations of (type)=((19, 20)).'
				]
			],
			// #5.
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
					'details' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// #6.
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
					'details' => 'Invalid parameter "/1/preprocessing/1/error_handler_params": cannot be empty.'
				]
			],
			// #7.
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'2_Item_Tags_Preprocessing',
						'2_Item_No_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'any error'],
						['type' => 'Check for not supported value', 'parameter_1' => 'any error']
					],
					'details' => 'Invalid parameter "/1/preprocessing/2": value (type, params)=(26, -1) already exists.'
				]
			],
			// #8.
			[
				[
					'names' => [
						'1_Item_Tags_Preprocessing',
						'2_Item_Tags_Preprocessing'
					],
					'Preprocessing steps' => []
				]
			],
			// #9.
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
			// #10.
			[
				[
					'names' => [
						'1_Item_Tags_Preprocessing',
						'2_Item_Tags_Preprocessing'
					],
					'Preprocessing steps' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error matches','parameter_2' => '^test.*$',
								'on_fail' => true, 'error_handler' => 'Set value to', 'error_handler_params' => 'custom value'
						],
						['type' => 'Replace', 'parameter_1' => 'text', 'parameter_2' => 'REPLACEMENT'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'SNMP walk value', 'parameter_1' => 'oid'],
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
								'parameter_3' => 'label_name'
						]
					],
					'Screenshot' => true
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
			$this->addPreprocessingSteps($data['Preprocessing steps'], true);

			// Take a screenshot to test draggable object position of preprocessing steps in mass update.
			if (array_key_exists('Screenshot', $data)) {
				$this->page->removeFocus();

				// It is necessary because of unexpected viewport shift.
				$this->page->updateViewport();
				$this->assertScreenshot($form->query('id:preprocessing')->waitUntilPresent()->one(),
						'Item mass update preprocessing'.$prototypes
				);
			}
		}
		else {
			$form->fill(['id:preprocessing_action' => 'Remove all']);
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
				$table = $this->query('xpath://form[@name='.
						CXPathHelper::escapeQuotes($prototypes ? 'itemprototype' : 'item_list').
						']/table')->asTable()->one();
				// TODO: not stable test testPageMassUpdateItems_ChangePreprocessing#8 on Jenkins, failed to properly waitUntilReady for page
				try {
					$table->query('link', $name)->one()->waitUntilClickable()->click();
				}
				catch (ElementClickInterceptedException $e) {
					$table->query('link', $name)->one()->waitUntilClickable()->click();
				}
				$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
				$form = $overlay->asForm();
				$form->selectTab('Preprocessing');
				$this->assertPreprocessingSteps($data['Preprocessing steps']);
				$overlay->getFooter()->query('button:Cancel')->one()->click();
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
			$this->query('class:tags-table')->asMultifieldTable()->one()->fill($data['Tags']['tags']);
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
				$table = $this->query('xpath://form[@name='.
						CXPathHelper::escapeQuotes($prototypes ? 'itemprototype' : 'item_list').
						']/table')->asTable()->one();
				$table->query('link', $name)->one()->waitUntilClickable()->click();
				$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
				$form = $overlay->asForm();
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
				$this->query('class:tags-table')->asMultifieldTable()->one()->checkValue($expected_tags);

				$overlay->getFooter()->query('button:Cancel')->one()->click();
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
			? 'zabbix.php?action=item.prototype.list&parent_discoveryid='.self::RULEID.'&context=host'
			: 'zabbix.php?action=item.list&filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host';
		$this->page->login()->open($link);

		// Get item table.
		$table = $this->query('xpath://form[@name='.
				CXPathHelper::escapeQuotes($prototypes ? 'itemprototype' : 'item_list').
				']/table')->asTable()->one();
		$table->findRows('Name', $data)->select();

		// Open mass update form.
		$this->query('button:Mass update')->one()->click();

		return COverlayDialogElement::find()->one()->waitUntilReady();
	}
}
