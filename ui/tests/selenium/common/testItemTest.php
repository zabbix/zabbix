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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CPreprocessingBehavior.php';

/**
 * Base class for "Test item" function tests.
 */
class testItemTest extends CWebTest {

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

	const HOST_ID = 99136;		// 'Test item host'  monitored by 'Active proxy 1'.
	const TEMPLATE_ID = 99137;	// 'Test Item Template'

	/**
	 * Test item button state data for item, item prototype and LLD.
	 */
	public function getCommonTestButtonStateData() {
		return [
				['Type' => 'Zabbix agent'],
				['Type' => 'Zabbix agent (active)'],
				['Type' => 'Simple check'],
				['Type' => 'SNMP agent','SNMP OID' => '[IF-MIB::]ifInOctets.1'],
				['Type' => 'Zabbix internal'],
				['Type' => 'Zabbix trapper'],
				['Type' => 'External check'],
				['Type' => 'Database monitor', 'SQL query' => 'query'],
				['Type' => 'HTTP agent', 'URL' => 'https://www.zabbix.com'],
				['Type' => 'IPMI agent', 'IPMI sensor' => 'Sensor'],
				['Type' => 'SSH agent', 'Key' => 'ssh.run[Description,127.0.0.1,50,[{#KEY}]]', 'User name' => 'Name', 'Executed script' => 'Script'],
				['Type' => 'TELNET agent', 'Key' => 'telnet[{#KEY}]'],
				['Type' => 'JMX agent', 'Key' => 'jmx[{#KEY}]', 'JMX endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi', 'User name' => ''],
				['Type' => 'Dependent item', 'Key' => 'dependent[{#KEY}]', 'Master item' => 'Master item'],
				['Type' => 'Script', 'Script' => 'return 1;'],
				['Type' => 'Browser']
		];
	}

	/*
	 * Test item button state data for item and item prototype.
	 */
	public function getItemTestButtonStateData() {
		return array_merge($this->getCommonTestButtonStateData(), [
				['Type' => 'SNMP trap', 'Key' => 'snmptrap.fallback[{#KEY}]'],
				['Type' => 'Calculated', 'Formula' => '"formula"']
		]);
	}

	/**
	 * Check test item button state depending on item type.
	 *
	 * @param array		$data			data provider
	 * @param string	$item_name		item given name
	 * @param string	$item_type		type of an item: item, prototype or lld rule
	 * @param string	$success_text	text part of a success message
	 * @param boolean	$check_now		possibility of executing item instantly
	 * @param boolean	$is_host		true if host, false if template
	 * @param string	$id				id of a host, template or LLD rule
	 * @param string	$items			pointer to form in URL
	 */
	public function checkTestButtonState($data, $item_name, $item_type, $success_text, $check_now, $is_host, $id, $items = null) {
		$context = $is_host ? 'host' : 'template';

		if ($item_type === 'Discovery rule') {
			$create_link = 'host_discovery.php?form=create&hostid='.$id.'&context='.$context;
			$saved_link = $items.'.php?form=update&context=host&hostid='.$id.'&itemid=';
		}
		else {
			$create_link = ($items === null)
				? 'zabbix.php?action=item.prototype.list&context='.$context.'&parent_discoveryid='.$id
				: 'zabbix.php?action=item.list&context='.$context.'&filter_set=1&filter_hostids[0]='.$id;
		}

		$this->page->login()->open($create_link);

		if ($item_type !== 'Discovery rule') {
			$this->query('button:'.(($items === null) ? 'Create item prototype' : 'Create item'))->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$item_form = $dialog->asForm();
		}
		else {
			$item_form = $this->query('name:itemForm')->asForm()->waitUntilReady()->one();
		}

		// Create item.
		$item_form->fill([
			'Name' => $item_name,
			'Type' => 'Zabbix agent',
			'Key' => 'key[{#KEY}]'
		]);
		$this->saveFormAndCheckMessage($item_type.$success_text);
		$itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.zbx_dbstr($item_name));

