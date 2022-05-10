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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * Base class for Host form tests.
 */
class testFormHost extends CWebTest {

	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * Link to page for opening host form.
	 */
	public $link;

	/**
	 * Flag for host form opened by direct link.
	 */
	public $standalone = false;

	/**
	 * Flag for form opened from Monitoring -> Hosts section.
	 */
	public $monitoring = false;

	/**
	 * SQL query to get host and host interfaces tables to compare hash values.
	 */
	private $hosts_sql = 'SELECT * FROM hosts h INNER JOIN interface i ON h.hostid=i.hostid ORDER BY h.hostid, i.interfaceid';

	/**
	 * SQL query to get snmp interface table to compare hash values.
	 */
	private $interface_snmp_sql = 'SELECT * FROM interface_snmp ORDER BY interfaceid, community';

	/**
	 * Ids of the hosts that are created within this test specifically for the update scenario.
	 *
	 * @var array
	 */
	protected static $hostids;

	/**
	 * Ids of the items that are created within this test.
	 *
	 * @var array
	 */
	protected static $itemids;

	/**
	 * Default values of interfaces.
	 *
	 * @var array
	 */
	private $default_values = [
		'Agent' => [
			'ip' => '127.0.0.1',
			'dns' => '',
			'Connect to' => 'IP',
			'port' => 10050
		],
		'SNMP' => [
			'ip' => '127.0.0.1',
			'dns' => '',
			'Connect to' => 'IP',
			'port' => 161,
			'SNMP version' => 'SNMPv2',
			'SNMP community' => '{$SNMP_COMMUNITY}',
			'Use bulk requests' => true
		],
		'JMX' => [
			'ip' => '127.0.0.1',
			'dns' => '',
			'Connect to' => 'IP',
			'port' => 12345
		],
		'IPMI' => [
			'ip' => '127.0.0.1',
			'dns' => '',
			'Connect to' => 'IP',
			'port' => 623
		]
	];

	public static function prepareUpdateData() {
		$interfaces = [
			[
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.1.1.1',
				'dns' => '',
				'port' => '10011'
			],
			[
				'type' => 2,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.2.2.2',
				'dns' => '',
				'port' => 122,
				'details' => [
					'version' => 1,
					'bulk' => 0,
					'community' => '🙃zabbix🙃'
				]
			],
			[
				'type' => 3,
				'main' => 1,
				'useip' => 0,
				'ip' => '',
				'dns' => 'selenium.test',
				'port' => 30053
			],
			[
				'type' => 4,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.4.4.4',
				'dns' => '',
				'port' => 426
			]
		];

		$groups = [
			[
				'groupid' => 4
			]
		];

		$result = CDataHelper::createHosts([
			[
				'host' => 'testFormHost_Update',
				'name' => 'testFormHost_Update Visible name',
				'description' => 'Created host via API to test update functionality in host form and interfaces',
				'interfaces' => $interfaces,
				'groups' => $groups,
				'proxy_hostid' => 20001,
				'status' => HOST_STATUS_MONITORED
			],
			[
				'host' => 'testFormHost with items',
				'description' => 'Created host via API to test clone functionality in host form and interfaces 😀',
				'interfaces' => $interfaces,
				'groups' => $groups,
				'proxy_hostid' => 20001,
				'status' => HOST_STATUS_NOT_MONITORED,
				'items' => [
					[
						'name' => 'Agent active item',
						'key_' => 'agent.ping',
						'type' => ITEM_TYPE_ZABBIX_ACTIVE,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '1m'
					],
					[
						'name' => 'Agent item',
						'key_' => 'agent.hostname',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '1h'
					],
					[
						'name' => 'JMX item',
						'key_' => 'jmx[object_name,attribute_name]',
						'type' => ITEM_TYPE_JMX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '1s',
						'username' => '',
						'password' => ''
					]
				]
			]
		]);

		self::$hostids = $result['hostids'];
		self::$itemids = $result['itemids'];
	}

	/**
	 * Test for checking host form layout.
	 */
	public function checkHostLayout() {
		$host = 'testFormHost with items';
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr($host));

		$form = $this->openForm(($this->standalone ? $this->link.$hostid : $this->link), $host);

		// Check tabs available in the form.
		$tabs = ['Host', 'IPMI', 'Tags', 'Macros', 'Inventory', 'Encryption', 'Value mapping'];
		$this->assertEquals(count($tabs), $form->query('xpath:.//li[@role="tab"]')->all()->count());
		foreach ($tabs as $tab) {
			$this->assertTrue($form->query("xpath:.//li[@role='tab']//a[text()=".CXPathHelper::escapeQuotes($tab)."]")
					->one()->isValid()
			);
		}

