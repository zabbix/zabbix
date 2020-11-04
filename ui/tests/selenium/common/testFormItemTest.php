<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../../include/items.inc.php';
require_once dirname(__FILE__).'/../traits/MacrosTrait.php';
require_once dirname(__FILE__).'/../traits/PreprocessingTrait.php';

/**
 * Base class for "Test item" function tests.
 */
class testFormItemTest extends CWebTest {

	use MacrosTrait;
	use PreprocessingTrait;

	/**
	 * Test item button state data for item, item prototype and LLD.
	 */
	public function getCommonTestButtonStateData() {
		return [
				['Type' => 'Zabbix agent (active)'],
				['Type' => 'Simple check'],
				['Type' => 'SNMP agent','SNMP OID' => '[IF-MIB::]ifInOctets.1'],
				['Type' => 'Zabbix internal'],
				['Type' => 'Zabbix trapper'],
				['Type' => 'External check'],
				['Type' => 'Database monitor', 'SQL query' => 'query'],
				['Type' => 'HTTP agent', 'URL' => 'https://www.zabbix.com'],
				['Type' => 'IPMI agent', 'IPMI sensor' => 'Sensor'],
				['Type' => 'SSH agent', 'Key' => 'ssh.run[Description,127.0.0.1,50]', 'User name' => 'Name', 'Executed script' => 'Script'],
				['Type' => 'TELNET agent', 'Key' => 'telnet'],
				['Type' => 'JMX agent', 'Key' => 'jmx','JMX endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi', 'User name' => ''],
				['Type' => 'Dependent item', 'Key'=>'dependent', 'Master item' => 'Master item']
		];
	}

	/*
	 * Test item button state data for item and item prototype.
	 */
	public function getItemTestButtonStateData() {
		return array_merge($this->getCommonTestButtonStateData(), [
				['Type' => 'SNMP trap', 'Key' => 'snmptrap.fallback'],
				['Type' => 'Zabbix aggregate', 'Key' => 'grpmax["Zabbix",key,last]'],
				['Type' => 'Calculated', 'Formula' => '"formula"']
		]);
	}

	/**
	 * Check test item button state depending on item type.
	 *
	 * @param arary		$data			data provider
	 * @param string	$item_name		item given name
	 * @param string	$create_link	url for creating new item
	 * @param string	$saved_link		url for opening saved item
	 * @param string	$item_type		type of an item: item, prototype or lld rule
	 * @param string	$success_text	text part of a success message
	 * @param boolean	$check_now		possibility of executing item instantly
	 * @param boolean	$is_host		true if host, false if template
	 */
	public function checkTestButtonState($data, $item_name, $create_link, $saved_link,
			$item_type, $success_text, $check_now, $is_host) {
		$this->page->login()->open($create_link);
		$item_form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();

		// Create item.
		$item_form->fill([
			'Name' => $item_name,
			'Type' => 'Zabbix agent',
			'Key' => 'key'
		]);
		// Check Test item button.
		$this->checkTestButtonInPreprocessing($item_type);
		$this->saveFormAndCheckMessage($item_type.$success_text);
		$itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE name='
			.zbx_dbstr($item_name));

