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

/**
 * Common class for LLD form testing.
 */
class testLowLevelDiscovery extends CWebTest {
	const SQL = 'SELECT * FROM items WHERE flags=1 ORDER BY itemid';
	const SIMPLE_UPDATE_CLONE_LLD = 'LLD for simple update or clone scenario';

	protected static $context = 'host';
	protected static $groupid;
	protected static $templateid;
	protected static $hostid;
	protected static $update_lld;

	/**
	 * Attach MessageBehavior and PreprocessingBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CPreprocessingBehavior::class
		];
	}

	/**
	 * Test for LLD Form initial layout check without changing the LLD type.
	 */
	protected function checkInitialLayout() {
		$url = (static::$context === 'template')
			? static::$templateid.'&context=template'
			: static::$empty_hostid.'&context=host';

		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$url);
		$this->query('button:Create discovery rule')->waitUntilClickable()->one()->click();
		$form = $this->query('id:host-discovery-form')->asForm()->waitUntilVisible()->one();
		$this->page->assertHeader('Discovery rules');
		$this->page->assertTitle('Configuration of discovery rules');
		$this->assertEquals(['Discovery rule', 'Preprocessing', 'LLD macros', 'Filters', 'Overrides'], $form->getTabs());

		// Check form footer buttons clickability.
		foreach (['id:add', 'button:Test', 'button:Cancel'] as $button) {
			$this->assertTrue($form->query($button)->one()->isClickable());
		}

		// Check the whole form required labels.
		$required_labels = ['Name', 'Key', 'URL', 'Script', 'Script', 'Master item', 'Host interface', 'SNMP OID', 'JMX endpoint',
				'Public key file', 'Private key file', 'Executed script', 'SQL query', 'Update interval', 'Timeout',
				'Delete lost resources', 'Disable lost resources'
		];

		if (static::$context === 'template') {
			$required_labels = array_values(array_diff($required_labels, ['Host interface']));
		}

		$this->assertEquals($required_labels, array_values($form->getLabels(CElementFilter::CLASSES_PRESENT,
				'form-label-asterisk')->asText())
		);

		// Check default fields' values.
		$fields = [
			// Discovery rule.
			'Name' => ['maxlength' => 255],
			'Type' => ['value' => 'Zabbix agent', 'options' => ['Zabbix agent', 'Zabbix agent (active)', 'Simple check',
				'SNMP agent', 'Zabbix internal', 'Zabbix trapper', 'External check', 'Database monitor', 'HTTP agent',
				'IPMI agent', 'SSH agent', 'TELNET agent', 'JMX agent', 'Dependent item', 'Script', 'Browser']
			],
			'Key' => ['maxlength' => 2048],
			'URL' => ['maxlength' => 2048],
			'Query fields' => ['value' => [['name' => '', 'value' => '']]],
			'name:query_fields[0][name]' => ['maxlength' => 255, 'placeholder' => 'name'],
			'name:query_fields[0][value]' => ['maxlength' => 255, 'placeholder' => 'value'],
			'Parameters' => ['value' => [['name' => '', 'value' => '']]],
			'name:parameters[0][name]' => ['maxlength' => 255],
			'name:parameters[0][value]' => ['maxlength' => 2048],
			'xpath://div[@id="script"]/input[@type="text"]' => ['value' => '', 'placeholder' => 'script'],
			'Request type' => ['options' => ['GET', 'POST', 'PUT', 'HEAD'], 'value' => 'GET'],
			'Request body type' => ['labels' => ['Raw data', 'JSON data', 'XML data'], 'value' => 'Raw data'],
			'Request body' => ['value' => ''],
			'Headers' => ['value' => [['name' => '', 'value' => '']]],
			'name:headers[0][name]' => ['maxlength' => 255, 'placeholder' => 'name'],
			'name:headers[0][value]' => ['maxlength' => 2000, 'placeholder' => 'value'],
			'Required status codes' => ['value' => 200, 'maxlength' => 255],
			'Follow redirects' => ['value' => true],
			'Retrieve mode' => ['labels' => ['Body', 'Headers', 'Body and headers'], 'value' => 'Body'],
			'HTTP proxy' => ['placeholder' => '[protocol://][user[:password]@]proxy.example.com[:port]', 'maxlength' => 255],
			'HTTP authentication' => ['options' => ['None', 'Basic', 'NTLM', 'Kerberos', 'Digest'], 'value' => 'None'],
			'SSL verify peer' => ['value' => false],
			'SSL verify host' => ['value' => false],
			'SSL certificate file' => ['maxlength' => 255],
			'SSL key file' => ['maxlength' => 255],
			'SSL key password' => ['maxlength' => 64],
			'Master item' => ['value' => ''],
			'Host interface' => ['value' => 'None'],
			'id:snmp_oid' => ['placeholder' => 'walk[OID1,OID2,...]', 'maxlength' => 512],
			'id:ipmi_sensor' => ['maxlength' => 128],
			'Authentication method' => ['options' => ['Password', 'Public key'], 'value' => 'Password'],
			'id:jmx_endpoint' => ['value' => ZBX_DEFAULT_JMX_ENDPOINT, 'maxlength' => 255],
			'id:publickey' => ['maxlength' => 64],
			'id:privatekey' => ['maxlength' => 64],
			'id:username' => ['maxlength' => 255],
			'id:password' => ['maxlength' => 255],
			'Executed script' => ['value' => ''],
			'SQL query' => ['value' => ''],
			'Update interval' => ['value' => '1h', 'maxlength' => 255],
			'Custom intervals' => ['value' => [['Type' => 'Flexible', 'delay' => '', 'period' => '']]],
			'id:delay_flex_0_type' => ['labels' => ['Flexible', 'Scheduling'], 'value' => 'Flexible'],
			'id:delay_flex_0_delay' => ['placeholder' => '50s', 'maxlength' => 255],
			'id:delay_flex_0_period' => ['placeholder' => '1-7,00:00-24:00', 'maxlength' => 255],
			'id:timeout' => ['value' => '3s', 'maxlength' => 255],
			'id:inherited_timeout' => ['value' => '3s', 'maxlength' => 255],
			'id:custom_timeout' => ['labels' => ['Global', 'Override'], 'value' => 'Global'],
			'Delete lost resources' => ['value' => '7d', 'maxlength' => 255],
			'Disable lost resources' => ['value' => '1h', 'maxlength' => 255],
			'id:lifetime_type' => ['labels' => ['Never', 'Immediately', 'After'], 'value' => 'After'],
			'id:enabled_lifetime_type' => ['labels' => ['Never', 'Immediately', 'After'], 'value' => 'Immediately'],
			'Enable trapping' => ['value' => false],
			'id:trapper_hosts' => ['maxlength' => 255],
			'Description' => ['value' => '', 'maxlength' => 65535],
			'Enabled' => ['value' => true],

			// Preprocessing tab.
			'Preprocessing steps' => ['value' => NULL],

			// LLD macros tab.
			'LLD macros' => ['value' => [['lld_macro' => '', 'path' => '']]],
			'id:lld_macro_paths_0_lld_macro' => ['placeholder' => '{#MACRO}', 'maxlength' => 255],
			'id:lld_macro_paths_0_path' => ['placeholder' => '$.path.to.node', 'maxlength' => 255],

			// Filters tab.
			'Filters' => ['value' => [['macro' => '']]],
			'id:conditions_0_macro' => ['placeholder' => '{#MACRO}', 'maxlength' => 64],
			'name:conditions[0][operator]' => ['options' => ['matches', 'does not match', 'exists', 'does not exist'],
				'value' => 'matches'
			],
			'id:conditions_0_value' => ['placeholder' => 'regular expression', 'maxlength' => 255],

			// Overrides tab.
			'Overrides' => ['value' => []]
		];

		if (static::$context === 'template') {
			unset($fields['Host interface']);
		}

		$this->checkFieldsParameters($fields);

		// Check initial visible fields for each tab.
		$visible_fields = [
			'Preprocessing' => ['Preprocessing steps'],
			'LLD macros' => ['LLD macros'],
			'Filters' => ['Filters'],
			'Overrides' => ['Overrides'],
			'Discovery rule' => ['Name', 'Type', 'Key', 'Host interface', 'Update interval', 'Custom intervals',
					'Timeout', 'Delete lost resources', 'Disable lost resources', 'Description', 'Enabled'
			]
		];

		if (static::$context === 'template') {
			$visible_fields['Discovery rule'] = array_values(array_diff($visible_fields['Discovery rule'], ['Host interface']));
		}