		// Host form fields maxlength attribute.
		foreach (['Host name' => 128, 'Visible name' => 128, 'Description' => 65535] as $field => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($field)->getAttribute('maxlength'));
		}

		$interfaces_form = $form->getFieldContainer('Interfaces')->asHostInterfaceElement();
		$this->assertEquals(['', 'Type', 'IP address', 'DNS name', 'Connect to', 'Port', 'Default'],
				$interfaces_form->getHeadersText()
		);

		foreach ($interfaces_form->getRows() as $i => $row) {
			$enabled = ($i !== 0 && $i !== 2);
			// "Remove" button is disabled (in the 7th column) for Agent (in 0th row) and JMX (in 2nd row) interfaces.
			$this->assertTrue($row->getColumn(7)->query('tag:button')->one()->isEnabled($enabled));
		}
		// Interface fields maxlength attribute.
		foreach (['IP address' => 64, 'DNS name' => 255, 'Port' => 64] as $field => $maxlength) {
			$this->assertEquals($maxlength, $interfaces_form->getRow(0)->getColumn($field)
					->query('tag:input')->one()->getAttribute('maxlength')
			);
		}

		// Click the "expand" icon (in the 0th column) for the SNMP interface (1st row).
		$interfaces_form->getRow(1)->getColumn(0)->query('tag:button')->one()->click();
		$snmp_form = $interfaces_form->getRow(1)->query('xpath:.//div[@class="form-grid"]')->one()->parents()
				->asForm(['normalized' => true])->one();
		$data = [
			'SNMPv1' => ['SNMP version', 'SNMP community'],
			'SNMPv2' => ['SNMP version', 'SNMP community'],
			'SNMPv3' => ['SNMP version', 'Context name', 'Security name', 'Security level'],
			'authNoPriv' => ['SNMP version', 'Context name', 'Security name', 'Security level',
				'Authentication protocol', 'Authentication passphrase'
			]
		];

		// The SNMP interface has specific fields depending on the SNMP version and protocol.
		foreach ($data as $field => $labels) {
			$select = ($field === 'authNoPriv') ? 'Security level' : 'SNMP version';
			$snmp_form->fill([$select => $field]);
			$this->assertEquals($labels, array_values($snmp_form->getLabels()
					->filter(new CElementFilter(CElementFilter::VISIBLE))->asText())
			);
			$this->assertFalse($snmp_form->query('xpath:.//label[text()="Use bulk requests"]')->one()
					->asCheckbox()->isChecked()
			);
		}

		// Close host form popup to avoid unexpected alert in further cases.
		if (!$this->standalone) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	public static function getCreateData() {
		return [
			// Host form without mandatory values.
			[
				[
					'expected' => TEST_BAD,
					'error' => ['Field "groups" is mandatory.', 'Incorrect value for field "host": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Groups' => 'Zabbix servers'
					],
					'error' => 'Incorrect value for field "host": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty host group'
					],
					'error' => 'Field "groups" is mandatory.'
				]
			],
			// Existing host name and visible name.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Available host',
						'Groups' => 'Zabbix servers'
					],
					'error_title' => 'Cannot add host',
					'error' => 'Host with the same name "Available host" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty template',
						'Groups' => 'Zabbix servers'
					],
					'error_title' => 'Cannot add host',
					'error' => 'Template with the same name "Empty template" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Existen visible name',
						'Groups' => 'Zabbix servers',
						'Visible name' => 'ЗАББИКС Сервер'
					],
					'error_title' => 'Cannot add host',
					'error' => 'Host with the same visible name "ЗАББИКС Сервер" already exists.'
				]
			],
			// Host name field validation.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => '@#$%^&*()_+',
						'Groups' => 'Zabbix servers'
					],
					'error_title' => 'Cannot add host',
					'error' => 'Incorrect characters used for host name "@#$%^&*()_+".'
				]
			],
			// UTF8MB4 check.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Groups' => 'Zabbix servers',
						'Host name' => '😀'
					],
					'error_title' => 'Cannot add host',
					'error' => 'Incorrect characters used for host name "😀".'
				]
			],
			// Interface fields validation.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty ip address',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => ''
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'IP and DNS cannot be empty for host interface.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty dns',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => '',
							'Connect to' => 'DNS'
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'IP and DNS cannot be empty for host interface.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty IP and filled in DNS',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => '',
							'dns' => 'test'
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Interface with DNS "test" cannot have empty IP address.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty dns and filled in IP',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'Connect to' => 'DNS'
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Interface with IP "127.0.0.1" cannot have empty DNS name while having'.
						' "Use DNS" property on "Empty dns and filled in IP".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty port',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'port' => ''
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Port cannot be empty for host interface.'
				]
			],
			// IP validation.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid ip',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => 'test'
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Invalid IP address "test".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid ip',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => '127.0.0.'
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Invalid IP address "127.0.0.".'
				]
			],
			// Port validation.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid port',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'port' => '100500'
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Incorrect interface port "100500" provided.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid port',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'IPMI',
							'port' => 'test'
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Incorrect interface port "test" provided.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid port',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'JMX',
							'port' => '10.5'
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Incorrect interface port "10.5" provided.'
				]
			],
			// Empty SNMP community.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid snmp community',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'SNMP community' => ''
						]
					],
					'error_title' => 'Cannot add host',
					'error' => 'Incorrect arguments passed to function.'
				]
			],
			// Host without interface.
			[
				[
					'expected' => TEST_GOOD,
					'host_fields' => [
						'Host name' => 'Host without interfaces',
						'Groups' => 'Zabbix servers'
					]
				]
			],
			// UTF8MB4 check.
			[
				[
					'expected' => TEST_GOOD,
					'host_fields' => [
						'Host name' => 'Host with utf8mb4 visible name',
						'Groups' => 'Zabbix servers',
						'Visible name' => '😀',
						'Description' => '😀🙃😀'
					]
				]
			],
			// Default values of all interfaces.
			[
				[
					'expected' => TEST_GOOD,
					'host_fields' => [
						'Host name' => 'Host with default values of all interfaces',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'JMX'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'IPMI'
						]
					]
				]
			],
			// Change default host interface.
			[
				[
					'expected' => TEST_GOOD,
					'default_values' => true,
					'host_fields' => [
						'Host name' => 'Host with default second agent interface',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => '127.1.1.1',
							'port' => '111'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => '127.2.2.2',
							'port' => '222',
							'Default' => true
						]
					]
				]
			],
			// Different versions of SNMP interface and encryption.
			[
				[
					'expected' => TEST_GOOD,
					'host_fields' => [
						'Host name' => 'Host with different versions of SNMP interface',
						'Groups' => 'Zabbix servers'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'SNMP version' => 'SNMPv1',
							'SNMP community' => 'test'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'SNMP version' => 'SNMPv2',
							'SNMP community' => '{$SNMP_TEST}'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'SNMP version' => 'SNMPv3',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA224'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'SNMP version' => 'SNMPv3',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA256',
							'Privacy protocol' => 'AES192'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'SNMP version' => 'SNMPv3',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA384',
							'Privacy protocol' => 'AES192C'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'SNMP version' => 'SNMPv3',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA512',
							'Privacy protocol' => 'AES256C'
						]
					]
				]
			],
			// All interfaces and all fields in form.
			[
				[
					'expected' => TEST_GOOD,
					'host_fields' => [
						'Host name' => 'Host with all interfaces',
						'Visible name' => 'Host with all interfaces visible name',
						'Groups' => 'Zabbix servers',
						'Description' => 'Added description for host with all interfaces',
						'Monitored by proxy' => 'Active proxy 1',
						'Enabled' => false
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => '::1',
							'dns' => '1211',
							'Connect to' => 'DNS',
							'port' => '100'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'ip' => '',
							'dns' => 'localhost',
							'Connect to' => 'DNS',
							'port' => '200',
							'SNMP version' => 'SNMPv3',
							'Context name' => 'aaa',
							'Security name' => 'bbb',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA1',
							'Authentication passphrase' => 'ccc',
							'Privacy protocol' => 'AES128',
							'Privacy passphrase' => 'ddd',
							'Use bulk requests' => false
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'ip' => '0:0:0:0:0:ffff:7c01:101',
							'dns' => 'test',
							'port' => '500',
							'SNMP version' => 'SNMPv1',
							'SNMP community' => 'test',
							'Use bulk requests' => true
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'JMX',
							'dns' => '1333',
							'Connect to' => 'DNS',
							'port' => '300'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'IPMI',
							'ip' => '127.2.2.2',
							'dns' => '1444',
							'port' => '400'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'IPMI',
							'ip' => '127.3.3.3',
							'dns' => '1444',
							'Connect to' => 'DNS',
							'port' => '500',
							'Default' => true
						]
					]
				]
			]
		];
	}

	/**
	 * Test for checking new host creation form.
	 *
	 * @param array     $data          data provider
	 */
	public function checkHostCreate($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->hosts_sql);
			$interface_old_hash = CDBHelper::getHash($this->interface_snmp_sql);
		}

		$this->page->login()->open($this->link)->waitUntilReady();

		if ($this->standalone) {
			$form = $this->query('id:host-form')->asForm()->waitUntilReady()->one();
		}
		else {
			$this->query('button:Create host')->one()->waitUntilClickable()->click();
			$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		}

		$form->fill(CTestArrayHelper::get($data, 'host_fields', []));

		// Set name for field "Default".
		$names = ['1' => 'default'];
		$interfaces_form = $form->getFieldContainer('Interfaces')->asHostInterfaceElement(['names' => $names]);
		$interfaces = CTestArrayHelper::get($data, 'interfaces', []);
		$interfaces_form->fill($interfaces);
		$form->submit();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, 'Host added');
				$this->page->assertTitle($this->monitoring ? 'Hosts' : 'Configuration of hosts');

				// Check host fields.
				$host = CTestArrayHelper::get($data, 'host_fields.Visible name', $data['host_fields']['Host name']);

				$form = $this->filterAndSelectHost($host);
				$form->checkValue($data['host_fields']);

				foreach ($interfaces as &$interface) {
					$interface['action'] = CTestArrayHelper::get($interface, 'action', USER_ACTION_ADD);

					// Add default values for interface, if it added without any values except action and type.
					if (count($interface) === 2 && $interface['action'] === USER_ACTION_ADD) {
						$interface = $this->default_values[$interface['type']];
					}

					unset($interface['index'], $interface['action'], $interface['type']);
				}
				unset($interface);

				$data['interfaces'] = $interfaces;

				// Check host fields in DB.
				$this->assertDatabaseFields($data);

				// Check interfaces field values.
				$form->getFieldContainer('Interfaces')->asHostInterfaceElement(['names' => $names])
						->checkValue($data['interfaces']);

				COverlayDialogElement::find()->one()->close();
				break;

			case TEST_BAD:
				$this->assertEquals($old_hash, CDBHelper::getHash($this->hosts_sql));
				$this->assertEquals($interface_old_hash, CDBHelper::getHash($this->interface_snmp_sql));
				$this->assertMessage(TEST_BAD, CTestArrayHelper::get($data, 'error_title', 'Cannot add host'), $data['error']);

				if (!$this->standalone) {
					COverlayDialogElement::find()->one()->close();
				}
				break;
		}
	}

	public static function getValidationUpdateData() {
		return [
			// Host form validation.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => '',
						'Groups' => ''
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						]
					],
					'error' => ['Field "groups" is mandatory.', 'Incorrect value for field "host": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => '',
						'Groups' => ''
					],
					'error' => ['Field "groups" is mandatory.', 'Incorrect value for field "host": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => ''
					],
					'error' => 'Incorrect value for field "host": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty host group',
						'Groups' => ''
					],
					'error' => 'Field "groups" is mandatory.'
				]
			],
			// Existing host name and visible name.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Available host'
					],
					'error_title' => 'Cannot update host',
					'error' => 'Host with the same name "Available host" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty template'
					],
					'error_title' => 'Cannot update host',
					'error' => 'Template with the same name "Empty template" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Visible name' => 'ЗАББИКС Сервер'
					],
					'error_title' => 'Cannot update host',
					'error' => 'Host with the same visible name "ЗАББИКС Сервер" already exists.'
				]
			],
			// Host name field validation.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => '@#$%^&*()_+'
					],
					'error_title' => 'Cannot update host',
					'error' => 'Incorrect characters used for host name "@#$%^&*()_+".'
				]
			],
			// UTF8MB4 check.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => '😀'
					],
					'error_title' => 'Cannot update host',
					'error' => 'Incorrect characters used for host name "😀".'
				]
			],
			// Interface fields validation.
			[
				[
					'expected' => TEST_BAD,
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'ip' => ''
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'IP and DNS cannot be empty for host interface.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty dns'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'ip' => '',
							'Connect to' => 'DNS'
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'IP and DNS cannot be empty for host interface.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty IP and filled in DNS'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'ip' => '',
							'dns' => 'test'
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Interface with DNS "test" cannot have empty IP address.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty dns and filled in IP'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Connect to' => 'DNS'
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Interface with IP "127.1.1.1" cannot have empty DNS name while having'.
						' "Use DNS" property on "Empty dns and filled in IP".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Empty port'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'port' => ''
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Port cannot be empty for host interface.'
				]
			],
			// IP validation.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid ip'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'ip' => 'test'
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Invalid IP address "test".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid ip'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'ip' => '127.0.0.'
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Invalid IP address "127.0.0.".'
				]
			],
			// Port validation.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid port'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'port' => '100500'
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Incorrect interface port "100500" provided.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid port'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'port' => 'test'
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Incorrect interface port "test" provided.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid port'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'port' => '10.5'
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Incorrect interface port "10.5" provided.'
				]
			],
			// Empty SNMP community.
			[
				[
					'expected' => TEST_BAD,
					'host_fields' => [
						'Host name' => 'Invalid snmp community'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'SNMP community' => ''
						]
					],
					'error_title' => 'Cannot update host',
					'error' => 'Incorrect arguments passed to function.'
				]
			]
		];
	}

	public static function getUpdateData() {
		return [
			// Successful host form update.
			// Add default interface values.
			[
				[
					'expected' => TEST_GOOD,
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'JMX'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'IPMI'
						]
					]
				]
			],
			// Set "Default" option to another interface.
			[
				[
					'expected' => TEST_GOOD,
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent',
							'ip' => '',
							'dns' => 'agent',
							'Connect to' => 'DNS',
							'port' => '10054',
							'Default' => true
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'ip' => '',
							'dns' => 'snmp',
							'Connect to' => 'DNS',
							'port' => '166',
							'SNMP version' => 'SNMPv3',
							'Context name' => 'zabbix',
							'Security name' => 'selenium',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA1',
							'Authentication passphrase' => 'test123',
							'Privacy protocol' => 'AES128',
							'Privacy passphrase' => '456test',
							'Default' => true
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'IPMI',
							'ip' => '',
							'dns' => 'ipmi',
							'Connect to' => 'DNS',
							'port' => '500',
							'Default' => true
						]
					]
				]
			],
			// Remove all interfaces except JMX.
			[
				[
					'expected' => TEST_GOOD,
					'interfaces' => [
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 1
						]
					]
				]
			],
			// Update all fields.
			[
				[
					'expected' => TEST_GOOD,
					'host_fields' => [
						'Host name' => 'Update host with all interfaces',
						'Visible name' => 'Update host with all interfaces visible name',
						'Groups' => 'Linux servers',
						'Description' => 'Update description',
						'Monitored by proxy' => 'Active proxy 3',
						'Enabled' => false
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'ip' => '',
							'dns' => 'zabbix.com',
							'Connect to' => 'DNS'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'ip' => '',
							'dns' => 'zabbix.com',
							'port' => '122',
							'Connect to' => 'DNS',
							'SNMP version' => 'SNMPv3',
							'Context name' => 'new-zabbix',
							'Security name' => 'new-selenium',
							'Security level' => 'authNoPriv',
							'Authentication protocol' => 'SHA384',
							'Authentication passphrase' => 'new-test123',
							'Use bulk requests' => true
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'ip' => '',
							'dns' => 'zabbix.com',
							'Connect to' => 'DNS'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 3,
							'ip' => '',
							'dns' => 'zabbix.com',
							'Connect to' => 'DNS'
						]
					]
				]
			],
			// UTF8MB4 check.
			[
				[
					'expected' => TEST_GOOD,
					'host_fields' => [
						'Host name' => 'Update host with utf8 visible name',
						'Groups' => 'Linux servers',
						'Visible name' => '😀😀',
						'Description' => '😀😀😀😀😀😀😀'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent'
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 1
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 1
						]
					]
				]
			],
			// Add two agent interfaces and remove it.
			[
				[
					'expected' => TEST_GOOD,
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent'
						],
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent'
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 1
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 1
						]
					]
				]
			],
			// Mixed actions Add, Remove, Update interfaces.
			[
				[
					'expected' => TEST_GOOD,
					'host_fields' => [
						'Host name' => 'Mixed interface actions',
						'Visible name' => '',
						'Groups' => 'Discovered hosts',
						'Description' => '',
						'Monitored by proxy' => '(no proxy)'
					],
					'interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'Agent'
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 3
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'port' => '501',
							'SNMP version' => 'SNMPv3',
							'Context name' => 'new-zabbix',
							'Security name' => 'new-selenium',
							'Security level' => 'noAuthNoPriv',
							'Use bulk requests' => true
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 3
						]
					]
				]
			]
		];
	}

	/**
	 * Test for checking existing host update form.
	 *
	 * @param array     $data          data provider
	 */
	public function checkHostUpdate($data) {
		if ($data['expected'] === TEST_BAD) {
			$host_old_hash = CDBHelper::getHash($this->hosts_sql);
			$interface_old_hash = CDBHelper::getHash($this->interface_snmp_sql);
		}

		$source = [
			'host_fields' => [
				'Host name' => 'testFormHost_Update',
				'Visible name' => 'testFormHost_Update Visible name',
				'Groups' => 'Zabbix servers',
				'Description' => 'Created host via API to test update functionality in host form and interfaces',
				'Monitored by proxy' => 'Active proxy 2',
				'Enabled' => true
			],
			'interfaces' => [
				'Agent' => [
					[
						'type' => 'Agent',
						'ip' => '127.1.1.1',
						'dns' => '',
						'Connect to' => 'IP',
						'port' => '10011'
					]
				],
				'SNMP' => [
					[
						'ip' => '127.2.2.2',
						'dns' => '',
						'Connect to' => 'IP',
						'port' => '122',
						'SNMP version' => 'SNMPv1',
						'SNMP community' => '🙃zabbix🙃',
						'Use bulk requests' => false
					]
				],
				'JMX' => [
					[
						'type' => 'JMX',
						'ip' => '127.4.4.4',
						'dns' => '',
						'Connect to' => 'IP',
						'port' => '426'
					]
				],
				'IPMI' => [
					[
						'type' => 'JMX',
						'ip' => '',
						'dns' => 'selenium.test',
						'Connect to' => 'DNS',
						'port' => '30053'
					]
				]
			]
		];

		$host = 'testFormHost_Update Visible name';
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr($host));

		$form = $this->openForm(($this->standalone ? $this->link.$hostid : $this->link), $host);
		$form->fill(CTestArrayHelper::get($data, 'host_fields', []));

		// Set name for field "Default".
		$names = ['1' => 'default'];
		$interfaces_form = $form->getFieldContainer('Interfaces')->asHostInterfaceElement(['names' => $names]);
		$interfaces_form->fill(CTestArrayHelper::get($data, 'interfaces', []));
		$form->submit();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, 'Host updated');

				$host = (CTestArrayHelper::get($data, 'host_fields.Visible name') === "")
					? CTestArrayHelper::get($data, 'host_fields.Host name', 'testFormHost_Update')
					: CTestArrayHelper::get($data, 'host_fields.Visible name', 'testFormHost_Update Visible name');

				$form = $this->filterAndSelectHost($host);

				// Update or add new source data from host data.
				foreach (CTestArrayHelper::get($data, 'host_fields', []) as $key => $value) {
					$source['host_fields'][$key] = $value;
				}

				// Check host fields.
				$form->checkValue($source['host_fields']);

				// Preparing reference data for interfaces.
				foreach ($data['interfaces'] as $i => $interface) {
					$interface['action'] = CTestArrayHelper::get($interface, 'action', USER_ACTION_ADD);

					switch ($interface['action']) {
						case USER_ACTION_ADD:
							$type = CTestArrayHelper::get($interface, 'type', 'Agent');
							// Add default values for interface, if it added without any values, except action and type.
							if (count($interface) === 2) {
								$interface = $this->default_values[$interface['type']];
								$interface['type'] = $type;
							}

							$source['interfaces'][$type][] = $interface;
							break;

						case USER_ACTION_REMOVE:
							foreach (array_keys($source['interfaces']) as $type) {
								$count = count($source['interfaces'][$type]);
								if ($interface['index'] >= $count) {
									$interface['index'] -= $count;
								}
								else {
									unset($source['interfaces'][$type][$interface['index']]);
									// Reindex the keys to start from 0.
									$source['interfaces'][$type] = array_values($source['interfaces'][$type]);
									break;
								}
							}
							break;

						case USER_ACTION_UPDATE:
							foreach (array_keys($source['interfaces']) as $type) {
								$count = count($source['interfaces'][$type]);
								if ($interface['index'] >= $count) {
									$interface['index'] -= $count;
								}
								else {
									// Update or add new source interface fields from host data.
									foreach ($interface as $key => $value) {
										$source['interfaces'][$type][$interface['index']][$key] = $value;
									}

									if (CTestArrayHelper::get($interface, 'SNMP version', false) === 'SNMPv3') {
										// 'SNMP community' used only by SNMPv1 and SNMPv2 interfaces.
										unset($source['interfaces']['SNMP'][0]['SNMP community']);
									}
									break;
								}
							}
							break;
					}
				}

				// Remove interface type as array key in source data.
				$source['interfaces'] = array_merge($source['interfaces']['Agent'], $source['interfaces']['SNMP'],
						$source['interfaces']['JMX'], $source['interfaces']['IPMI']);

				// Remove unnecessary keys from source array to check the values on frontend.
				foreach ($source['interfaces'] as &$interface) {
					unset($interface['index'], $interface['action'], $interface['type']);
				}
				unset($interface);

				// Check host fields in DB.
				$this->assertDatabaseFields($source);

				// Check interfaces field values.
				$form->getFieldContainer('Interfaces')->asHostInterfaceElement(['names' => $names])
						->checkValue($source['interfaces']);

				COverlayDialogElement::find()->one()->close();
				break;

			case TEST_BAD:
				$this->assertEquals($host_old_hash, CDBHelper::getHash($this->hosts_sql));
				$this->assertEquals($interface_old_hash, CDBHelper::getHash($this->interface_snmp_sql));

				$error_title = CTestArrayHelper::get($data, 'error_title', 'Cannot update host');
				$this->assertMessage(TEST_BAD, $error_title, $data['error']);

				if (!$this->standalone) {
					COverlayDialogElement::find()->one()->close();
				}
				break;
		}
	}

	/**
	 * Update the host without any changes and check host and interfaces hashes.
	 */
	public function checkHostSimpleUpdate() {
		$host_old_hash = CDBHelper::getHash($this->hosts_sql);
		$interface_old_hash = CDBHelper::getHash($this->interface_snmp_sql);

		$host = 'testFormHost_Update';
		$visible_name = 'testFormHost_Update Visible name';
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host));

		$form = $this->openForm(($this->standalone ? $this->link.$hostid : $this->link), $visible_name);
		$this->page->waitUntilReady();
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Host updated');

		$this->assertEquals($host_old_hash, CDBHelper::getHash($this->hosts_sql));
		$this->assertEquals($interface_old_hash, CDBHelper::getHash($this->interface_snmp_sql));
	}

	public static function getCloneData() {
		return [
			[
				[
					'Host name' => microtime().' clone without interface changes'

				]
			],
			[
				[
					'Host name' => microtime().' clone with interface changes',
					'Visible name' => microtime().'😀😀😀',
					'Description' => '😀😀😀😀😀😀😀',
					'Interfaces' => [
						[
							'action' => USER_ACTION_ADD,
							'type' => 'SNMP',
							'ip' => '127.3.3.3',
							'dns' => '',
							'Connect to' => 'IP',
							'port' => '122',
							'SNMP version' => 'SNMPv3',
							'Context name' => 'zabbix',
							'Security name' => 'selenium',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA256',
							'Authentication passphrase' => 'test123',
							'Privacy protocol' => 'AES256',
							'Privacy passphrase' => '456test',
							'Use bulk requests' => false
						]
					]
				]
			]
		];
	}

	/**
	 * Clone or Full clone a host and compare the data with the original host.
	 *
	 * @param array     $data		   data provider with fields values
	 * @param string    $button        Clone or Full clone
	 */
	public function cloneHost($data, $button = 'Clone') {
		$host = 'testFormHost with items';
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host));
		$form = $this->openForm(($this->standalone ? 'zabbix.php?action=host.edit&hostid='.$hostid : $this->link), $host);

		// Get values from form.
		$form->fill($data);
		$original = $form->getFields()->asValues();

		// Clone host.
		$this->query('button', $button)->waitUntilClickable()->one()->click();

		$cloned_form = (!$this->standalone)
			? COverlayDialogElement::find()->asForm()->waitUntilReady()->one()
			: $this->query('id:host-form')->asForm()->waitUntilVisible()->one();

		$cloned_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Host added');

		// Check the values of the original host with the cloned host.
		$this->filterAndSelectHost((CTestArrayHelper::get($data, 'Visible name', $data['Host name'])) )
				->checkValue($original);
		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Check host fields data with data in DB.
	 *
	 * @param array $data	data provider with fields values
	 */
	private function assertDatabaseFields($data) {
		$db_default = [
			'name' => $data['host_fields']['Host name'],
			'description' => '',
			'status' => 0
		];
		$db_host = CDBHelper::getRow('SELECT hostid, host, name, description, status FROM hosts WHERE host='.
				zbx_dbstr($data['host_fields']['Host name'])
		);

		if (CTestArrayHelper::get($data, 'host_fields.Visible name') === "") {
			$data['host_fields']['Visible name'] = $data['host_fields']['Host name'];
		}

		$fields = ['Host name' => 'host', 'Visible name' => 'name', 'Description' => 'description', 'Enabled' => 'status'];
		foreach ($fields as $ui_field => $db_field) {
			if (array_key_exists($ui_field, $data['host_fields'])) {
				if ($ui_field === 'Enabled') {
					$data['host_fields'][$ui_field] = ($data['host_fields'][$ui_field] === false)
							? HOST_STATUS_NOT_MONITORED
							: HOST_STATUS_MONITORED;
				}
				$this->assertEquals($data['host_fields'][$ui_field], $db_host[$db_field]);
			}
			else {
				$this->assertEquals($db_default[$db_field], $db_host[$db_field]);
			}
		}

		// Check interfaces amount in DB.
		$db_interfaces = CDBHelper::getCount('SELECT NULL FROM interface WHERE hostid='.$db_host['hostid']);
		$this->assertEquals(count($data['interfaces']), $db_interfaces);
	}

	public static function getCancelData() {
		return [
			[
				[
					'action' => 'Add'
				]
			],
			[
				[
					'action' => 'Update'
				]
			],
			[
				[
					'action' => 'Clone'
				]
			],
			[
				[
					'action' => 'Full clone'
				]
			],
			[
				[
					'action' => 'Delete'
				]
			]
		];
	}

	/**
	 * Test for checking host actions cancelling.
	 *
	 * @param array     $data		   data provider with fields values
	 */
	public function checkCancel($data) {
		$host_old_hash = CDBHelper::getHash($this->hosts_sql);
		$interface_old_hash = CDBHelper::getHash($this->interface_snmp_sql);

		$host = 'testFormHost with items';
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host));
		$new_name = microtime(true).' Cancel '.$host;

		$interface = [
			[
				'action' => USER_ACTION_ADD,
				'type' => 'SNMP',
				'ip' => '0:0:0:0:0:ffff:7c01:101',
				'dns' => 'test',
				'port' => '500',
				'SNMP version' => 'SNMPv1',
				'SNMP community' => 'test',
				'Use bulk requests' => false
			]
		];

		if ($data['action'] === 'Add') {
			$this->page->login()->open($this->link)->waitUntilReady();

			if (!$this->standalone) {
				$this->query('button:Create host')->one()->waitUntilClickable()->click();
				$form = COverlayDialogElement::find()->waitUntilReady()->one()->asForm();
			}
			else {
				$form = $this->query('id:host-form')->asForm()->waitUntilReady()->one();
			}
		}
		else {
			$form = $this->openForm(($this->standalone ? 'zabbix.php?action=host.edit&hostid='.$hostid : $this->link), $host);
		}

		$form_type = ($this->standalone) ? $form : COverlayDialogElement::find()->waitUntilReady()->one();

		// Change the host data to make sure that the changes are not saved to the database after cancellation.
		$form->fill(['Host name' => $new_name]);
		$interfaces_form = $form->getFieldContainer('Interfaces')->asHostInterfaceElement(['names' => ['1' => 'default']]);
		$interfaces_form->fill($interface);

		if (in_array($data['action'], ['Clone', 'Full clone', 'Delete'])) {
			$form_type->query('button', $data['action'])->one()->click();
		}
		if ($data['action'] === 'Delete') {
			$this->page->dismissAlert();
		}

		$this->page->waitUntilReady();

		// Check that the host creation page is open after cloning or full cloning.
		if ($data['action'] === 'Clone' || $data['action'] === 'Full clone') {
			$form_type->invalidate();
			$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host));
			$action = ($data['action'] === 'Clone') ? 'clone' : 'full_clone';
			$expected_url = PHPUNIT_URL.'zabbix.php?action=host.edit&hostid='.$id.'&'.$action.'=1';

			$this->assertEquals($expected_url, $this->page->getCurrentUrl());
			$this->assertFalse($form_type->query("xpath:.//ul[".CXPathHelper::fromClass('filter-breadcrumb')."]")
					->one(false)->isValid()
			);
			$this->assertFalse($form_type->query('button', ['Update', 'Clone', 'Full clone', 'Delete'])->one(false)
					->isValid()
			);
			$this->assertTrue($form_type->query('button', ['Add', 'Cancel'])->one(false)->isValid());
		}

		$form_type->query('button:Cancel')->waitUntilClickable()->one()->click();

		// Check invariability of host data in the database.
		$this->assertEquals($host_old_hash, CDBHelper::getHash($this->hosts_sql));
		$this->assertEquals($interface_old_hash, CDBHelper::getHash($this->interface_snmp_sql));
		$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM hosts WHERE host='.zbx_dbstr($new_name)));
	}

	public static function getDeleteData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testFormHost with items'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Host for suppression',
					'error' => 'Cannot delete host "Host for suppression" because maintenance "Maintenance for suppression test"'.
							' must contain at least one host or host group.'
				]
			]
		];
	}

	/**
	 * Test for checking host deleting.
	 *
	 * @param array     $data		   data provider with fields values
	 */
	public function checkDelete($data) {
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['name']));

		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->hosts_sql);
		}
		else {
			// Get snmp interface ids.
			$interfaceids = CDBHelper::getAll('SELECT interfaceid FROM interface WHERE hostid='.$hostid.' AND type=2');
			$ids = array_column($interfaceids, 'interfaceid');
		}

		$form = $this->openForm(($this->standalone ? $this->link.$hostid : $this->link), $data['name']);
		$form_type = ($this->standalone) ? $form : COverlayDialogElement::find()->waitUntilReady()->one();
		$form_type->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, 'Host deleted');

				// Check if all host records have been deleted.
				$tables = ['hosts', 'interface', 'items', 'hostmacro', 'hosts_groups', 'hosts_templates',
						'maintenances_hosts', 'host_inventory'];

				foreach ($tables as $table) {
					$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM '.$table.' WHERE hostid='.$hostid));
				}
				$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM interface_snmp'.
						' WHERE interfaceid IN ('.CDBHelper::escape($ids).')')
				);
				break;

			case TEST_BAD:
				$this->assertEquals($old_hash, CDBHelper::getHash($this->hosts_sql));
				$this->assertMessage(TEST_BAD, 'Cannot delete host', $data['error']);
		}
	}

	/**
	 * Function for opening host form.
	 *
	 * @param string   $url     url of page where host form  is opened
	 * @param string   $host    host name to be opened
	 */
	private function openForm($url, $host) {
		$this->page->login()->open($url)->waitUntilReady();

		return ($this->standalone)
			? $this->query('id:host-form')->asForm()->waitUntilReady()->one()
			: $this->filterAndSelectHost($host);
	}

	/**
	 * Function for filtering necessary host on page.
	 *
	 * @param string	$host    host name to be filtered
	 *
	 * @return CFormElement
	 */
	public function filterAndSelectHost($host) {
		$this->query('button:Reset')->one()->click();
		$this->query('name:zbx_filter')->asForm()->waitUntilReady()->one()->fill(['Name' => $host]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();

		$host_link = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->waitUntilVisible()
				->findRow('Name', $host)->getColumn('Name')->query('tag:a')->waitUntilClickable();

		if ($this->monitoring) {
			$host_link->asPopupButton()->one()->select('Configuration');
		}
		else {
			$host_link->one()->click();
		}

		return COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
	}

	/**
	 * Function for selecting and counting items on particular host.
	 *
	 * @param boolean   $host     name of host
	 * @param string	$count    expected items count
	 */
	public function assertItemsDBCount($host, $count) {
		$sql = 'SELECT null FROM items WHERE hostid IN (SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host).')';
		$this->assertEquals($count, CDBHelper::getCount($sql));
	}
}