		// Open created item and change type.
		foreach ($data as $update) {
			if ($item_type === 'Discovery rule') {
				$this->page->open($saved_link.$itemid);
			}
			else {
				$this->page->open($create_link);
				$this->query('link:'.$item_name)->one()->click();
				COverlayDialogElement::find()->one()->waitUntilReady();
			}
			$item_form->invalidate();
			$type = $item_form->getField('Type')->getValue();

			for ($i = 0; $i < 2; $i++) {
				if ($type === 'IPMI agent' && $is_host === false) {
					$enabled = false;
				}
				else {
					$enabled = (!in_array($type, ['Zabbix agent (active)', 'SNMP trap', 'Zabbix trapper', 'Dependent item']));
				}

				$this->checkTestButtonInPreprocessing($item_type, $enabled, $i);

				// Change item type.
				if ($i === 0) {
					$item_form->fill($update);
					// TODO: workaround for DEV-3855
					if ($item_type === 'Item prototype' && array_key_exists('Master item', $update)) {
						sleep(2);
						$item_form->getFieldContainer('Master item')->asMultiselect()->select($update['Master item']);
					}

					$type = $update['Type'];
				}
			}

			$this->saveFormAndCheckMessage($item_type.' updated', $item_type == 'Discovery rule' ? true : false);

			/**
			 * By design, when changing item type, the "Execute now" doesn't change its state, as these changes have not
			 * been written to the DB yet. To check the "Execute now" button state the item needs to be saved and
			 * its form should be opened again.
			 */
			if ($check_now) {
				if ($type === 'Dependent item') {
					$enabled = true;
				}

				$this->query('link', $item_name)->waitUntilClickable()->one()->click();

				if ($item_type === 'Discovery rule') {
					$button = $this->query('button:Execute now')->waitUntilVisible()->one();
				}
				else {
					$button = COverlayDialogElement::find()->one()->waitUntilReady()->query('button:Execute now')->one();
				}

				$this->assertTrue($button->isEnabled($enabled));

				if ($item_type !== 'Discovery rule') {
					COverlayDialogElement::find()->one()->close();
				}
			}
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
					'host_interface' => '127.0.0.2:161',
					'snmp_fields' => [
						'version' => 'SNMPv2',
						'community' => 'public'
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
					'host_interface' => '127.0.0.5:161',
					'snmp_fields' => [
						'version' => 'SNMPv1',
						'community' => 'public'
					],
					'interface_text_part' => 'SNMPv1, Community: {$SNMP_COMMUNITY}'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'SNMP agent',
						'Key' => 'snmp.v3'
					],
					'host_interface' => '127.0.0.6:161',
					'snmp_fields' => [
						'version' => 'SNMPv3',
						'context' => 'test_context',
						'security' => 'test_security_name',
						'security_level' => 'authPriv',
						'authentication_protocol' => 'SHA1',
						'authentication_passphrase' => '{$TEST}',
						'privacy_protocol' => 'AES128',
						'privacy_passphrase' => 'test_privpassphrase'
					],
					'interface_text_part' => 'SNMPv3, Context name: test_context, (priv: AES128, auth: SHA1)'
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
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Script',
						'Key' => 'test.script',
						'Script' => 'return 1;'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Browser',
						'Key' => 'test.browser'
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
					'error' => 'Invalid parameter "/1/params/1": cannot be empty.'
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
					'error' => 'Invalid parameter "/1/params/2": cannot be empty.'
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
					'error' => 'Invalid parameter "/1/params/1": cannot be empty.'
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
					'error' => 'Invalid parameter "/1/error_handler_params": cannot be empty.'
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
					'interface' => ['address' => '  127.0.0.1   ', 'port' => '   10050    ']
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
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Calculated',
						'Key' => 'calculated0'
					],
					'test_error' => 'Incorrect value for field "Formula": incorrect expression starting from "".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Calculated',
						'Key' => 'calculated1',
						'Formula' => '((),9'
					],
					'test_error' => 'Incorrect value for field "Formula": incorrect expression starting from "),9".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Calculated',
						'Key' => 'calculated2',
						'Formula' => '{{?{{?{{?'
					],
					'test_error' => 'Incorrect value for field "Formula": incorrect expression starting from "{{?{{?{{?".'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Calculated',
						'Key' => 'test.calculated',
						'Formula' => 'avg(/Zabbix Server/zabbix[wcache,values],10m)'
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
	 * @param array		$data			data provider
	 * @param boolean	$is_host		true if host, false if template
	 * @param string	$id				id of a host, template or LLD rule
	 * @param string	$items			pointer to form in URL
	 * @param boolean   $lld            true if lld, false if item or item prototype
	 */
	public function checkTestItem($data, $is_host, $id, $items = null, $lld = false) {
		$context = ($is_host === true) ? 'host' : 'template';
		$create_link = ($items === null)
			? 'zabbix.php?action=item.prototype.list&context='.$context.'&parent_discoveryid='.$id
			: 'zabbix.php?action=item.list&context='.$context.'&filter_set=1&filter_hostids[0]='.$id;

		if (!$is_host && $data['fields']['Type'] === 'IPMI agent') {
			return;
		}

		$this->page->login()->open($create_link);
		$this->query('button:'.(($items === null) ? 'Create item prototype' : 'Create item'))->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$item_form = $dialog->asForm();
		$item_form->fill($data['fields']);

		if ($is_host) {
			// If host fill interface.
			if (CTestArrayHelper::get($data, 'host_interface')) {
				/**
				 * The value of an SNMP interface option element contains not only the IP and port, but also the
				 * interface type and context name or community. In this case the address and details must be merged.
				 */
				$interface = $data['host_interface'].CTestArrayHelper::get($data, 'interface_text_part', '');

				$item_form->getField('Host interface')->fill($interface);
			}
			// Get ip and port separately.
			$host_interface = explode(':', $item_form->getField('Host interface')->getText(), 2);
		}

		if (CTestArrayHelper::get($data, 'preprocessing')){
			$item_form->selectTab('Preprocessing');
			$this->addPreprocessingSteps($data['preprocessing']);
		}

		// Open Test item dialog form.
		$dialog->getFooter()->query('button:Test')->one()->click();
		$overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertEquals('Test item', $overlay->getTitle());
				$test_form = $this->query('id:preprocessing-test-form')->asForm()->waitUntilReady()->one();

				// Check "Get value from host" checkbox.
				$get_host_value = $test_form->query('id:get_value')->asCheckbox()->one();
				$this->assertTrue($get_host_value->isEnabled());
				$this->assertTrue($get_host_value->isChecked());

				if ($lld === false) {
					$not_supported = $test_form->query('id:not_supported')->asCheckbox()->one();
					$this->assertFalse($not_supported->isEnabled());
				}
				else {
					$not_supported = null;
				}

				if (CTestArrayHelper::get($data, 'snmp_fields.version') === 'SNMPv3') {
					$elements = [
						'address' => 'id:interface_address',
						'port' => 'id:interface_port',
						'test_with' => 'id:test_with',
						'proxy' => 'xpath:.//div[@id="proxyid"]/..',
						'version' => 'id:interface_details_version',
						'context' => 'id:interface_details_contextname',
						'security' => 'id:interface_details_securityname',
						'security_level' => 'id:interface_details_securitylevel',
						'authentication_protocol' => 'name:interface[details][authprotocol]',
						'authentication_passphrase' => 'id:interface_details_authpassphrase',
						'privacy_protocol' => 'name:interface[details][privprotocol]',
						'privacy_passphrase' => 'id:interface_details_privpassphrase'
					];
				}
				elseif (in_array(CTestArrayHelper::get($data, 'snmp_fields.version'), ['SNMPv1', 'SNMPv2'])) {
					$elements = [
						'address' => 'id:interface_address',
						'port' => 'id:interface_port',
						'test_with' => 'id:test_with',
						'proxy' => 'xpath:.//div[@id="proxyid"]/..',
						'version' => 'id:interface_details_version',
						'community' => 'id:interface_details_community'
					];
				}
				else {
					$elements = [
						'address' => 'id:interface_address',
						'port' => 'id:interface_port',
						'test_with' => 'id:test_with',
						'proxy' => 'xpath:.//div[@id="proxyid"]/..'
					];
				}

				foreach ($elements as $name => $selector) {
					$elements[$name] = $test_form->query($selector)->one()->detect();
				}

				$proxy = CDBHelper::getValue("SELECT name FROM proxy WHERE proxyid IN ".
						"(SELECT proxyid FROM hosts WHERE host = 'Test item host')");

				// Check test item form fields depending on item type.
				switch ($data['fields']['Type']) {
					case 'Zabbix agent':
					case 'IPMI agent':
						if ($is_host) {
							$fields_value = [
								'address' => $host_interface[0],
								'port' => $host_interface[1],
								'test_with' => 'Proxy',
								'proxy' => [$proxy]
							];
						}
						else {
							$fields_value = [
								'address' => '',
								'port' => '',
								'test_with' => 'Server'
							];
						}
						$fields_state = ['address' => true, 'port' => true, 'test_with' => true, 'proxy' => true];
						break;

					case 'SNMP agent':
						if (CTestArrayHelper::get($data, 'snmp_fields.version') === 'SNMPv3') {
							$fields_state = [
								'address' => true,
								'port' => true,
								'test_with' => true,
								'version' => true,
								'context' => true,
								'security' => true,
								'security_level' => true,
								'authentication_protocol' => true,
								'authentication_passphrase' => true,
								'privacy_protocol' => true,
								'privacy_passphrase' => true
							];

							if ($is_host) {
								$fields_value = [
									'address' => $host_interface[0],
									'port' => $host_interface[1],
									'test_with' => 'Proxy',
									'proxy' => [$proxy],
									'version' => 'SNMPv3',
									'context' => $data['snmp_fields']['context'],
									'security' => $data['snmp_fields']['security'],
									'security_level' => $data['snmp_fields']['security_level'],
									'authentication_protocol' => $data['snmp_fields']['authentication_protocol'],
									'authentication_passphrase' => $data['snmp_fields']['authentication_passphrase'],
									'privacy_protocol' => $data['snmp_fields']['privacy_protocol'],
									'privacy_passphrase' => $data['snmp_fields']['privacy_passphrase']
								];

								$fields_state['proxy'] = true;
							}
							else {
								$fields_value = [
									'address' => '',
									'port' => '',
									'test_with' => 'Server',
									'version' => 'SNMPv2',
									'context' => '',
									'security' => '',
									'security_level' => 'noAuthNoPriv',
									'authentication_protocol' => 'MD5',
									'authentication_passphrase' => '',
									'privacy_protocol' => 'DES',
									'privacy_passphrase' => ''
								];
							}
						}
						else {
							$fields_state = [
								'address' => true,
								'port' => true,
								'test_with' => true,
								'version' => true,
								'community' => true
							];

							if ($is_host) {
								$fields_value = [
									'address' => $host_interface[0],
									'port' => $host_interface[1],
									'test_with' => 'Proxy',
									'proxy' => [$proxy],
									'version' => CTestArrayHelper::get($data, 'snmp_fields.version', 'SNMPv2'),
									'community' => CTestArrayHelper::get($data, 'snmp_fields.community', 'public')
								];

								$fields_state['proxy'] = true;
							}
							else {
								$fields_value = [
									'address' => '',
									'port' => '',
									'test_with' => 'Server',
									'version' => 'SNMPv2',
									'community' => ''
								];
							}
						}
						break;

					case 'SSH agent':
					case 'TELNET agent':
					case 'Simple check':
						$fields_state = [
							'address' => true,
							'port' => false,
							'test_with' => true
						];

						$fields_value = [
							'address' => $is_host ? $host_interface[0] : '',
							'port' => '',
							'test_with' => ($is_host) ? 'Proxy' : 'Server'
						];

						if ($is_host) {
							$fields_value['proxy'] = [$proxy];
							$fields_state['proxy'] = true;
						}

						break;

					case 'Zabbix internal':
					case 'External check':
					case 'Database monitor':
					case 'HTTP agent':
					case 'JMX agent':
					case 'Script':
					case 'Browser':
						$fields_state = [
							'address' => false,
							'port' => false,
							'test_with' => true
						];

						$fields_value = [
							'address' => '',
							'port' => '',
							'test_with' => ($is_host) ? 'Proxy' : 'Server'
						];

						if ($is_host) {
							$fields_value['proxy'] = [$proxy];
							$fields_state['proxy'] = true;
						}

						break;

					case 'Calculated':
						$fields_state = [
							'address' => false,
							'port' => false,
							'test_with' => false
						];

						$fields_value = [
							'address' => '',
							'port' => '',
							'test_with' => 'Server'
						];

						if ($is_host) {
							$fields_value['proxy'] = '';
							$fields_state['proxy'] = true;
						}

						break;
				}

				foreach ($fields_value as $field => $value) {
					$this->assertEquals($value, $elements[$field]->getValue());
				}
				foreach ($fields_state as $field => $state) {
					$this->assertTrue($elements[$field]->isEnabled($state));

					// Check that proxy multiselect is not visible if "Test with" is set to "Server".
					if ($field === 'test_with' && $fields_value[$field] === 'Server') {
						$this->assertFalse($test_form->query('id:proxyid')->one()->isDisplayed());
					}
				}

				// Check value fields.
				$this->checkValueFields($data, $not_supported, $lld);

				// Change interface fields in testing form.
				if (CTestArrayHelper::get($data, 'interface')) {
					$elements['address']->fill($data['interface']['address']);
					$elements['port']->fill($data['interface']['port']);
				}

				if ($is_host || array_key_exists('interface', $data) || in_array($data['fields']['Type'],
						['Zabbix internal', 'External check', 'Database monitor', 'HTTP agent', 'JMX agent',
						'Calculated', 'Script', 'Browser'])) {
					$details = 'Connection to Zabbix server "localhost:10051" refused. Possible reasons:';
				}
				else {
					$details = ($data['fields']['Type'] === 'SNMP agent')
						? 'Incorrect value for field "SNMP community": cannot be empty.'
						: 'Incorrect value for field "Host address": cannot be empty.';
				}

				// Click Get value button.
				$button = $test_form->query('button:Get value')->one();
				$button->click();
				$this->assertMessage(TEST_BAD, null, $details);
				$test_form->getOverlayMessage()->close();

				// Click Test button in test form.
				$overlay->query('button:Get value and test')->one()->waitUntilVisible()->click();
				$this->assertMessage(TEST_BAD, null, $details);
				$test_form->getOverlayMessage()->close();

				// Check empty interface fields.
				if (in_array($data['fields']['Type'], ['Zabbix agent', 'SNMP agent', 'IPMI agent', 'Simple check'])) {
					if ($data['fields']['Type'] !== 'Simple check') {
						$elements['port']->clear();
						$button->click();

						if (!$is_host && !array_key_exists('interface', $data)) {
							$details = ($data['fields']['Type'] === 'SNMP agent')
								? 'Incorrect value for field "SNMP community": cannot be empty.'
								: 'Incorrect value for field "Host address": cannot be empty.';
						}
						else {
							$details = 'Incorrect value for field "Port": cannot be empty.';
						}

						$this->assertMessage(TEST_BAD, null, $details);
						$test_form->getOverlayMessage()->close();
					}

					$elements['address']->clear();
					$button->click();
					$details = (!$is_host && $data['fields']['Type'] === 'SNMP agent')
						? 'Incorrect value for field "SNMP community": cannot be empty.'
						: 'Incorrect value for field "Host address": cannot be empty.';
					$this->assertMessage(TEST_BAD, null, $details);
					$test_form->getOverlayMessage()->close();

					// Check SNMP empty fields for Template.
					if (!$is_host && (CTestArrayHelper::get($data, 'snmp_fields.community'))) {
						$test_form->fill(['id:interface_details_community' => $data['snmp_fields']['community']]);
						$button->click();
						$this->assertMessage(TEST_BAD, null, 'Incorrect value for field "Host address": cannot be empty.');
						$test_form->getOverlayMessage()->close();

						$elements['address']->fill('127.0.0.1');
						$button->click();
						$this->assertMessage(TEST_BAD, null, 'Incorrect value for field "Port": cannot be empty.');
						$test_form->getOverlayMessage()->close();
					}
				}

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

					if ($lld === false) {
						$this->assertTrue($not_supported->isEnabled());
						$this->assertFalse($not_supported->isChecked());
					}

					// Check that value fields still present after "Get value from host" checkbox is unset.
					$this->checkValueFields($data, $not_supported, $lld);
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

				if ($macros['expected']) {
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
						$this->assertEquals(($i+1).': '.$step['type'], $preprocessing_table->getRow($i)->getText());
					}
				}
				break;
			case TEST_BAD:
				if (CTestArrayHelper::get($data, 'test_error')) {
					$overlay->query('button:Get value and test')->one()->click();
					$data['error'] = $data['test_error'];
				}

				$this->assertMessage(TEST_BAD, null, $data['error']);
				break;
		}

		$overlay->close();
		$dialog->close();
	}

	/**
	 * Function for checking presence of fields and their editability, depending on specific preprocessing steps.
	 *
	 * @param array				$data data		provider
	 * @param CCheckboxElement	$not_supported	"Not supported" checkbox
	 * @param boolean			$lld			true if lld, false if item or prototype
	 */
	private function checkValueFields($data, $not_supported, $lld = false) {
		$test_form = $this->query('id:preprocessing-test-form')->waitUntilReady()->one();
		$get_host_value = $test_form->query('id:get_value')->asCheckbox()->one();
		$checked = $get_host_value->isChecked();
		$prev_enabled = false;
		$value = $test_form->query('id:value')->asMultiline()->one();

		if (!$checked) {
			/*
			 * If item has at least one of following preprocessing steps,
			 * previous value and time field should become editable.
			 */
			if (CTestArrayHelper::get($data, 'preprocessing')){
				$prev_enabled = false;
				foreach ($data['preprocessing'] as $step) {
					if (in_array($step['type'], ['Discard unchanged with heartbeat',
							'Simple change', 'Change per second', 'Discard unchanged'])
					) {
						$prev_enabled = true;
						break;
					}
				}
			}
			if ($lld === false){
				$not_supported->check();
				$this->assertFalse($value->isEnabled());
			}
		}
		else {
			$this->assertTrue($value->isEnabled(!$checked));
		}

		$this->assertTrue($test_form->query('id:prev_value')->asMultiline()->one()->isEnabled($checked && $prev_enabled));
		$this->assertTrue($test_form->query('id:prev_time')->one()->isEnabled($prev_enabled));
		$this->assertFalse($test_form->query('id:time')->one()->isEnabled());
		$this->assertTrue($test_form->query('id:eol')->one()->isEnabled());
	}

	/**
	 * Function for checking if Test button is enabled  or disabled.
	 *
	 * @param string	$item_type	type of an item: item, prototype or lld rule
	 * @param boolean	$enabled	status of an element, true is enabled, false if disabled
	 * @param int		$i			index number of preprocessing step
	 */
	private function checkTestButtonInPreprocessing($item_type, $enabled = true, $i = 0) {

		if ($item_type == 'Discovery rule') {
			$item_form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
			$test_button = $this->query('id:test_item')->waitUntilVisible()->one();
		}
		else {
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$item_form = $dialog->asForm();
			$test_button = $dialog->getFooter()->query('button:Test')->one();
		}

		$this->assertTrue($test_button->isEnabled($enabled));
		$item_form->selectTab('Preprocessing');
		$this->assertTrue($test_button->isEnabled($enabled));
		$this->query('id:param_add')->one()->click();
		$this->assertTrue($test_button->isEnabled($enabled));
		$this->query('name:preprocessing['.$i.'][remove]')->one()->click();
		$item_form->selectTab($item_type);
	}

	private function saveFormAndCheckMessage($message, $lld = 'false') {

		$item_form = $lld
			? $this->query('name:itemForm')->waitUntilPresent()->asForm()->one()
			: COverlayDialogElement::find()->one()->waitUntilReady()->asForm();

		$item_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $message);
	}
}