		// Open created item and change type.
		foreach ($data as $update) {
			$this->page->open($saved_link.$itemid);
			$item_form->invalidate();
			$type = $item_form->getField('Type')->getValue();

			for ($i = 0; $i < 2; $i++) {

				if ($type === 'IPMI agent' && $is_host === false) {
					$enabled = false;
				}
				else {
					$enabled = (!in_array($type, ['Zabbix agent (active)',
							'SNMP trap', 'Zabbix trapper','Dependent item'
					]));
				}

				$this->checkTestButtonInPreprocessing($item_type, $enabled, $i);
				/*
				 * Check "Execute now" button only in host case item saved form
				 * and then change type.
				 */
				if ($i === 0) {
					if ($check_now) {
						$execute_button = $this->query('id:check_now')
							->waitUntilVisible()->one();
						$this->assertTrue($execute_button->isEnabled($enabled));
					}

					$item_form->fill($update);
					// TODO: workaround for ZBXNEXT-5365
					if ($item_type === 'Item prototype'
						&& array_key_exists('Master item', $update)) {
							sleep(2);
							$item_form->getFieldContainer('Master item')
								->asMultiselect()->select($update['Master item']);
					}

					$type = $update['Type'];
				}
			}

			$this->saveFormAndCheckMessage($item_type.' updated');
		}
	}

	/**
	 * Test item button data for item, item prototype and LLD.
	 */
	public function getCommonTestItemData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'key.macro.in.preproc.steps'
					],
					'macros' => [
						[
							'macro' => '{$1}',
							'value' => 'Numeric macro'
						],
						[
							'macro' => '{$A}',
							'value' => 'Some text'
						],
						[
							'macro' => '{$_}',
							'value' => 'Underscore'
						]
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$A}', 'parameter_2' => '{$1}'],
						['type' => 'JSONPath', 'parameter_1' => '{$_}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'macro.in.key.and.preproc.steps[{$DEFAULT_DELAY}]'
					],
					'macros' => [
						[
							'macro' => '{$1}',
							'value' => 'Numeric macro'
						],
						[
							'macro' => '{$A}',
							'value' => 'Some text'
						],
						[
							'macro' => '{$_}',
							'value' => 'Underscore'
						],
						[
							'macro' => '{$DEFAULT_DELAY}',
							'value' => '30'
						]
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$A}', 'parameter_2' => '{$1}'],
						['type' => 'JSONPath', 'parameter_1' => '{$_}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.key'
					]

				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Simple check',
						'Key' => 'test.item.key'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'SNMP agent',
						'Key' => 'test.item.no.host.value'
					],
					'snmp_fields' => [
						'version' => 'SNMPv2'
					],
					'host_value' => false
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'SNMP agent',
						'Key' => 'snmp.v2'
					],
					'host_interface' => '127.0.0.2 : 161',
					'snmp_fields' => [
						'version' => 'SNMPv2',
						'comunity' => 'public'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'SNMP agent',
						'Key' => 'snmp.v1'
					],
					'host_interface' => '127.0.0.5 : 161',
					'snmp_fields' => [
						'version' => 'SNMPv1',
						'comunity' => 'public'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'SNMP agent',
						'Key' => 'snmp.v3'
					],
					'host_interface' => '127.0.0.6 : 161',
					'snmp_fields' => [
						'version' => 'SNMPv3',
						'context' => 'test_context',
						'security' => 'test_security_name',
						'security_level' => 'authPriv',
						'authentication_protocol' => 'SHA',
						'authentication_passphrase' => '{$TEST}',
						'privacy_protocol' => 'AES',
						'privacy_passphrase' => 'test_privpassphrase'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix internal',
						'Key' => 'test.zabbix.internal'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'External check',
						'Key' => 'test.external.check'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Database monitor',
						'Key' => 'test.db.monitor'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'HTTP agent',
						'Key' => 'test.http.agent'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'IPMI agent',
						'Key' => 'test.ipmi.agent'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'SSH agent',
						'Key' => 'test.ssh.agent'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'TELNET agent',
						'Key' => 'test.telnet.agent'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'JMX agent',
						'Key' => 'test.jmx.agent',
						'JMX endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi'
					],
					'macros' => [
						[
							'macro' => '{HOST.CONN}',
							'value' => '127.0.0.4'
						],
						[
							'macro' => '{HOST.PORT}',
							'value' => '12345'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => ''
					],
					'error' => 'Incorrect value for field "key_": key is empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'key space'
					],
					'error' => 'Incorrect value for field "key_": incorrect syntax near " space".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.no.fist.param'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => '2']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.no.second.param'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '1', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": second parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.no.params'
					],
					'preprocessing' => [
						['type' => 'XML XPath', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.no.custom.error'
					],
					'preprocessing' => [
						['type' => 'Regular expression',
						'parameter_1' => '1',
						'parameter_2' => '2',
						'on_fail' => true,
						'error_handler' => 'Set error to',
						'error_handler_params' => '']
					],
					'error' => 'Incorrect value for field "error_handler_params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'key.macro.preproc[param,{$A},{$1}]'
					],
					'macros' => [
						[
							'macro' => '{$A}',
							'value' => 'Some text'
						],
						[
							'macro' => '{$1}',
							'value' => 'Numeric macro'
						]
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system'],
						['type' => 'JSONPath', 'parameter_1' => '$.path']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.discard.unchanged.with.heartbeat'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.discard.unchanged.with.heartbeat'
					],
					'host_value' => false,
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.interface.trailing.spaces'
					],
					'interface' => ['address' => '  127.0.0.1   ', 'port' => '   10050    '],
				]
			]
		];
	}

	/**
	 * Test item button data for item.
	 */
	public function getItemTestItemData() {
		return array_merge($this->getCommonTestItemData(), [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix aggregate',
						'Key' => 'test.zabbix.aggregate'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Calculated',
						'Key' => 'test.calculated'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.discard.unchanged'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.discard.unchanged'
					],
					'host_value' => false,
					'preprocessing' => [
						['type' => 'Discard unchanged']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.simple.change'
					],
					'preprocessing' => [
						['type' => 'Simple change']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.simple.change'
					],
					'host_value' => false,
					'preprocessing' => [
						['type' => 'Simple change']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.change.per.second'
					],
					'preprocessing' => [
						['type' => 'Change per second']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'test.item.preproc.change.per.second'
					],
					'host_value' => false,
					'preprocessing' => [
						['type' => 'Change per second']
					]
				]
			]
		]);
	}

	/**
	 * Test item button data for item prototype.
	 */
	public function getPrototypeTestItemData() {
		return array_merge($this->getItemTestItemData(), [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Zabbix agent',
						'Key' => 'prototype.with.lld.macros[{#KEY1},{#KEY2}]'
					],
					'macros' => [
						[
							'macro' => '{#KEY1}',
							'value' => '{#KEY1}'
						],
						[
							'macro' => '{#KEY2}',
							'value' => '{#KEY2}'
						]
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system'],
						['type' => 'JSONPath', 'parameter_1' => '$.path']
					]
				]
			]
		]);
	}

	/**
	 * Check test item form.
	 *
	 * @param string	$create_link	url for creating new item
	 * @param arary		$data			data provider
	 * @param boolean	$is_host		true if host, false if template
	 */
	public function checkTestItem($create_link, $data, $is_host) {
		if (!$is_host && $data['fields']['Type'] === 'IPMI agent') {
			return;
		}

		$this->page->login()->open($create_link);
		$item_form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();

		$item_form->fill($data['fields']);

		if ($is_host) {
			// If host fill interface.
			if (CTestArrayHelper::get($data, 'host_interface')) {
				$item_form->getField('Host interface')->fill($data['host_interface']);
			}
			// Get ip and port separately.
			$host_interface = explode(' : ', $item_form->getField('Host interface')
				->getText(), 2);
		}

		if (CTestArrayHelper::get($data, 'preprocessing')){
			$item_form->selectTab('Preprocessing');
			$this->addPreprocessingSteps($data['preprocessing']);
		}

		// Open Test item dialog form.
		$this->query('id:test_item')->waitUntilVisible()->one()->click();
		$dialog = $this->query('xpath://div[@data-dialogueid="item-test" and @role="dialog"]')
			->waitUntilPresent()->asOverlayDialog()->one()->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertEquals('Test item', $dialog->getTitle());
				$test_form = $this->query('id:preprocessing-test-form')
					->waitUntilPresent()->one()->waitUntilReady();
				// Check "Get value from host" checkbox.
				$get_host_value = $test_form->query('id:get_value')->asCheckbox()->one();
				$this->assertTrue($get_host_value->isEnabled());
				$this->assertTrue($get_host_value->isChecked());

				if (CTestArrayHelper::get($data, 'snmp_fields.version') === 'SNMPv3') {
					$elements = [
						'address' => 'id:interface_address',
						'port' => 'id:interface_port',
						'proxy' => 'id:proxy_hostid',
						'version' => 'id:interface_details_version',
						'context' => 'id:interface_details_contextname',
						'security' => 'id:interface_details_securityname',
						'security_level' => 'id:interface_details_securitylevel',
						'authentication_protocol' => 'id:interface_details_authprotocol',
						'authentication_passphrase' => 'id:interface_details_authpassphrase',
						'privacy_protocol' => 'id:interface_details_privprotocol',
						'privacy_passphrase' => 'id:interface_details_privpassphrase'
					];
				}
				elseif (in_array(CTestArrayHelper::get($data, 'snmp_fields.version'), ['SNMPv1', 'SNMPv2'])) {
					$elements = [
						'address' => 'id:interface_address',
						'port' => 'id:interface_port',
						'proxy' => 'id:proxy_hostid',
						'version' => 'id:interface_details_version',
						'comunity' => 'id:interface_details_community'
					];
				}
				else {
					$elements = [
						'address' => 'id:interface_address',
						'port' => 'id:interface_port',
						'proxy' => 'id:proxy_hostid'
					];
				}

				foreach ($elements as $name => $selector) {
					$elements[$name] = $test_form->query($selector)->one()->detect();
				}
				$proxy = CDBHelper::getValue("SELECT host FROM hosts WHERE hostid IN "
					. "(SELECT proxy_hostid FROM hosts WHERE host = 'Test item host')");
				// Check test item form fields depending on item type.
				switch ($data['fields']['Type']) {
					case 'Zabbix agent':
					case 'IPMI agent':
						if ($is_host) {
							$fields_value = [
								'address' => $host_interface[0],
								'port' => $host_interface[1],
								'proxy' => $proxy
							];
						}
						else {
							$fields_value = [
								'address' => '',
								'port' => '',
								'proxy' => '(no proxy)'
							];
						}
						$fields_state = ['address' => true, 'port' => true, 'proxy' => true];
						break;

					case 'SNMP agent':
						if (CTestArrayHelper::get($data, 'snmp_fields.version') === 'SNMPv3') {
							if ($is_host) {
								$fields_value = [
									'address' => $host_interface[0],
									'port' => $host_interface[1],
									'proxy' => $proxy,
									'version' => 'SNMPv3',
									'context' => $data['snmp_fields']['context'],
									'security' => $data['snmp_fields']['security'],
									'security_level' => $data['snmp_fields']['security_level'],
									'authentication_protocol' => $data['snmp_fields']['authentication_protocol'],
									'authentication_passphrase' => $data['snmp_fields']['authentication_passphrase'],
									'privacy_protocol' => $data['snmp_fields']['privacy_protocol'],
									'privacy_passphrase' => $data['snmp_fields']['privacy_passphrase'],
								];
							}
							else {
								$fields_value = [
									'address' => '',
									'port' => '',
									'proxy' => '(no proxy)',
									'version' => 'SNMPv2',
									'context' => '',
									'security' => '',
									'security_level' => 'noAuthNoPriv',
									'authentication_protocol' => 'MD5',
									'authentication_passphrase' => '',
									'privacy_protocol' => 'DES',
									'privacy_passphrase' => '',
								];
							}

							$fields_state = [
								'address' => true,
								'port' => true,
								'proxy' => true,
								'version' => true,
								'context' => true,
								'security' => true,
								'security_level' => true,
								'authentication_protocol' => true,
								'authentication_passphrase' => true,
								'privacy_protocol' => true,
								'privacy_passphrase' => true
							];
						}
						else {
							if ($is_host) {
								$fields_value = [
									'address' => $host_interface[0],
									'port' => $host_interface[1],
									'proxy' => $proxy,
									'version' => CTestArrayHelper::get($data, 'snmp_fields.version', 'SNMPv2'),
									'comunity' => CTestArrayHelper::get($data, 'snmp_fields.comunity', 'public')
								];
							}
							else {
								$fields_value = [
									'address' => '',
									'port' => '',
									'proxy' => '(no proxy)',
									'version' => 'SNMPv2',
									'comunity' => ''
								];
							}

							$fields_state = [
								'address' => true,
								'port' => true,
								'proxy' => true,
								'version' => true,
								'comunity' => true
							];
						}
						break;

					case 'SSH agent':
					case 'TELNET agent':
					case 'Simple check':
						$fields_value = [
							'address' => $is_host ? $host_interface[0] : '',
							'port' => '',
							'proxy' => $is_host ? $proxy : '(no proxy)'
						];
						$fields_state = ['address' => true, 'port' => false, 'proxy' => true];
						break;

					case 'Zabbix internal':
					case 'External check':
					case 'Database monitor':
					case 'HTTP agent':
					case 'JMX agent':
						$fields_value = [
							'address' => '',
							'port' => '',
							'proxy' => $is_host ? $proxy : '(no proxy)'
						];
						$fields_state = ['address' => false, 'port' => false, 'proxy' => true];
						break;

					case 'Zabbix aggregate':
					case 'Calculated':
						$fields_value = ['address' => '', 'port' => '', 'proxy' => '(no proxy)'];
						$fields_state = ['address' => false, 'port' => false, 'proxy' => false];
						break;
				}

				foreach ($fields_value as $field => $value) {
					$this->assertEquals($elements[$field]->getValue(), $value);
				}
				foreach ($fields_state as $field => $state) {
					$this->assertTrue($elements[$field]->isEnabled($state));
				}

				// Check value fields.
				$this->checkValueFields($data);

				// Change interface fields in testing form.
				if (CTestArrayHelper::get($data, 'interface')) {
					$elements['address']->fill($data['interface']['address']);
					$elements['port']->fill($data['interface']['port']);
				}

				// Click Get value button.
				$button = $test_form->query('button:Get value')->one();
				$button->click();
				$this->checkServerMessage(['Connection to Zabbix server "localhost" refused. Possible reasons:']);

				// Click Test button in test form.
				$dialog->query('button:Get value and test')->one()->waitUntilVisible()->click();
				$this->checkServerMessage(['Connection to Zabbix server "localhost" refused. Possible reasons:']);

				// Check empty interface fields.
				if (in_array($data['fields']['Type'], ['Zabbix agent', 'SNMP agent', 'IPMI agent'])) {
					$elements['address']->clear();
					$elements['port']->clear();
					$button->click();
					$this->checkServerMessage(['Incorrect value for field "Host address": cannot be empty.',
						'Incorrect value for field "Port": cannot be empty.']);
				}
				if ($data['fields']['Type'] === 'Simple check') {
					$elements['address']->clear();
					$button->click();
					$this->checkServerMessage(['Incorrect value for field "Host address": cannot be empty.']);
				}

				$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
				$value_test_button = $overlay->query('button:Get value and test')->waitUntilVisible()->one();
				// Uncheck "Get value from host" checkbox.
				if (CTestArrayHelper::get($data, 'host_value', true) === false) {
					$get_host_value->uncheck();
					// Check that interface and proxy fields disappeared.
					foreach (['address', 'port', 'proxy'] as $field) {
						$elements[$field]->waitUntilNotVisible();
					}
					$button->waitUntilNotVisible();
					// Check that Test button changed its name.
					$this->assertFalse($overlay->query('button:Get value and test')->one(false)->isValid());
					$overlay->query('button:Test')->waitUntilVisible()->one();

					/*
					 * Check that value fields still present after "Get value
					 * from host" checkbox is unset.
					 */
					$this->checkValueFields($data);
				}

				// Compare data macros with macros from test table.
				$macros = [
					'expected' => CTestArrayHelper::get($data, 'macros'),
					'actual' => []
				];

				// Global macros values for items on template are empty.
				if (!$is_host && $data['fields']['Type'] === 'JMX agent') {
					foreach ($macros['expected'] as &$macro) {
						if (in_array(substr($macro['macro'], 1, 1), ['$', '#'])) {
							continue;
						}
						$macro['value'] = '';
					}
					unset($macro);
				}

				if ($macros['expected']){
					foreach ($test_form->query('class:textarea-flexible-container')->asTable()->one()->getRows() as $row) {
						$columns = $row->getColumns()->asArray();
						/*
						 * Macro columns are represented in following way:
						 * (0)macro (1)=> (2)value
						 */
						$macros['actual'][] = [
							'macro' => $columns[0]->getText(),
							'value' => $columns[2]->getText()
						];
					}
					foreach ($macros as &$array) {
						usort($array, function ($a, $b) {
							return strcmp($a['macro'], $b['macro']);
						});
					}
					unset ($array);

					$this->assertEquals($macros['expected'], $macros['actual']);
				}

				// Compare preprocessing from data with steps from test table.
				if (CTestArrayHelper::get($data, 'preprocessing')) {
					$preprocessing_table = $test_form->query('id:preprocessing-steps')->asTable()->one();

					foreach ($data['preprocessing'] as $i => $step) {
						$this->assertEquals(($i+1).': '.$step['type'],
							$preprocessing_table->getRow($i)->getText());
					}
				}
				break;
			case TEST_BAD:
				$message = $dialog->query('tag:output')->waitUntilPresent()
					->asMessage()->one();
				$this->assertTrue($message->isBad());
				$this->assertTrue($message->hasLine($data['error']));
				$dialog->close();
				break;
		}
	}

	/**
	 * Function for checking presence of fields and their editability
	 * depending on specific preprocessing steps.
	 *
	 * @param array $data data provider
	 */
	private function checkValueFields($data) {
		$test_form = $this->query('id:preprocessing-test-form')
			->waitUntilPresent()->one()->waitUntilReady();
		$get_host_value = $test_form->query('id:get_value')->asCheckbox()->one();

		$checked = $get_host_value->isChecked();
		$prev_enabled = false;

		if (!$checked) {
			/*
			 * If item has at least one of following preprocessing steps,
			 * previous value and time field should become editable.
			 */
			if (CTestArrayHelper::get($data, 'preprocessing')){
				$prev_enabled = false;
				foreach ($data['preprocessing'] as $step) {
					if (in_array($step['type'], ['Discard unchanged with heartbeat',
						'Simple change', 'Change per second', 'Discard unchanged'
					])) {
						$prev_enabled = true;
						break;
					}
				}
			}
		}

		$this->assertTrue($test_form->query('id:value')->asMultiline()
				->one()->isEnabled(!$checked));
		$this->assertTrue($test_form->query('id:prev_value')->asMultiline()
				->one()->isEnabled($checked && $prev_enabled));
		$this->assertTrue($test_form->query('id:prev_time')
				->one()->isEnabled($prev_enabled));

		$this->assertFalse($test_form->query('id:time')->one()->isEnabled());
		$this->assertTrue($test_form->query('id:eol')->one()->isEnabled());
	}

	private function checkServerMessage($message) {
		$test_form = $this->query('id:preprocessing-test-form')->asForm()->one();
		$message = $test_form->getOverlayMessage();
		$this->assertTrue($message->isBad());
		foreach ($message as $line) {
			$this->assertTrue($message->hasLine($line));
		}
		$message->close();
	}

	private function checkTestButtonInPreprocessing($item_type, $enabled = true, $i = 0) {
		$item_form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$test_button = $this->query('id:test_item')->waitUntilVisible()->one();

		$this->assertTrue($test_button->isEnabled($enabled));
		$item_form->selectTab('Preprocessing');
		$this->assertTrue($test_button->isEnabled($enabled));
		$this->query('id:param_add')->one()->click();
		$this->assertTrue($test_button->isEnabled($enabled));
		$this->query('name:preprocessing['.$i.'][remove]')->one()->click();
		$item_form->selectTab($item_type);
	}

	private function saveFormAndCheckMessage($message_text) {
		$item_form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$item_form->submit();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals($message_text, $message->getTitle());
	}
}