		foreach ($visible_fields as $tab => $fields) {
			$form->selectTab($tab);
			$this->assertEquals($fields, array_values($form->getLabels()->filter(CElementFilter::VISIBLE)->asText()));

			// Check buttons default values and parameters in fields in every tab.
			switch ($tab) {
				case 'Preprocessing':
					// Other Preprocessing checks are performed in testFormPreprocessingLowLevelDiscovery.
					$this->assertTrue($form->getField('Preprocessing steps')->isVisible());
					break;

				case 'LLD macros':
					$macros_table = $form->query('id:lld_macro_paths')->asTable()->one();
					$this->assertTrue($macros_table->isVisible());
					$this->assertEquals(2, $macros_table->query('button', ['Add', 'Remove'])->all()
							->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
					);
					$this->assertEquals(['LLD macro', 'JSONPath', ''], $macros_table->getHeadersText());

					$macros_fields = [
						'id:lld_macro_paths_0_lld_macro' => ['value' => '', 'placeholder' => '{#MACRO}', 'maxlength' => 255],
						'id:lld_macro_paths_0_path' => ['value' => '', 'placeholder' => '$.path.to.node', 'maxlength' => 255]
					];
					$this->checkFieldsParameters($macros_fields);

					foreach (array_keys($macros_fields) as $key) {
						$this->assertTrue($form->getField($key)->isEnabled());
					}
					break;

				case 'Filters':
					$filters_container = $form->getFieldContainer('Filters');
					$this->assertTrue($form->query('id:conditions')->one()->isVisible());
					$this->assertEquals(2, $filters_container->query('button', ['Add', 'Remove'])->all()
							->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
					);
					$filter_table = $filters_container->query('id:conditions')->asTable()->one();
					$this->assertEquals(['Label', 'Macro', '', 'Regular expression', ''],
							$filter_table->getHeadersText()
					);
					$this->assertFalse($filters_container->query('id:evaltype')->exists());
					$filters_container->query('button:Add')->waitUntilClickable()->one()->click();
					$evaluation_type = $form->getField('id:evaltype');
					$form->checkValue(['id:evaltype' => 'And/Or']);
					$evaluation_type->fill('Custom expression');

					$filter_fields = [
						'id:evaltype' => ['value' => 'Custom expression', 'options' => ['And/Or', 'And', 'Or', 'Custom expression']],
						'id:formula' => ['value' => '', 'placeholder' => 'A or (B and C) ...', 'maxlength' => 255],
						'id:conditions_0_macro' => ['value' => '', 'placeholder' => '{#MACRO}', 'maxlength' => 64],
						'id:conditions_0_value' => ['value' => '', 'placeholder' => 'regular expression', 'maxlength' => 255],
						'name:conditions[0][operator]' => [
							'value' => 'matches',
							'options' => ['matches', 'does not match', 'exists', 'does not exist']
						]
					];
					$this->checkFieldsParameters($filter_fields);

					foreach (array_keys($filter_fields) as $key) {
						$this->assertTrue($form->getField($key)->isEnabled());
					}

					$calculation_types = [
						'And/Or' => 'A or B',
						'And' => 'A and B',
						'Or' => 'A or B'
					];
					foreach ($calculation_types as $type => $formula) {
						$evaluation_type->fill($type);
						$this->assertEquals($formula, $form->query('xpath:.//div[@class="cell expression-cell"]')
								->one()->getText()
						);
					}

					foreach (['A', 'B'] as $i => $letter) {
						$this->assertEquals($letter, $filter_table->getRow($i)->query('tag:span')->one()->getText());
					}
					break;

				case 'Overrides':
					$overrides_container = $form->getFieldContainer('Overrides');
					$this->assertTrue($overrides_container->query('button:Add')->one()->isClickable());
					$this->assertEquals(['', '', 'Name', 'Stop processing', 'Action'],
							$overrides_container->query('id:lld-overrides-table')->asTable()->one()->getHeadersText()
					);
					break;
			}
		}
	}

	public static function getTypeDependingData() {
		return [
			// #0.
			[
				[
					'type' => 'Zabbix agent',
					'fields' => ['Host interface', 'Update interval', 'Custom intervals', 'Timeout']
				]
			],
			// #1.
			[
				[
					'type' => 'Zabbix agent (active)',
					'fields' => ['Update interval', 'Custom intervals', 'Timeout']
				]
			],
			// #2.
			[
				[
					'type' => 'Simple check',
					'fields' => ['Host interface', 'User name', 'Password', 'Update interval', 'Custom intervals', 'Timeout']
				]
			],
			// #3.
			[
				[
					'type' => 'SNMP agent',
					'fields' => ['Host interface', 'SNMP OID', 'Update interval', 'Custom intervals']
				]
			],
			// #4.
			[
				[
					'type' => 'Zabbix internal',
					'fields' => ['Update interval', 'Custom intervals']
				]
			],
			// #5.
			[
				[
					'type' => 'Zabbix trapper',
					'fields' => ['Allowed hosts']
				]
			],
			// #6.
			[
				[
					'type' => 'External check',
					'fields' => ['Host interface', 'Update interval', 'Custom intervals', 'Timeout']
				]
			],
			// #7.
			[
				[
					'type' => 'Database monitor',
					'fields' => ['User name', 'Password', 'SQL query', 'Update interval', 'Custom intervals', 'Timeout']
				]
			],
			// #8.
			[
				[
					'type' => 'HTTP agent',
					'fields' => ['URL', 'Query fields', 'Request type', 'Request body type', 'Request body', 'Headers',
							'Required status codes', 'Follow redirects', 'Retrieve mode', 'HTTP proxy', 'HTTP authentication',
							'SSL verify peer', 'SSL verify host', 'SSL certificate file', 'SSL key file', 'SSL key password',
							'Host interface', 'Update interval', 'Custom intervals', 'Timeout', 'Enable trapping'
					]
				]
			],
			// #9.
			[
				[
					'type' => 'IPMI agent',
					'fields' => ['Host interface', 'IPMI sensor', 'Update interval', 'Custom intervals']
				]
			],
			// #10.
			[
				[
					'type' => 'SSH agent',
					'fields' => ['Host interface', 'Authentication method', 'User name', 'Password',
							'Executed script', 'Update interval', 'Custom intervals', 'Timeout'
					]
				]
			],
			// #11.
			[
				[
					'type' => 'TELNET agent',
					'fields' => ['Host interface', 'User name', 'Password', 'Executed script',
							'Update interval', 'Custom intervals', 'Timeout'
					]
				]
			],
			// #12.
			[
				[
					'type' => 'JMX agent',
					'fields' => ['Host interface', 'JMX endpoint', 'User name', 'Password', 'Update interval', 'Custom intervals']
				]
			],
			// #13.
			[
				[
					'type' => 'Dependent item',
					'fields' => ['Master item']
				]
			],
			// #14.
			[
				[
					'type' => 'Script',
					'fields' =>  ['Parameters', 'Script', 'Update interval', 'Custom intervals', 'Timeout']
				]
			],
			// #15.
			[
				[
					'type' => 'Browser',
					'fields' =>  ['Parameters', 'Script', 'Update interval', 'Custom intervals', 'Timeout']
				]
			]
		];
	}

	/**
	 * Test for LLD Form's layout check depending on LLD type.
	 *
	 * @param array $data        data provider
	 */
	protected function checkLayoutDependingOnType($data) {
		$url = (static::$context === 'template')
			? static::$templateid.'&context=template'
			: static::$empty_hostid.'&context=host';

		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$url);
		$this->query('button:Create discovery rule')->waitUntilClickable()->one()->click();
		$form = $this->query('id:host-discovery-form')->asForm()->waitUntilVisible()->one();

		// Check visible fields depending on LLD type.
		$permanent_fields = ['Name', 'Type', 'Key', 'Delete lost resources', 'Disable lost resources', 'Description', 'Enabled'];

		// Host interface field doesn't exist for Template.
		if (static::$context === 'template') {
			$data['fields'] = array_values(array_diff($data['fields'], ['Host interface']));
		}

		$hints = [
			'SNMP OID' => "Field requirements:".
					"\nwalk[OID1,OID2,...] - to retrieve a subtree".
					"\ndiscovery[{#MACRO1},OID1,{#MACRO2},OID2,...] - (legacy) to retrieve a subtree in JSON",
			'Delete lost resources' => 'The value should be greater than LLD rule update interval.',
			'Disable lost resources' => 'The value should be greater than LLD rule update interval.'
		];

		$form->fill(['Type' => $data['type']]);

		// Get expected visible fields.
		$form_fields = array_merge($permanent_fields, array_values($data['fields']));
		usort($form_fields, function ($a, $b) {
			return strcasecmp($a, $b);
		});

		// Get actual visible fields.
		$present_fields = $form->getLabels()->filter(CElementFilter::VISIBLE)->asText();
		usort($present_fields, function ($a, $b) {
			return strcasecmp($a, $b);
		});

		$this->assertEquals($form_fields, $present_fields);

		switch ($data['type']) {
			case 'SNMP agent':
				foreach (['walk[]' => true, '' => false] as $oid => $status) {
					$form->fill(['SNMP OID' => $oid]);
					$this->assertTrue($form->getField('Timeout')->isVisible($status));
				}

				// Check hints and texts.
				foreach ($hints as $label => $hint_text) {
					$form->getLabel($label)->query('xpath:./button[@data-hintbox]')->one()->click();
					$hint = $this->query('xpath://div[contains(@class, "overlay-dialogue")]')->waitUntilPresent()
							->all()->last();
					$this->assertEquals($hint_text, $hint->getText());
					$hint->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();
				}

			case 'Zabbix agent';
			case 'JMX agent':
			case 'IPMI agent':
				if (static::$context === 'host') {
					// Check red interface info message.
					$this->assertTrue($form->query('xpath:.//span[@class="red" and text()="No interface found"]')
							->one()->isVisible()
					);
				}
				break;

			case 'HTTP agent':
				// Check common buttons clickability.
				$buttons = [
					'Query fields' => ['Add', 'Remove'],
					'Headers' => ['Add', 'Remove'],
					'Custom intervals' => ['Add', 'Remove'],
					'URL' => 'Parse'
				];

				foreach ($buttons as $label => $query) {
					$this->assertTrue($form->getFieldContainer($label)->query('button', $query)->one()->isClickable());
				}

				// Request type and Retrieve mode fields dependency.
				$request_type_array = [
					'HEAD' => ['enabled' => false, 'visible' => true],
					'GET' => ['enabled' => true, 'visible' => true],
					'POST' => ['enabled' => true, 'visible' => true],
					'PUT' => ['enabled' => true, 'visible' => true]
				];

				foreach ($request_type_array as $request_type => $status) {
					$this->checkFieldsDependency($form, ['Request type' => $request_type], ['Retrieve mode' => $status]);
				}

				// HTTP authentication and User name/Password fields dependency.
				$http_fields = [
					'Basic' => ['enabled' => true, 'visible' => true],
					'NTLM' => ['enabled' => true, 'visible' => true],
					'Kerberos' => ['enabled' => true, 'visible' => true],
					'Digest' => ['enabled' => true, 'visible' => true],
					'None' => ['enabled' => true, 'visible' => false]
				];

				foreach ($http_fields as $http_auth => $status) {
					$this->checkFieldsDependency($form, ['HTTP authentication' => $http_auth],
							['User name' => $status, 'Password' => $status]
					);
				}

				// Custom intervals field's type and intervals dependency.
				$scheduling_fields = [
					'id:delay_flex_0_schedule' => ['enabled' => true, 'visible' => true],
					'id:delay_flex_0_delay' => ['enabled' => true, 'visible' => false],
					'id:delay_flex_0_period' => ['enabled' => true, 'visible' => false]
				];
				$this->checkFieldsDependency($form, ['id:delay_flex_0_type' => 'Scheduling'], $scheduling_fields);

				$flexible_fields = [
					'id:delay_flex_0_schedule' => ['enabled' => true, 'visible' => false],
					'id:delay_flex_0_delay' => ['enabled' => true, 'visible' => true],
					'id:delay_flex_0_period' => ['enabled' => true, 'visible' => true]
				];
				$this->checkFieldsDependency($form, ['id:delay_flex_0_type' => 'Flexible'], $flexible_fields);

				// Timeout fields' dependency.
				$timeout_array = [
					'Global' => [
						'id:inherited_timeout' => ['enabled' => false, 'visible' => true],
						'id:timeout' => ['enabled' => false, 'visible' => false]
					],
					'Override' => [
						'id:inherited_timeout' => ['enabled' => false, 'visible' => false],
						'id:timeout' => ['enabled' => true, 'visible' => true]
					]
				];
				foreach ($timeout_array as $timeout => $statuses) {
					foreach ($statuses as $id => $status) {
						$this->checkFieldsDependency($form, ['id:custom_timeout' => $timeout], [$id => $status]);
					}
				}

				// Check Timeouts link.
				$this->assertTrue($form->query('link:Timeouts')->one()->isClickable());

				$lifetime_array = [
					'Never' => ['enabled' => true, 'visible' => false],
					'Immediately' => ['enabled' => true, 'visible' => false],
					'After' => ['enabled' => true, 'visible' => true]
				];

				// Delete lost resources input dependency.
				foreach ($lifetime_array as $lifetime => $status) {
					$this->checkFieldsDependency($form, ['id:lifetime_type' => $lifetime], ['id:lifetime' => $status]);
				}

				// Disable lost resources input dependency.
				foreach ($lifetime_array as $lifetime => $status) {
					$this->checkFieldsDependency($form, ['id:enabled_lifetime_type' => $lifetime],
							['id:enabled_lifetime' => $status]
					);
				}

				$disabled_lifetime_array = [
					'Never' => ['enabled' => true, 'visible' => true],
					'Immediately' => ['enabled' => true, 'visible' => false],
					'After' => ['enabled' => true, 'visible' => true]
				];

				// Disable lost resources visibility dependency.
				foreach ($disabled_lifetime_array as $disabled_lifetime => $status) {
					$this->checkFieldsDependency($form, ['id:lifetime_type' => $disabled_lifetime],
							['id:enabled_lifetime_type' => $status]
					);
				}

				// Allowed hosts field's dependency.
				$allowed_hosts = $form->getField('Allowed hosts');
				foreach ([true, false] as $status) {
					$form->fill(['Enable trapping' => $status]);
					$this->assertTrue($allowed_hosts->isVisible($status));
					$this->assertTrue($allowed_hosts->isEnabled());
				}
				break;

			case 'SSH agent':
				// User/password and public/private keys fields dependency on "Authentication method".
				$key_fields = [
					'id:username' => ['enabled' => true, 'visible' => true],
					'id:password' => ['enabled' => true, 'visible' => true],
					'id:publickey' => ['enabled' => true, 'visible' => true],
					'id:privatekey' => ['enabled' => true, 'visible' => true]
				];
				$this->checkFieldsDependency($form, ['Authentication method' => 'Public key'], $key_fields);

				$password_fields = [
					'id:username' => ['enabled' => true, 'visible' => true],
					'id:password' => ['enabled' => true, 'visible' => true],
					'id:publickey' => ['enabled' => false, 'visible' => false],
					'id:privatekey' => ['enabled' => false, 'visible' => false]
				];
				$this->checkFieldsDependency($form, ['Authentication method' => 'Password'], $password_fields);
				break;

			case 'Script':
				$buttons = [
					'Parameters' => 'button:Add',
					'Parameters' => 'button:Remove',
					'Script' => 'xpath:.//button[@title="Click to view or edit"]' // Pencil icon button.
				];

				foreach ($buttons as $label => $query) {
					$this->assertTrue($form->getFieldContainer($label)->query($query)->one()->isClickable());
				}
				break;
		}
	}

	/**
	 * Test for checking LLD update form without any changes.
	 */
	protected function checkSimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::SQL);

		$url = (static::$context === 'template')
			? static::$templateid.'&context=template'
			: static::$hostid.'&context=host';

		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$url);
		$this->query('link', self::SIMPLE_UPDATE_CLONE_LLD)->waitUntilClickable()->one()->click();
		$this->query('button:Update')->waitUntilClickable()->one()->click();
		$this->assertMessage(TEST_GOOD, 'Discovery rule updated');
		$this->page->assertTitle('Configuration of discovery rules');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * These cases are for LLD form check as well as Macros and Filters tabs.
	 * Preprocessing and Overrides tabs are checked in separate test files:
	 * testFormLowLevelDiscoveryHostOverrides and testFormPreprocessingLowLevelDiscovery.
	 */
	public static function getLLDData() {
		return [
			// Main Discovery rule tab.
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'Type' => 'Zabbix agent',
						'Key' => '',
						'Update interval' => '',
						'id:custom_timeout' => 'Override',
						'id:timeout' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => [
						'Incorrect value for field "Name": cannot be empty.',
						'Incorrect value for field "Key": cannot be empty.',
						'Field "Update interval" is not correct: a time unit is expected',
						'Field "Timeout" is not correct: a time unit is expected'
					]
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Update interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'Update interval' => '1M'
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Field "Update interval" is not correct: a time unit is expected'
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Macro in key',
						'Type' => 'Zabbix agent',
						'Key' => '{$MACRO}'
					],
					'error_details' => 'Invalid parameter "/1/key_": incorrect syntax near "{$MACRO}".'
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '🙂 in key',
						'Type' => 'Zabbix agent',
						'Key' => '🙂🙃み け め 𒁥'
					],
					'error_details' => 'Invalid parameter "/1/key_": incorrect syntax near "🙂🙃み け め 𒁥".'
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Update interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'Update interval' => '{#LLD}'
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Field "Update interval" is not correct: a time unit is expected'
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Update intervals validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'Update interval' => 0
					],
					'error_details' => 'Invalid parameter "/1/delay": cannot be equal to zero without custom intervals.'
				]
			],
			// #6.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Update intervals validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'Update interval' => 86401
					],
					'error_details' => 'Invalid parameter "/1/delay": value must be one of 0-86400.'
				]
			],
			// #7.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => 1
					],
					'error_details' => 'Invalid interval "1".'
				]
			],
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => 'qd1-5h9-18'
					],
					'error_details' => 'Invalid interval "qd1-5h9-18".'
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => 'wd1-8h9-18'
					],
					'error_details' => 'Invalid interval "wd1-8h9-18".'
				]
			],
			// #10.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => 'wd1-5h9-25'
					],
					'error_details' => 'Invalid interval "wd1-5h9-25".'
				]
			],
			// #11.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Flexible',
						'id:delay_flex_0_delay' => '4w',
						'id:delay_flex_0_period' => '1-7,00:00-24:00'
					],
					'error_details' => 'Invalid parameter "/1/delay": update interval "4w" is longer than period "1-7,00:00-24:00".'
				]
			],
			// #12.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Flexible',
						'id:delay_flex_0_delay' => '60s',
						'id:delay_flex_0_period' => '1-8,00:00-24:00'
					],
					'error_details' => 'Invalid interval "1-8,00:00-24:00".'
				]
			],
			// #13.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Flexible',
						'id:delay_flex_0_delay' => '1M',
						'id:delay_flex_0_period' => '1-8,00:00-24:00'
					],
					'error_details' => 'Invalid interval "1M".'
				]
			],
			// #14.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:custom_timeout' => 'Override',
						'id:timeout' => 0
					],
					'error_details' => 'Invalid parameter "/1/timeout": value must be one of 1-600.'
				]
			],
			// #15.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:custom_timeout' => 'Override',
						'id:timeout' => 601
					],
					'error_details' => 'Invalid parameter "/1/timeout": value must be one of 1-600.'
				]
			],
			// #16.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:custom_timeout' => 'Override',
						'id:timeout' => 9999999999
					],
					'error_details' => 'Invalid parameter "/1/timeout": a number is too large.'
				]
			],
			// #17.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:custom_timeout' => 'Override',
						'id:timeout' => 'text'
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Field "Timeout" is not correct: a time unit is expected'
				]
			],
			// #18.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation 1',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:lifetime_type' => 'After',
						'id:lifetime' => ''
					],
					'error_details' => 'Invalid parameter "/1/lifetime": cannot be empty.'
				]
			],
			// #19.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation 2',
						'Type' => 'Zabbix agent',
						'Key' => 'test2',
						'id:lifetime_type' => 'After',
						'id:lifetime' => '1M'
					],
					'error_details' => 'Invalid parameter "/1/lifetime": a time unit is expected.'
				]
			],
			// #20.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'After',
						'id:lifetime' => '{#LLD}'
					],
					'error_details' => 'Invalid parameter "/1/lifetime": a time unit is expected.'
				]
			],
			// #21.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'After',
						'id:lifetime' => 1
					],
					'error_details' => 'Invalid parameter "/1/lifetime": value must be one of 0, 3600-788400000.'
				]
			],
			// #22.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'After',
						'id:lifetime' => 3599
					],
					'error_details' => 'Invalid parameter "/1/lifetime": value must be one of 0, 3600-788400000.'
				]
			],
			// #23.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'After',
						'id:lifetime' => 788400001
					],
					'error_details' => 'Invalid parameter "/1/lifetime": value must be one of 0, 3600-788400000.'
				]
			],
			// #24.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'After',
						'id:lifetime' => 999999999999
					],
					'error_details' => 'Invalid parameter "/1/lifetime": a number is too large.'
				]
			],
			// #25.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'Never',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => ''
					],
					'error_details' => 'Invalid parameter "/1/enabled_lifetime": cannot be empty.'
				]
			],
			// #26.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'Never',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => '1M'
					],
					'error_details' => 'Invalid parameter "/1/enabled_lifetime": a time unit is expected.'
				]
			],
			// #27.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'Never',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => '{#LLDMACRO}'
					],
					'error_details' => 'Invalid parameter "/1/enabled_lifetime": a time unit is expected.'
				]
			],
			// #28.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'Never',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => 1
					],
					'error_details' => 'Invalid parameter "/1/enabled_lifetime": value must be one of 0, 3600-788400000.'
				]
			],
			// #29.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'Never',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => 3599
					],
					'error_details' => 'Invalid parameter "/1/enabled_lifetime": value must be one of 0, 3600-788400000.'
				]
			],
			// #30.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'Never',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => 788400001
					],
					'error_details' => 'Invalid parameter "/1/enabled_lifetime": value must be one of 0, 3600-788400000.'
				]
			],
			// #31.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'Never',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => 999999999999
					],
					'error_details' => 'Invalid parameter "/1/enabled_lifetime": a number is too large.'
				]
			],
			// #32.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Lifetime fields validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test',
						'id:lifetime_type' => 'After',
						'id:lifetime' => '1h',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => '1d'
					],
					'error_details' => 'Incorrect value for field "Disable lost resources":'.
						' cannot be greater than or equal to the value of field "Delete lost resources".'
				]
			],
			// #33.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'SNMP LLD',
						'Type' => 'SNMP agent',
						'Key' => 'snmp.test',
						'SNMP OID' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Incorrect value for field "SNMP OID": cannot be empty.'
				]
			],
			// #34.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Trapper',
						'Type' => 'Zabbix trapper',
						'Key' => 'test[1]',
						'Allowed hosts' => '::ffff:127.0.0.1'
					],
					'error_details' => 'Invalid parameter "/1/trapper_hosts": incorrect address starting from ".0.0.1".'
				]
			],
			// #35.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Database monitor',
						'Type' => 'Database monitor',
						'Key' => 'db.check',
						'SQL query' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Incorrect value for field "SQL query": cannot be empty.'
				]
			],
			// #36.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Database monitor',
						'Type' => 'Database monitor',
						'Key' => 'db.odbc.select[<unique short description>,<dsn>,<connection string>]',
						'SQL query' => 'test'
					],
					'error_details' => 'Check the key, please. Default example was passed.'
				]
			],
			// #37.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'HTTP check',
						'Type' => 'HTTP agent',
						'Key' => 'http.check',
						'URL' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Incorrect value for field "URL": cannot be empty.'
				]
			],
			// #38.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'HTTP check',
						'Type' => 'HTTP agent',
						'Key' => 'http.check',
						'URL' => 'test',
						'name:query_fields[0][name]' => '',
						'name:query_fields[0][value]' => 'test'
					],
					'error_details' => 'Invalid parameter "/1/query_fields/1/name": cannot be empty.'
				]
			],
			// #39.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'HTTP check',
						'Type' => 'HTTP agent',
						'Key' => 'http.check',
						'URL' => 'test',
						'name:headers[0][name]' => '',
						'name:headers[0][value]' => 'test'
					],
					'error_details' => 'Invalid parameter "/1/headers/1/name": cannot be empty.'
				]
			],
			// #40.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'HTTP LLD simple 2',
						'Type' => 'HTTP agent',
						'Key' => 'http_check[2]',
						'URL' => 'www.test.com/search',
						'Request body type' => 'JSON data',
						'Request body' => ''
					],
					'error_details' => 'Invalid parameter "/1/posts": cannot be empty.'
				]
			],
			// #41.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'HTTP check',
						'Type' => 'HTTP agent',
						'Key' => 'http.check',
						'URL' => 'test',
						'Request type' => 'PUT',
						'Request body type' => 'XML data',
						'Request body' => ''
					],
					'error_details' => 'Invalid parameter "/1/posts": cannot be empty.'
				]
			],
			// #42.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'HTTP check',
						'Type' => 'HTTP agent',
						'Key' => 'http.check',
						'URL' => 'test',
						'Request type' => 'PUT',
						'Request body type' => 'XML data',
						'Request body' => 'test'
					],
					'error_details' => 'Invalid parameter "/1/posts": (4) Start tag expected, '<' not found [Line: 1 | Column: 1].'
				]
			],
			// #43.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'HTTP check',
						'Type' => 'HTTP agent',
						'Key' => 'http.check',
						'URL' => 'test',
						'Enable trapping' => true,
						'Allowed hosts' => '::ffff:127.0.0.1'
					],
					'error_details' => 'Invalid parameter "/1/trapper_hosts": incorrect address starting from ".0.0.1".'
				]
			],
			// #44.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'IPMI agent LLD',
						'Type' => 'IPMI agent',
						'Key' => 'ipmi_check[]',
						'IPMI sensor' => ''
					],
					'error_details' => 'Invalid parameter "/1/ipmi_sensor": cannot be empty.'
				]
			],
			// #45.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'SSH agent LLD',
						'Type' => 'SSH agent',
						'Key' => 'ssh_check[]',
						'User name' => '',
						'Executed script' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => [
						'Incorrect value for field "User name": cannot be empty.',
						'Incorrect value for field "Executed script": cannot be empty.'
					]
				]
			],
			// #46.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'SSH agent LLD',
						'Type' => 'SSH agent',
						'Key' => 'ssh.run[<unique short description>,<ip>,<port>,<encoding>,<ssh options>,<subsystem>]',
						'User name' => 'test_user',
						'Executed script' => 'test_script'
					],
					'error_details' => 'Check the key, please. Default example was passed.'
				]
			],
			// #47.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'TELNET agent LLD',
						'Type' => 'TELNET agent',
						'Key' => 'telnet_check[]',
						'User name' => '',
						'Executed script' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => [
						'Incorrect value for field "User name": cannot be empty.',
						'Incorrect value for field "Executed script": cannot be empty.'
					]
				]
			],
			// #48.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'JMX agent LLD empty',
						'Type' => 'JMX agent',
						'Key' => 'jmx_check[]',
						'JMX endpoint' => '',
						'User name' => '',
						'Password' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Incorrect value for field "jmx_endpoint": cannot be empty.'
				]
			],
			// #49.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Dependent LLD master empty',
						'Type' => 'Dependent item',
						'Key' => 'dependent_check[]',
						'Master item' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Field "Master item" is mandatory.'
				]
			],
			// #50.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'JMX agent',
						'Type' => 'JMX agent',
						'Key' => 'jmx_check[]',
						'User name' => 'Test',
						'Password' => '',
						'JMX endpoint' => 'test'
					],
					'error_details' => 'Invalid parameter "/1": both username and password should be either present or empty.'
				]
			],
			// #51.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'JMX agent',
						'Type' => 'JMX agent',
						'Key' => 'jmx_check[]',
						'User name' => '',
						'Password' => 'Test',
						'JMX endpoint' => 'test'
					],
					'error_details' => 'Invalid parameter "/1": both username and password should be either present or empty.'
				]
			],
			// #52.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty script',
						'Type' => 'Script',
						'Key' => 'script_check[3]',
						'Script' => ""
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Incorrect value for field "Script": cannot be empty.'
				]
			],
			// #53.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty script',
						'Type' => 'Script',
						'Key' => 'script_check[4]',
						'Script' => 'wait(2000).then(() => goToPage());'
					],
					'Parameters' => [
						['Name' => '', 'Value' => 'value_1']
					],
					'error_details' => 'Invalid parameter "/1/parameters/1/name": cannot be empty.'
				]
			],
			// #54.
			[
				[
					'fields' => [
						'Name' => 'Simple LLD',
						'Type' => 'Zabbix agent',
						'Key' => 'agent'
					]
				]
			],
			// #55.
			[
				[
					'fields' => [
						'Name' => 'Simple active LLD',
						'Type' => 'Zabbix agent (active)',
						'Key' => 'active.agent[]',
						'Update interval' => 1,
						'id:delay_flex_0_type' => 'Flexible',
						'id:delay_flex_0_delay' => '    100s   ',
						'id:delay_flex_0_period' => '     1-5,00:00-18:00       ',
						'id:custom_timeout' => 'Override',
						'id:timeout' => 1,
						'id:lifetime_type' => 'Never',
						'id:enabled_lifetime_type' => 'Never',
						'Description' => 'Test description',
						'Enabled' => false
					],
					'trim' => true
				]
			],
			// #56.
			[
				[
					'fields' => [
						'Name' => 'Simple check LLD',
						'Type' => 'Simple check',
						'Key' => 'simple.check[123, param]',
						'Update interval' => 86400,
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => '    wd1-3h15-18       ',
						'id:lifetime_type' => 'Immediately'
					],
					'trim' => true
				]
			],
			// #57.
			[
				[
					'fields' => [
						'Name' => 'SNMP LLD 1',
						'Type' => 'SNMP agent',
						'Key' => 'snmp.test',
						'Update interval' => '24h',
						'Host interface' => '127.0.0.3:161SNMPv1, Community: {$SNMP_COMMUNITY}',
						'SNMP OID' => '.1.3.6.1.2.1.1.1.0',
						'id:lifetime_type' => 'After',
						'id:lifetime' => '   3601    ',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => '   3600      '
					],
					'checked_interface' => '127.0.0.3:161',
					'trim' => true
				]
			],
			// #58.
			[
				[
					'fields' => [
						'Name' => 'SNMP LLD 2',
						'Type' => 'SNMP agent',
						'Key' => 'snmp.test[1]',
						'Update interval' => '1d',
						'SNMP OID' => 'walk[.1.3.6.1.4.1,.1.3.6.1.2.1.1.1.0,.1.3.6.1.2.1.7.3.0]',
						'id:lifetime_type' => 'After',
						'id:lifetime' => 788400000,
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => 788399999
					]
				]
			],
			// #59.
			[
				[
					'fields' => [
						'Name' => 'SNMP LLD 3',
						'Type' => 'SNMP agent',
						'Key' => 'snmp.test[{#MACRO}]      ',
						'SNMP OID' => '   discovery[{#MACRO1},.1.3.6.1.2.1.1.1.0,{#MACRO2}]   '
					],
					'trim' => true
				]
			],
			// #60.
			[
				[
					'fields' => [
						'Name' => 'SNMP LLD 4',
						'Type' => 'SNMP agent',
						'Key' => 'snmp.test[{$MACRO}]',
						'SNMP OID' => '{$MACRO}'
					]
				]
			],
			// #61.
			[
				[
					'fields' => [
						'Name' => 'SNMP LLD 5',
						'Type' => 'SNMP agent',
						'Key' => 'snmp.test["OID"]',
						'SNMP OID' => '🙂🙃み け め 𒁥'
					]
				]
			],
			// #62.
			[
				[
					'fields' => [
						'Name' => 'Internal',
						'Type' => 'Zabbix internal',
						'Key' => 'zabbix[triggers]'
					]
				]
			],
			// #63.
			[
				[
					'fields' => [
						'Name' => 'Trapper',
						'Type' => 'Zabbix trapper',
						'Key' => 'test[1]',
						'Allowed hosts' => '     127.0.0.2,::/0,0.0.0.0/0,mysqlserver1, zabbix.example.com, {HOST.HOST},'.
								'192.168.1-10.1-255, ::1,2001:db8::/32, {$MACRO},::ffff, ::         '
					],
					'trim' => true
				]
			],
			// #64.
			[
				[
					'fields' => [
						'Name' => 'External',
						'Type' => 'External check',
						'Key' => 'zabbix[external]'
					]
				]
			],
			// #65.
			[
				[
					'fields' => [
						'Name' => 'Database monitor',
						'Type' => 'Database monitor',
						'Key' => 'db_check[]',
						'SQL query' => '   test query    '
					],
					'trim' => true
				]
			],
			// #66.
			[
				[
					'fields' => [
						'Name' => 'HTTP LLD simple',
						'Type' => 'HTTP agent',
						'Key' => 'http_check[1]',
						'URL' => 'www.test.com/search'
					]
				]
			],
			// #67.
			[
				[
					'fields' => [
						'Name' => 'HTTP JSON',
						'Type' => 'HTTP agent',
						'Key' => 'http_check[2]',
						'URL' => 'www.test.com/search',
						'Request body type' => 'JSON data',
						'Request body' => '{"export": {"version": "6.0","date": "2024-03-20T20:05:14Z"}}'
					]
				]
			],
			// #68.
			[
				[
					'fields' => [
						'Name' => 'HTTP XML',
						'Type' => 'HTTP agent',
						'Key' => 'http_check[3]',
						'URL' => 'www.test.com/search',
						'Request body type' => 'XML data',
						'Request body' => '<export><version>6.0</version><date>2024-03-20T20:05:14Z</date></export>'
					]
				]
			],
			// #69.
			[
				[
					'fields' => [
						'Name' => 'HTTP LLD',
						'Type' => 'HTTP agent',
						'Key' => 'http_check[]',
						'URL' => 'https://www.test.com/search?q=cat&rlz=1C1GCEU_enLV1043LV1043',
						'Required status codes' => '200,200-{$M},{$M},200-400',
						'HTTP authentication' => 'NTLM'
					],
					'parse' => true,
					'parsed' => [
						'url' => 'https://www.test.com/search',
						'fields' => [
							['Name' => 'q', 'Value' => 'cat'],
							['Name' => 'rlz', 'Value' => '1C1GCEU_enLV1043LV1043']
						]
					],
					'Headers' => [
						['Name' => 'name1', 'Value' => 'value_1'],
						['Name' => 'name2', 'Value' => 'value_2']
					]
				]
			],
			// #70.
			[
				[
					'fields' => [
						'Name' => '   {$MACRO}     ',
						'Type' => 'HTTP agent',
						'Key' => 'key[{$MACRO}]       ',
						'URL' => '      {$MACRO}     ',
						'name:query_fields[0][name]' => '          {$MACRO}     ',
						'name:query_fields[0][value]' => '        {$MACRO}       ',
						'Request type' => 'POST',
						'Request body type' => 'JSON data',
						'Request body' => '         {$MACRO}      ',
						'name:headers[0][name]' => '     {$MACRO}        ',
						'name:headers[0][value]' => '     {$MACRO}     ',
						'Required status codes' => '    {$MACRO}, {$MACRO_2}     ',
						'Follow redirects' => false,
						'Retrieve mode' => 'Body and headers',
						'HTTP proxy' => '      {$MACRO}           ',
						'HTTP authentication' => 'Basic',
						'id:http_username' => '         {$MACRO}        ',
						'id:http_password' => '        {$MACRO}        ',
						'SSL verify peer' => false,
						'SSL verify host' => false,
						'SSL certificate file' => '        {$MACRO}      ',
						'SSL key file' => '       {$MACRO}          ',
						'SSL key password' => '              {$MACRO}       ',
						'Update interval' => '      {$MACRO}      ',
						'id:custom_timeout' => 'Override',
						'id:timeout' => '       {$MACRO}      ',
						'id:lifetime_type' => 'After',
						'id:lifetime' => '   {$MACRO}    ',
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => '   {$MACRO}    ',
						'Enable trapping' => true,
						'Allowed hosts' => '     {$MACRO}         ',
						'Description' => '     {$MACRO}       '
					],
					'Custom intervals' => [
						['Type' => 'Flexible', 'Interval' => '{$MACRO}', 'Period' => '{$MACRO2}'],
						['Type' => 'Scheduling', 'Interval' => '{$MACRO}']
					],
					'trim' => true
				]
			],
			// #71.
			[
				[
					'fields' => [
						'Name' => '🙂🙃み け め 𒁥',
						'Type' => 'HTTP agent',
						'Key' => 'test[🙂🙃み け め 𒁥]',
						'URL' => '🙂🙃み け め 𒁥',
						'name:query_fields[0][name]' => '🙂🙃み け め 𒁥',
						'name:query_fields[0][value]' => '🙂🙃み け め 𒁥',
						'Request type' => 'PUT',
						'Request body type' => 'Raw data',
						'Request body' => '🙂🙃み け め 𒁥',
						'name:headers[0][name]' => '🙂🙃み け め 𒁥',
						'name:headers[0][value]' => '🙂🙃み け め 𒁥',
						'HTTP proxy' => '🙂🙃み け め 𒁥',
						'HTTP authentication' => 'Kerberos',
						'id:http_username' => '🙂🙃み け め 𒁥',
						'id:http_password' => '🙂🙃み け め 𒁥',
						'SSL certificate file' => '🙂🙃み け め 𒁥',
						'SSL key file' => '🙂🙃み け め 𒁥',
						'SSL key password' => '🙂🙃み け め 𒁥',
						'Description' => '🙂🙃み け め 𒁥'
					]
				]
			],
			// #72.
			[
				[
					'fields' => [
						'Name' => '{#LLD_MACRO}',
						'Type' => 'HTTP agent',
						'Key' => 'test[{#LLD_MACRO}]',
						'URL' => '{?EXPRESSION}',
						'name:query_fields[0][name]' => '{ITEM.KEY}',
						'name:query_fields[0][value]' => '{ITEM.VALUE}',
						'Request type' => 'HEAD',
						'Request body type' => 'Raw data',
						'Request body' => '{#LLD_MACRO}',
						'name:headers[0][name]' => '{#LLD_MACRO}',
						'name:headers[0][value]' => '{#LLD_MACRO}',
						'HTTP proxy' => '{HOST.HOST}',
						'HTTP authentication' => 'Kerberos',
						'id:http_username' => '{#LLD_MACRO}',
						'id:http_password' => '{?EXPRESSION}',
						'SSL certificate file' => '{#LLD_MACRO}',
						'SSL key file' => '{#LLD_MACRO}',
						'SSL key password' => '{#LLD_MACRO}',
						'Description' => '{?EXPRESSION}'
					]
				]
			],
			// #73.
			[
				[
					'fields' => [
						'Name' => 'IPMI agent LLD',
						'Type' => 'IPMI agent',
						'Key' => 'ipmi_check[]',
						// This field is not being trimmed on purpose.
						'IPMI sensor' => '     test sensor  🙂🙃み け め 𒁥     '
					]
				]
			],
			// #74.
			[
				[
					'fields' => [
						'Name' => 'SSH agent LLD no password',
						'Type' => 'SSH agent',
						'Key' => 'ssh_check[]',
						'User name' => 'test_username',
						'Password' => '',
						'Executed script' => 'test script'
					]
				]
			],
			// #75.
			[
				[
					'fields' => [
						'Name' => '      SSH agent LLD 🙂🙃み け め 𒁥      ',
						'Type' => 'SSH agent',
						'Key' => 'ssh_check[🙂🙃み け め 𒁥]       ',
						'User name' => '         🙂🙃み け め 𒁥        ',
						'Password' => '       🙂🙃み け め 𒁥       ',
						'Executed script' => '        🙂🙃み け め 𒁥         '
					],
					'trim' => true
				]
			],
			// #76.
			[
				[
					'fields' => [
						'Name' => 'SSH agent LLD {$MACRO}',
						'Type' => 'SSH agent',
						'Key' => 'ssh_check[2]',
						'User name' => '{$MACRO}',
						'Password' => '{$MACRO}',
						'Executed script' => '{$MACRO}'
					]
				]
			],
			// #77.
			[
				[
					'fields' => [
						'Name' => 'TELNET agent LLD {$MACRO}',
						'Type' => 'TELNET agent',
						'Key' => 'telnet_check[{$MACRO}]',
						'User name' => '  {$MACRO}     ',
						'Password' => '   {$MACRO}       ',
						'Executed script' => '      {$MACRO}  🙂🙃み け め 𒁥     '
					],
					'trim' => true
				]
			],
			// #78.
			[
				[
					'fields' => [
						'Name' => 'JMX agent LLD {$MACRO}',
						'Type' => 'JMX agent',
						'Key' => 'jmx_check[{$MACRO}]',
						'User name' => '',
						'Password' => '',
						'JMX endpoint' => '      service:jmx:{$MACRO}:///jndi/[🙂🙃み け め 𒁥]'.
								'://{HOST.CONN}:{HOST.PORT}/jmxrmi          '
					],
					'trim' => true
				]
			],
			// #79.
			[
				[
					'fields' => [
						'Name' => 'Dependent LLD',
						'Type' => 'Dependent item',
						'Key' => 'dependent_lld',
						'Master item' => 'Master item'
					]
				]
			],
			// #80.
			[
				[
					'fields' => [
						'Name' => 'Script LLD',
						'Type' => 'Script',
						'Key' => 'script_check[1]',
						'Script' => '  wait(2000).then(() => goToPage());    '
					],
					'Parameters' => [
						['Name' => 'param_1', 'Value' => 'value_1'],
						['Name' => 'param_2', 'Value' => 'value_2']
					],
					'trim' => true
				]
			],
			// #81.
			[
				[
					'fields' => [
						'Name' => 'Multiline Script LLD',
						'Type' => 'Script',
						'Key' => 'script_check[2]',
						'Script' => "const = 'Hello World!';".
								"\r\nlet favePhrase = const;".
								"\r\nnconsole.log(favePhrase);"
					],
					'Parameters' => [
						['Name' => 'param_1', 'Value' => 'value_1'],
						['Name' => 'param_2', 'Value' => 'value_2']
					]
				]
			],
			// LLD Macros tab.
			// #82.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Macro with empty path',
						'Key' => 'macro-with-empty-path',
						'Type' => 'Zabbix agent'
					],
					'LLD macros' => [
						['LLD macro' => '{#MACRO}', 'JSONPath' => '']
					],
					'error_details' => 'Invalid parameter "/1/lld_macro_paths/1/path": cannot be empty.'
				]
			],
			// #83.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Macro without #',
						'Key' => 'macro-without-hash',
						'Type' => 'Zabbix agent'
					],
					'LLD macros' => [
						['LLD macro' => '{MACRO}', 'JSONPath' => '$.path']
					],
					'error_details' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a low-level discovery macro is expected.'
				]
			],
			// #84.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Macro with cyrillic symbols',
						'Key' => 'macro-with-cyrillic-symbols',
						'Type' => 'Zabbix agent'
					],
					'LLD macros' => [
						['LLD macro' => '{#МАКРО}', 'JSONPath' => '$.path']
					],
					'error_details' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a low-level discovery macro is expected.'
				]
			],
			// #85.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Macro with special symbols',
						'Key' => 'macro-with-with-special-symbols',
						'Type' => 'Zabbix agent'
					],
					'LLD macros' => [
						['LLD macro' => '{#MACRO!@$%^&*()_+|?}', 'JSONPath' => '$.path']
					],
					'error_details' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a low-level discovery macro is expected.'
				]
			],
			// #86.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'LLD with empty macro',
						'Key' => 'lld-with-empty-macro',
						'Type' => 'Zabbix agent'
					],
					'LLD macros' => [
						['LLD macro' => '', 'JSONPath' => '$.path']
					],
					'error_details' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": cannot be empty.'
				]
			],
			// #87.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'LLD with context macro',
						'Key' => 'lld-with-context-macro',
						'Type' => 'Zabbix agent'
					],
					'LLD macros' => [
						['LLD macro' => '{$MACRO:A}', 'JSONPath' => '$.path']
					],
					'error_details' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a low-level discovery macro is expected.'
				]
			],
			// #88.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'LLD with two equal macros',
						'Key' => 'lld-with-two-equal-macros',
						'Type' => 'Zabbix agent'
					],
					'LLD macros' => [
						['LLD macro' => '{#MACRO}', 'JSONPath' => '$.path.a'],
						['LLD macro' => '{#MACRO}', 'JSONPath' => '$.path.2']
					],
					'error_details' => 'Invalid parameter "/1/lld_macro_paths/2": value (lld_macro)=({#MACRO}) already exists.'
				]
			],
			// #89.
			[
				[
					'fields' => [
						'Name' => 'LLD with valid macro and path',
						'Key' => 'lld-with-valid-macro-and-path',
						'Type' => 'Zabbix agent'
					],
					'LLD macros' => [
						['LLD macro' => '{#MACRO1}', 'JSONPath' => '$.path.a'],
						['LLD macro' => '{#MACRO2}', 'JSONPath' => "$['а']['!@#$%^&*()_+'].🙂🙃み け め 𒁥"]
					]
				]
			],
			// Filters tab.
			// #90.
			[
				[
					'fields' => [
						'Name' => 'Rule with macro does not match',
						'Key' => 'macro-doesnt-match-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'filters' => [
							['Macro' => '{#TEST_MACRO}', 'Regular expression' => 'Test expression', 'operator' => 'does not match']
						]
					]
				]
			],
			// #91.
			[
				[
					'fields' => [
						'Name' => 'Rule with macro exists',
						'Key' => 'macro-exists',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'filters' => [
							['Macro' => '{#TEST_MACRO}', 'Regular expression' => '', 'operator' => 'exists']
						]
					]
				]
			],
			// #92.
			[
				[
					'fields' => [
						'Name' => 'Rule with two macros And/Or',
						'Key' => 'two-macros-and-or-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'And/Or',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'matches', 'Regular expression' => 'Test expression 1'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'does not match', 'Regular expression' => '🙂🙃み け め 𒁥']
						]
					]
				]
			],
			// #93.
			[
				[
					'fields' => [
						'Name' => 'Rule with two macros And',
						'Key' => 'two-macros-and-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'And',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'matches', 'Regular expression' => 'Test expression 1'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'does not exist']
						]
					]
				]
			],
			// #94.
			[
				[
					'fields' => [
						'Name' => 'Rule with two macros Or',
						'Key' => 'two-macros-or-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Or',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'exists'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'does not match', 'Regular expression' => 'Test expression 2']
						]
					]
				]
			],
			// #95.
			[
				[
					'fields' => [
						'Name' => 'Rule with three macros Custom expression',
						'Key' => 'three-macros-custom-expression-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Custom expression',
						'formula' => 'not A or not (B and C)',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'matches', 'Regular expression' => 'Test expression 1'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'exists'],
							['Macro' => '{#TEST_MACRO3}', 'operator' => 'does not exist']
						]
					]
				]
			],
			// #96.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Rule with wrong macro',
						'Key' => 'macro-wrong-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'filters' => [
							['Macro' => '{TEST_MACRO}', 'operator' => 'does not match', 'Regular expression' => 'Test expression']
						]
					],
					'error_details' => 'Invalid parameter "/1/filter/conditions/1/macro": a low-level discovery macro is expected.'
				]
			],
			// #97.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Rule with empty formula',
						'Key' => 'macro-empty-formula-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Custom expression',
						'formula' => '',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'matches', 'Regular expression' => 'Test expression 1'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'exists']
						]
					],
					'error_details' => 'Invalid parameter "/1/filter/formula": cannot be empty.'
				]
			],
			// #98.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Rule with extra argument',
						'Key' => 'macro-extra-argument-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Custom expression',
						'formula' => 'A and B or F',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'matches', 'Regular expression' => 'Test expression 1'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'does not match', 'Regular expression' => 'Test expression 2']
						]
					],
					'error_details' => 'Invalid parameter "/1/filter/formula": missing filter condition "F".'
				]
			],
			// #99.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Rule with missing argument',
						'Key' => 'macro-missing-argument-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Custom expression',
						'formula' => 'A and B',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'matches', 'Regular expression' => 'Test expression 1'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'does not match', 'Regular expression' => 'Test expression 2'],
							['Macro' => '{#TEST_MACRO3}', 'operator' => 'does not exist']
						]
					],
					'error_details' => 'Invalid parameter "/1/filter/conditions/3/formulaid": an identifier is not'.
						' defined in the formula.'
				]
			],
			// #100.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Rule with wrong formula',
						'Key' => 'macro-wrong-formula-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Custom expression',
						'formula' => 'Wrong formula',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'exists'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'does not match', 'Regular expression' => 'Test expression 2']
						]
					],
					'error_details' => 'Invalid parameter "/1/filter/formula": incorrect syntax near "Wrong formula".'
				]
			],
			// #101.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Check case sensitive of operator in formula',
						'Key' => 'macro-not-in-formula-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Custom expression',
						'formula' => 'A and Not B',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'matches', 'Regular expression' => 'Test expression 1'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'does not match', 'Regular expression' => 'Test expression 2']
						]
					],
					'error_details' => 'Invalid parameter "/1/filter/formula": incorrect syntax near "Not B"'
				]
			],
			// #102.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Check case sensitive of first operator in formula',
						'Key' => 'macro-wrong-operator-key',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Custom expression',
						'formula' => 'NOT A and not B',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'exists'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'does not exist']
						]
					],
					'error_details' => 'Invalid parameter "/1/filter/formula": incorrect syntax near " A and not B".'
				]
			],
			// #103.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Test create with only NOT in formula',
						'Key' => 'macro-not-formula',
						'Type' => 'Zabbix agent'
					],
					'Filters' => [
						'calculation' => 'Custom expression',
						'formula' => 'not A not B',
						'filters' => [
							['Macro' => '{#TEST_MACRO1}', 'operator' => 'matches', 'Regular expression' => 'Test expression 1'],
							['Macro' => '{#TEST_MACRO2}', 'operator' => 'matches', 'Regular expression' => 'Test expression 2']
						]
					],
					'error_details' => 'Invalid parameter "/1/filter/formula": incorrect syntax near " not B".'
				]
			],
			// #104.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty script in Browser item',
						'Type' => 'Browser',
						'Key' => 'browser_check[2]',
						'Script' => ''
					],
					'error' => 'Page received incorrect data',
					'error_details' => 'Incorrect value for field "Script": cannot be empty.'
				]
			],
			// #105.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty parameters in Browser item',
						'Type' => 'Browser',
						'Key' => 'browser_check[2]',
						'Script' => 'test sript'
					],
					'Parameters' => [
						['Name' => '', 'Value' => 'value_1']
					],
					'error_details' => 'Invalid parameter "/1/parameters/1/name": cannot be empty.'
				]
			],
			// #106.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Identical parameters in Browser item',
						'Type' => 'Browser',
						'Key' => 'browser_check[2]',
						'Script' => 'test sript'
					],
					'Parameters' => [
						['Name' => 'test_name', 'Value' => 'value_1'],
						['Name' => 'test_name', 'Value' => 'value_2']
					],
					'error_details' => 'Invalid parameter "/1/parameters/2": value (name)=(test_name) already exists.'
				]
			],
			// #107.
			[
				[
					'fields' => [
						'Name' => 'Browser item',
						'Type' => 'Browser',
						'Key' => 'browser_check[3]',
						'Script' => 'test sript'
					],
					'Parameters' => [
						['Name' => 'param_1', 'Value' => 'value_1']
					]
				]
			]
		];
	}

	/**
	 * Check LLD create or update form fields.
	 *
	 * @param array   $data       data provider
	 * @param boolean $update     true for update scenario, false for create
	 */
	protected function checkForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		// Make name and key unique for every case.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD && $update
				&& $data['fields']['Name'] !== '') {
			$data['fields']['Name'] = trim($data['fields']['Name']).'_updated';
			$data['fields']['Key'] = 'upd.'.$data['fields']['Key'];
		}

		$url = (static::$context === 'template')
			? static::$templateid.'&context=template'
			: static::$interfaces_hostid.'&context=host';

		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$url);
		$this->query($update ? 'link:'.static::$update_lld : 'button:Create discovery rule')->waitUntilClickable()
				->one()->click();
		$form = $this->query('id:host-discovery-form')->asForm()->waitUntilVisible()->one();

		if (static::$context === 'template') {
			unset($data['fields']['Host interface']);
		}

		$form->fill($data['fields']);

		if (CTestArrayHelper::get($data, 'parse', false)) {
			$this->query('button:Parse')->waitUntilClickable()->one()->click();
		}

		// Fill custom intervals if there are more than one interval in data provider.
		if (array_key_exists('Custom intervals', $data)) {
			foreach ($data['Custom intervals'] as $i => $interval) {
				if ($i > 0) {
					$form->getFieldContainer('Custom intervals')->query('button:Add')->waitUntilClickable()->one()->click();
				}

				if ($interval['Type'] === 'Flexible') {
					$form->fill([
						'id:delay_flex_'.$i.'_type' => $interval['Type'],
						'id:delay_flex_'.$i.'_delay' => $interval['Interval'],
						'id:delay_flex_'.$i.'_period' => $interval['Period']
					]);
				}
				else {
					$form->fill([
						'id:delay_flex_'.$i.'_type' => $interval['Type'],
						'id:delay_flex_'.$i.'_schedule' => $interval['Interval']
					]);
				}
			}
		}

		// Fill Headers.
		if (array_key_exists('Headers', $data)) {
			$this->fillComplexFields($data['Headers'], $form, 'Headers', 'input');
		}

		// Fill Parameters.
		if (array_key_exists('Parameters', $data)) {
			$this->fillComplexFields($data['Parameters'], $form, 'Parameters', 'input');
		}

		// Fill LLD macros tab.
		if (array_key_exists('LLD macros', $data)) {
			$form->selectTab('LLD macros');
			$this->fillComplexFields($data['LLD macros'], $form, 'LLD macros', 'textarea');
		}

		// Fill Filters tab.
		if (array_key_exists('Filters', $data)) {
			$form->selectTab('Filters');
			$this->fillComplexFields($data['Filters']['filters'], $form, 'Filters', 'input');

			if (array_key_exists('calculation', $data['Filters'])) {
				$form->fill(['id:evaltype' => $data['Filters']['calculation']]);

				if (array_key_exists('formula', $data['Filters'])) {
					$form->fill(['id:formula' => $data['Filters']['formula']]);
				}
			}
		}

		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, CTestArrayHelper::get($data, 'error',
					($update ? 'Cannot update discovery rule' : 'Cannot add discovery rule')), $data['error_details']
			);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			$this->assertMessage(TEST_GOOD, $update ? 'Discovery rule updated' : 'Discovery rule created');

			// Remove leading and trailing spaces from data for assertion.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data = CTestArrayHelper::trim($data);
			}

			// Write new LLD name for the next update case.
			if ($update) {
				static::$update_lld = $data['fields']['Name'];
			}

			$this->assertEquals(1, CDBHelper::getCount(
				'SELECT * FROM items'.
				' WHERE name='.zbx_dbstr($data['fields']['Name']).
					' AND flags=1'
			));

			$this->query('link', $data['fields']['Name'])->waitUntilClickable()->one()->click();
			$form->invalidate();

			// Check parsed query fields.
			if (CTestArrayHelper::get($data, 'parse', false)) {
				$data['fields']['URL'] = $data['parsed']['url'];

				foreach ($data['parsed']['fields'] as $i => $field) {
					$form->checkValue([
						'name:query_fields['.$i.'][name]' => $field['Name'],
						'name:query_fields['.$i.'][value]' => $field['Value']
					]);
				}
			}

			// Rewrite complex interface value for SNMP.
			if (static::$context === 'host') {
				if (CTestArrayHelper::get($data, 'checked_interface', false)) {
					$data['fields']['Host interface'] = $data['checked_interface'];
				}
			}

			if ($data['fields']['Type'] === 'Dependent item') {
				$data['fields']['Master item'] = (static::$context === 'template')
					? 'Template with LLD: Master item'
					: 'Host for LLD form test with all interfaces: Master item';
			}

			$form->checkValue($data['fields']);

			// Check custom intervals.
			if (array_key_exists('Custom intervals', $data)) {
				foreach ($data['Custom intervals'] as $i => $interval) {
					if ($interval['Type'] === 'Flexible') {
						$form->checkValue([
							'id:delay_flex_'.$i.'_type' => $interval['Type'],
							'id:delay_flex_'.$i.'_delay' => $interval['Interval'],
							'id:delay_flex_'.$i.'_period' => $interval['Period']
						]);
					}
					else {
						$form->checkValue([
							'id:delay_flex_'.$i.'_type' => $interval['Type'],
							'id:delay_flex_'.$i.'_schedule' => $interval['Interval']
						]);
					}
				}
			}

			// Check Headers.
			if (array_key_exists('Headers', $data)) {
				$this->checkComplexFields($data['Headers'], $form, 'Headers', 'input');
			}

			// Check Parameters.
			if (array_key_exists('Parameters', $data)) {
				$this->checkComplexFields($data['Parameters'], $form, 'Parameters', 'input');
			}

			// Check LLD macros.
			if (array_key_exists('LLD macros', $data)) {
				$form->selectTab('LLD macros');
				$this->checkComplexFields($data['LLD macros'], $form, 'LLD macros', 'textarea');
			}

			// Check LLD Filters.
			if (array_key_exists('Filters', $data)) {
				$form->selectTab('Filters');
				$this->checkComplexFields($data['Filters']['filters'], $form, 'Filters', 'input');

				if (array_key_exists('calculation', $data['Filters'])) {
					$form->checkValue(['id:evaltype' => $data['Filters']['calculation']]);

					if (array_key_exists('formula', $data['Filters'])) {
						$form->checkValue(['id:formula' => $data['Filters']['formula']]);
					}
				}
			}
		}
	}

	/**
	 * Data for checking that LLD fields are being cloned correctly.
	 * Note that preprocessing cloning is fully checked in
	 * testFormPreprocessingCloneHost and testFormPreprocessingCloneTemplate.
	 */
	public static function getCloneData() {
		return [
			// #0 Clone with the same fields.
			[
				[
					'expected' => TEST_BAD
				]
			],
			// #1 Clone with just change LLD key but the same other fields.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Key' => 'simple_update_clone_key[cloned]'
					],
					'expected_fields' => [
						'Name' => self::SIMPLE_UPDATE_CLONE_LLD,
						'Type' => 'HTTP agent',
						'Key' => 'simple_update_clone_key[cloned]',
						'URL' => 'https://www.test.com/search',
						'name:query_fields[0][name]' => 'test_name1',
						'name:query_fields[0][value]' => 'value1',
						'name:query_fields[1][name]' => '2',
						'name:query_fields[1][value]' => 'value2',
						'Request type' => 'HEAD',
						'Request body type' => 'JSON data',
						'Request body' => '{"zabbix_export": {"version": "6.0","date": "2024-03-20T20:05:14Z"}}',
						'name:headers[0][name]' => 'name1',
						'name:headers[0][value]' => 'value',
						'Required status codes' => '400, 600',
						'Follow redirects' => true,
						'Retrieve mode' => 'Headers',
						'HTTP proxy' => '161.1.1.5',
						'HTTP authentication' => 'NTLM',
						'id:http_username' => 'user',
						'id:http_password' => 'pass',
						'SSL verify peer' => true,
						'SSL verify host' => true,
						'SSL certificate file' => '/home/test/certdb/ca.crt',
						'SSL key file' => '/home/test/certdb/postgresql-server.crt',
						'SSL key password' => '/home/test/certdb/postgresql-server.key',
						'Update interval' => '1h',
						'id:custom_timeout' => 'Override',
						'id:timeout' => '10s',
						'id:lifetime_type' => 'After',
						'id:lifetime' => '15d',
						'id:enabled_lifetime_type' => 'Never',
						'Enable trapping' => true,
						'Allowed hosts' => '127.0.2.3',
						'Description' => 'LLD for test',
						'Enabled' => true,
						// Preprocessing tab.
						'id:preprocessing_0_type' => 'Replace',
						'id:preprocessing_0_params_0' => 'a',
						'id:preprocessing_0_params_1' => 'b',
						// LLD macros tab.
						'id:lld_macro_paths_0_lld_macro' => '{#MACRO}',
						'id:lld_macro_paths_0_path' => '$.path',
						// Filters tab.
						'id:conditions_0_macro' => '{#MACRO}',
						'name:conditions[0][operator]' => 'does not match',
						'id:conditions_0_value' => 'expression'
					],
					'Overrides' => [
						'Name' => 'Override',
						'If filter matches' => 'Stop processing',
						'id:overrides_filters_0_macro' => '{#MACRO}',
						'name:overrides_filters[0][operator]' => 'exists'
					]
				]
			],
			// #2 Clone with all fields change.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => self::SIMPLE_UPDATE_CLONE_LLD.' cloned with field changes',
						'Type' => 'SSH agent',
						'Key' => 'simple_update_clone_key[cloned_2]',
						'Host interface' => '127.0.0.1:10050',
						'User name' => 'cloned_username',
						'Password' => 'cloned_password',
						'Executed script' => 'test_script',
						'Update interval' => '65s',
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => 'wd3-4h3-5',
						'id:lifetime_type' => 'Immediately',
						'Description' => 'New cloned description',
						'Enabled' => false
					],
					'Preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name!~"name"}']
					],
					'LLD macros' => [
						['LLD macro' => '{#CLONED_MACRO}', 'JSONPath' => '$.cloned.path.a']
					],
					'Filters' => [
						[
							'Macro' => '{#CLONED_FILTER_MACRO}',
							'Regular expression' => 'cloned_expression',
							'operator' => 'matches'
						]
					],
					'change_overrides' => true,
					'Overrides' => [
						'Name' => 'New Cloned override',
						'If filter matches' => 'Continue overrides',
						'id:overrides_filters_0_macro' => '{#NEW_CLONED_MACRO}',
						'name:overrides_filters[0][operator]' => 'does not exist'
					],
					'expected_fields' => [
						'Name' => self::SIMPLE_UPDATE_CLONE_LLD.' cloned with field changes',
						'Type' => 'SSH agent',
						'Key' => 'simple_update_clone_key[cloned_2]',
						'Host interface' => '127.0.0.1:10050',
						'User name' => 'cloned_username',
						'Password' => 'cloned_password',
						'Executed script' => 'test_script',
						'Update interval' => '65s',
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => 'wd3-4h3-5',
						'id:lifetime_type' => 'Immediately',
						'Description' => 'New cloned description',
						'Enabled' => false,
						// Preprocessing tab.
						'id:preprocessing_0_type' => 'Prometheus to JSON',
						'id:preprocessing_0_params_0' => '{label_name!~"name"}',
						// LLD macros tab.
						'id:lld_macro_paths_0_lld_macro' => '{#CLONED_MACRO}',
						'id:lld_macro_paths_0_path' => '$.cloned.path.a',
						// Filters tab.
						'id:conditions_0_macro' => '{#CLONED_FILTER_MACRO}',
						'name:conditions[0][operator]' => 'matches',
						'id:conditions_0_value' => 'cloned_expression'
					]
				]
			]
		];
	}

	/**
	 * Test for checking LLD cloning.
	 *
	 * @param  array $data       given data provider
	 */
	public function checkClone($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$url = (static::$context === 'template')
			? static::$templateid.'&context=template'
			: static::$hostid.'&context=host';

		$host_name = (static::$context === 'template') ? 'Template with LLD' : 'Host for LLD form test';
		$original_key = 'simple_update_clone_key';
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$url);
		$this->query('link', self::SIMPLE_UPDATE_CLONE_LLD)->waitUntilClickable()->one()->click();
		$form = $this->query('id:host-discovery-form')->asForm()->waitUntilVisible()->one();
		$form->query('button:Clone')->waitUntilClickable()->one()->click();
		$form->invalidate();
		$this->assertEquals(['Add', 'Test', 'Cancel'], $form->query('xpath:.//div[@class="form-actions"]/button')
				->all()->filter(CElementFilter::CLICKABLE)->asText()
		);

		if (CTestArrayHelper::get($data, 'fields')) {
			if (static::$context === 'template') {
				unset($data['fields']['Host interface']);
				unset($data['expected_fields']['Host interface']);
			}

			$form->fill($data['fields']);
		}

		// Change Preprocessing.
		if (array_key_exists('Preprocessing', $data)) {
			$form->selectTab('Preprocessing');
			$form->query('name:preprocessing[0][remove]')->waitUntilClickable()->one()->click();
			$this->addPreprocessingSteps($data['Preprocessing']);
		}

		// Change LLD macros.
		if (array_key_exists('LLD macros', $data)) {
			$form->selectTab('LLD macros');
			$this->fillComplexFields($data['LLD macros'], $form, 'LLD macros', 'textarea');
		}

		// Change Filters.
		if (array_key_exists('Filters', $data)) {
			$form->selectTab('Filters');
			$this->fillComplexFields($data['Filters'], $form, 'Filters', 'input');
		}

		// Change Overrides.
		if (array_key_exists('Overrides', $data) && CTestArrayHelper::get($data, 'change_overrides')) {
			$form->selectTab('Overrides');
			$form->query('link:Override')->waitUntilClickable()->one()->click();
			$override_dialog_form = COverlayDialogElement::find()->all()->last()->asForm()->waitUntilReady();
			$override_dialog_form->fill($data['Overrides']);
			$override_dialog_form->submit();
			$override_dialog_form->waitUntilNotVisible();
		}

		$form->submit();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Discovery rule created');

			// Check that LLD created on the right host.
			$this->page->assertHeader('Discovery rules');
			$this->assertTrue($this->query('xpath://ul[@class="breadcrumbs"]//a[text()='.
					CXPathHelper::escapeQuotes($host_name).']')->one()->isClickable()
			);

			// Open created new LLD form.
			$this->query('class:list-table')->asTable()->one()->findRow('Key', $data['fields']['Key'])
					->getColumn('Name')->query('tag:a')->waitUntilClickable()->one()->click();
			$form->invalidate();
			$form->checkValue($data['expected_fields']);

			if (array_key_exists('Overrides', $data)) {
				$form->selectTab('Overrides');
				$override_name = CTestArrayHelper::get($data, 'change_overrides', false)
					? $data['Overrides']['Name']
					: 'Override';
				$form->query('link', $override_name)->waitUntilClickable()->one()->click();
				$override_dialog_form = COverlayDialogElement::find()->all()->last()->asForm()->waitUntilReady();
				$override_dialog_form->checkValue($data['Overrides']);
				$override_dialog_form->submit();
				$override_dialog_form->waitUntilNotVisible();
			}

			// Check that original LLD remained in DB.
			$this->assertEquals(1, CDBHelper::getCount(
				'SELECT * FROM items'.
				' WHERE key_='.zbx_dbstr($original_key).
					' AND flags=1'
			));
		}
		else {
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
			$this->assertMessage(TEST_BAD, 'Cannot add discovery rule', 'An LLD rule with key "'.$original_key.'"'.
					' already exists on the '.static::$context.' "'.$host_name.'".'
			);
		}
	}

	public static function getCancelData() {
		return [
			[['action' => 'Add']],
			[['action' => 'Update']],
			[['action' => 'Clone']],
			[['action' => 'Delete']]
		];
	}

	/**
	 * Check cancelling LLD form.
	 *
	 * @param array  $data       given data provider
	 */
	protected function checkCancel($data) {
		$url = (static::$context === 'template')
			? static::$templateid.'&context=template'
			: static::$hostid.'&context=host';

		$old_hash = CDBHelper::getHash(self::SQL);
		$lld_name = 'LLD for cancel scenario';
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$url);

		if ($data['action'] === 'Add') {
			$this->query('button:Create discovery rule')->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('link', $lld_name)->waitUntilClickable()->one()->click();

			if ($data['action'] === 'Clone') {
				$this->query('button:Clone')->waitUntilClickable()->one()->click();
			}

			if ($data['action'] === 'Delete') {
				$this->query('button:Delete')->waitUntilClickable()->one()->click();
				$this->assertTrue($this->page->isAlertPresent());
				$this->assertEquals('Delete discovery rule?', $this->page->getAlertText());
				$this->page->dismissAlert();
			}
		}

		if ($data['action'] !== 'Delete') {
			$form = $this->query('id:host-discovery-form')->asForm()->waitUntilVisible()->one();

			$fields = [
				'lld_fields' => [
					'Name' => 'Updated LLD for cancel scenario',
					'Key' => 'new_key',
					'Type' => 'SSH agent',
					'Authentication method' => 'Password',
					'User name' => 'new_user',
					'Password' => 'new_password',
					'Executed script' => 'new script',
					'Update interval' => '2h',
					'id:delay_flex_0_type' => 'Scheduling',
					'id:delay_flex_0_schedule' => 'wd2-4h2-5',
					'id:custom_timeout' => 'Override',
					'id:timeout' => '25s',
					'id:lifetime_type' => 'Immediately',
					'Description' => 'New description',
					'Enabled' => false
				],
				'preprocessing_fields' => [
					[
						'type' => 'Prometheus to JSON',
						'parameter_1' => '{label_name!~"name"}'
					]
				],
				'lld_macros_fields' => [
					'id:lld_macro_paths_0_lld_macro' => '{#NEW_LLDMACRO}',
					'id:lld_macro_paths_0_path' => '$.new.path.to.node'
				],
				'filters_fields' => [
					'id:conditions_0_macro' => '{#NEW_FILTERMACRO}',
					'name:conditions[0][operator]' => 'does not match',
					'id:conditions_0_value' => 'new value'
				],
				'overrides_fields' => [
					'filters' => [
						'Name' => 'New override',
						'id:overrides_filters_0_macro' => '{#NEW_OVERRIDE_MACRO}',
						'name:overrides_filters[0][operator]' => 'exists'
					],
					'operations' => [
						'Object' => 'Host prototype',
						'id:visible_opstatus' => true
					]
				]
			];

			$form->fill($fields['lld_fields']);

			// Change Preprocessing.
			$form->selectTab('Preprocessing');

			if ($data['action'] === 'Add') {
				$form->query('id:param_add')->waitUntilClickable()->one()->click();
			}

			$this->addPreprocessingSteps($fields['preprocessing_fields']);

			// Change LLD Macros.
			$form->selectTab('LLD macros');
			$form->fill($fields['lld_macros_fields']);

			// Change Filters.
			$form->selectTab('Filters');
			$form->fill($fields['filters_fields']);

			// Change Overrides.
			$form->selectTab('Overrides');

			if ($data['action'] === 'Add') {
				$form->getFieldContainer('Overrides')->query('button:Add')->waitUntilClickable()->one()->click();
			}
			else {
				$form->query('link:Cancel override')->waitUntilClickable()->one()->click();
			}

			$override_dialog_form = COverlayDialogElement::find()->all()->last()->asForm()->waitUntilReady();
			$override_dialog_form->fill($fields['overrides_fields']['filters']);
			$override_dialog_form->getFieldContainer('Operations')->query('button:Add')->waitUntilClickable()->one()->click();
			$operation_dialog_form = COverlayDialogElement::find()->all()->last()->asForm()->waitUntilReady();
			$operation_dialog_form->fill($fields['overrides_fields']['operations']);
			$operation_dialog_form->submit();
			$operation_dialog_form->waitUntilNotVisible();
			$override_dialog_form->submit();
			$operation_dialog_form->waitUntilNotVisible();
			COverlayDialogElement::ensureNotPresent();
		}

		$this->query('button:Cancel')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->assertTrue($this->query('link', $lld_name)->one()->isVisible());
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Check deleting LLD from Host or Template.
	 */
	protected function checkDelete() {
		$url = (static::$context === 'template')
			? static::$templateid.'&context=template'
			: static::$hostid.'&context=host';

		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$url);
		$lld_name = 'LLD for delete scenario';
		$this->query('link', $lld_name)->waitUntilClickable()->one()->click();
		$this->query('button:Delete')->waitUntilClickable()->one()->click();

		// Check alert.
		$this->assertTrue($this->page->isAlertPresent());
		$this->assertEquals('Delete discovery rule?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Discovery rule deleted');

		// Check DB.
		$this->assertEquals(0, CDBHelper::getCount(
			'SELECT * FROM items'.
			' WHERE name='.zbx_dbstr($lld_name).
				' AND flags=1'
		));

		// Check frontend table.
		$this->assertFalse($this->query('link', $lld_name)->exists());

		// Check that user redirected on Discovery rules page.
		$this->page->assertTitle('Configuration of discovery rules');
	}

	/**
	 * Check inputs are visible/editable depending on other field's value.
	 *
	 * @param array $fields_array    given fields
	 */
	protected function checkFieldsParameters($fields_array) {
		$form = $this->query('id:host-discovery-form')->asForm()->waitUntilVisible()->one();

		foreach ($fields_array as $id => $parameters) {
			$error_output = 'Failed field: '.$id;

			$this->assertEquals(CTestArrayHelper::get($parameters, 'value', ''),
					$form->getField($id)->getValue(), $error_output
			);

			foreach (['placeholder', 'maxlength'] as $attribute) {
				$this->assertEquals(CTestArrayHelper::get($parameters, $attribute),
						$form->getField($id)->getAttribute($attribute), $error_output
				);
			}

			if (array_key_exists('options', $parameters)) {
				$this->assertEquals($parameters['options'], $form->getField($id)->getOptions()->asText(), $error_output);
			}

			if (array_key_exists('labels', $parameters)) {
				$this->assertEquals($parameters['labels'], $form->getField($id)->getLabels()->asText(), $error_output);
			}
		}
	}

	/**
	 * Check inputs are visible/editable depending on other field's value.
	 *
	 * @param CFormElement $form                  LLD edit form
	 * @param array        $master_field_value    field => value array which is being filled to cause dependency
	 * @param array        $dependant_array       given array of changed labels
	 */
	protected function checkFieldsDependency($form, $master_field_value, $dependant_array) {
		$form->fill($master_field_value);

		foreach ($dependant_array as $label => $status) {
			$dependant_field = $form->getField($label);
			$this->assertTrue($dependant_field->isVisible($status['visible']));
			$this->assertTrue($dependant_field->isEnabled($status['enabled']));
		}
	}

	/**
	 * Fill complex or draggable fields in the view like name => value.
	 *
	 * @param array        $data       given array of fields
	 * @param CFormElement $form       LLD edit form
	 * @param string       $label      container's label
	 * @param string       $locator    field's locator input or textarea
	 */
	protected function fillComplexFields($data, $form, $label, $locator) {
		$table = $form->getField($label);
		$add_button = $table->query('button:Add')->one();

		foreach ($data as $i => $data_row) {
			$table_row = $table->getRows()->get($i);

			foreach ($data_row as $column => $value) {
				if ($column === 'operator') {
					$form->fill(['name:conditions['.$i.'][operator]' => $value]);
				}
				else {
					$table_row->getColumn($column)->query('tag', $locator)->one()->fill($value);
				}
			}

			if ($i !== (count($data) - 1)) {
				$add_button->click();
			}
		}
	}

	/**
	 * Check complex or draggable fields.
	 *
	 * @param array        $data       given array of fields
	 * @param CFormElement $form       LLD edit form
	 * @param string       $label      container's label
	 * @param string       $locator    field's locator input or textarea
	 */
	protected function checkComplexFields($data, $form, $label, $locator) {
		$table = $form->getField($label);

		foreach ($data as $i => $data_row) {
			$row = $table->getRows()->get($i);

			foreach ($data_row as $column => $value) {
				if ($column === 'operator') {
					$form->checkValue(['name:conditions['.$i.'][operator]' => $value]);
				}
				else {
					$this->assertEquals($value, $row->getColumn($column)->query('tag', $locator)->one()->getValue());
				}
			}
		}
	}

	protected static function deleteData() {
		$hostids = CDBHelper::getColumn('SELECT hostid FROM hosts_groups WHERE groupid='.zbx_dbstr(static::$groupid),
				'hostid'
		);

		$delete_methods = (static::$context === 'host')
			? ['host' => 'host.delete', 'group' => 'hostgroup.delete']
			: ['host' => 'template.delete', 'group' => 'templategroup.delete'];

		CDataHelper::call($delete_methods['host'], array_values($hostids));
		CDataHelper::call($delete_methods['group'], [static::$groupid]);
	}
}
