<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

/**
 * @backup hosts
 *
 * @onBefore prepareLLDData
 */
class testFormLowLevelDiscovery extends CWebTest {

	const SQL = 'SELECT * FROM items WHERE flags=1 ORDER BY itemid';

	protected static $hostid;
	protected static $interfaces_hostid;
	protected static $update_lld = 'LLD for update scenario';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	public function prepareLLDData() {
		$result = CDataHelper::createHosts([
			[
				'host' => 'Host for LLD form test with all interfaces',
				'groups' => ['groupid' => 4], // Zabbix servers.
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					],
					[
						'type'=> INTERFACE_TYPE_SNMP,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.2',
						'dns' => '',
						'port' => '161',
						'details' => [
							'version' => SNMP_V2C,
							'community' => '{$SNMP_COMMUNITY}',
							'max_repetitions' => 10
						]
					],
					[
						'type'=> INTERFACE_TYPE_SNMP,
						'main' => INTERFACE_SECONDARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.3',
						'dns' => '',
						'port' => '161',
						'details' => [
							'version' => SNMP_V1,
							'community' => '{$SNMP_COMMUNITY}'
						]
					],
					[
						'type' => INTERFACE_TYPE_JMX,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '',
						'dns' => 'text.jmx.com',
						'port' => '12345'
					],
					[
						'type' => INTERFACE_TYPE_IPMI,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.4',
						'dns' => '',
						'port' => '12345'
					]
				],
				'discoveryrules' => [
					[
						'name' => 'LLD for update scenario',
						'key_' => 'vfs.fs.discovery2',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 30
					]
				]
			],
			[
				'host' => 'Host for LLD form test',
				'groups' => ['groupid' => 4], // Zabbix servers.
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					]
				],
				'discoveryrules' => [
					[
						'name' => 'LLD for delete scenario',
						'key_' => 'vfs.fs.discovery2',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 30
					],
					[
						'name' => 'LLD for clone scenario',
						'key_' => 'vfs.fs.discovery3',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 30
					],
					[
						'name' => 'LLD for simple update scenario',
						'key_' => 'update_key',
						'type' => ITEM_TYPE_HTTPAGENT,
						'delay' => "1h;wd1-2h7-14",
						'url' => 'https://www.test.com/search',
						'query_fields' => [['name' => 'test_name1', 'value' => 'value1'], ['name' => '2', 'value' => 'value2']],
						'request_method' => HTTPCHECK_REQUEST_HEAD,
						'post_type' => ZBX_POSTTYPE_JSON,
						'posts' => "{\"zabbix_export\": {\"version\": \"6.0\",\"date\": \"2024-03-20T20:05:14Z\"}}",
						'headers' => [['name' => 'name1', 'value' => 'value']],
						'status_codes' => '400, 600',
						'follow_redirects' => 1,
						'retrieve_mode' => 1,
						'http_proxy' => '161.1.1.5',
						'authtype' => ZBX_HTTP_AUTH_NTLM,
						'username' => 'user',
						'password' => 'pass',
						'verify_peer' => ZBX_HTTP_VERIFY_PEER_ON,
						'verify_host' => ZBX_HTTP_VERIFY_HOST_ON,
						'ssl_cert_file' => '/home/test/certdb/ca.crt',
						'ssl_key_file' => '/home/test/certdb/postgresql-server.crt',
						'ssl_key_password' => '/home/test/certdb/postgresql-server.key',
						'timeout' => '10s',
						'lifetime_type' => ZBX_LLD_DELETE_AFTER,
						'lifetime' => '15d',
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_NEVER,
						'allow_traps' => HTTPCHECK_ALLOW_TRAPS_ON,
						'trapper_hosts' => '127.0.2.3',
						'description' => 'LLD for test',
						'preprocessing' => [['type' => ZBX_PREPROC_STR_REPLACE, 'params' => "a\nb"]],
						'lld_macro_paths' => ['lld_macro' => '{#MACRO}', 'path' => '$.path'],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								[
									'macro' => '{#MACRO}',
									'value' => 'expression',
									'operator' => CONDITION_OPERATOR_NOT_REGEXP
								]
							]
						],
						'overrides' => [
							'name' => 'Override',
							'step' => 1,
							'stop' => ZBX_LLD_OVERRIDE_STOP_YES,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'formula' => '',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'value' => '',
										'operator' => CONDITION_OPERATOR_EXISTS
									]
								]
							],
							'operations' => [
								'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => 'test',
								'opstatus' => ['status' => ZBX_PROTOTYPE_DISCOVER]
							]
						]
					],
					[
						'name' => 'LLD for cancel scenario',
						'key_' => 'ssh.run[test]',
						'type' => ITEM_TYPE_SSH,
						'delay' => "3h;20s/1-3,00:02-14:30",
						'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
						'username' => 'username',
						'password' => 'passphrase',
						'publickey' => '/home/test/public-server.key',
						'privatekey' => '/home/test/private-server.key',
						'params' => 'test script',
						'timeout' => '',
						'lifetime_type' => ZBX_LLD_DELETE_NEVER,
						'enabled_lifetime_type' => ZBX_LLD_DISABLE_AFTER,
						'enabled_lifetime' => '20h',
						'description' => 'Description for cancel scenario',
						'preprocessing' => [['type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE, 'params' => '30s']],
						'lld_macro_paths' => ['lld_macro' => '{#LLDMACRO}', 'path' => '$.path.to.node'],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								[
									'macro' => '{#FILTERMACRO}',
									'operator' => CONDITION_OPERATOR_NOT_EXISTS
								]
							]
						],
						'overrides' => [
							'name' => 'Cancel override',
							'step' => 1,
							'stop' => ZBX_LLD_OVERRIDE_STOP_YES,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'formula' => '',
								'conditions' => [
									[
										'macro' => '{#OVERRIDE_MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => 'expression'
									]
								]
							],
							'operations' => [
								'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
								'operator' => CONDITION_OPERATOR_NOT_EQUAL,
								'value' => 'test',
								'opstatus' => ['status' => ZBX_PROTOTYPE_DISCOVER],
								'opseverity' => ['severity' => TRIGGER_SEVERITY_HIGH]
							]
						]
					]
				]
			]
		]);

		self::$hostid = $result['hostids']['Host for LLD form test'];
		self::$interfaces_hostid = $result['hostids']['Host for LLD form test with all interfaces'];
	}

	public function testFormLowLevelDiscovery_Layout() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host');
		$this->query('button:Create discovery rule')->one()->waitUntilClickable()->click();
		$form = $this->query('id:host-discovery-form')->asForm()->one()->waitUntilVisible();
		$this->page->assertHeader('Discovery rules');
		$this->page->assertTitle('Configuration of discovery rules');
		$this->assertEquals(['Discovery rule', 'Preprocessing', 'LLD macros', 'Filters', 'Overrides'], $form->getTabs());

		// Check form footer buttons clickability.
		foreach (['xpath://div[@class="form-actions"]/button[@id="add"]', 'button:Test', 'button:Cancel'] as $query) {
			$this->assertTrue($form->query($query)->one()->isClickable());
		}

		// Check the whole form required labels.
		$required_labels = ['Name', 'Key', 'URL', 'Script', 'Master item', 'Host interface', 'SNMP OID', 'JMX endpoint',
			'Public key file', 'Private key file', 'Executed script', 'SQL query', 'Update interval', 'Timeout',
			'Delete lost resources', 'Disable lost resources'
		];
		$this->assertEquals($required_labels, array_values($form->getLabels(CElementFilter::CLASSES_PRESENT,
				'form-label-asterisk')->asText())
		);

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
		foreach ($visible_fields as $tab => $fields) {
			$form->selectTab($tab);
			$this->assertEquals($fields, array_values($form->getLabels()->filter(CElementFilter::VISIBLE)->asText()));

			// Check buttons default values and parameters in fields in every tab.
			switch ($tab) {
				case 'Preprocessing':
					$preprocessing_container = $form->getFieldContainer('Preprocessing steps');
					$preprocessing_container->query('button:Add')->one()->waitUntilCLickable()->click();
					$this->assertTrue($preprocessing_container->query('id:preprocessing')->one()->isVisible());
					$this->assertTrue($preprocessing_container->query('button', ['Add', 'Test', 'Remove'])->one()->isClickable());

					$preprocessing_fields = [
						'id:preprocessing_0_type' => ['value' => 'Regular expression'],
						'id:preprocessing_0_params_0' => ['value' => '', 'placeholder' => 'pattern', 'maxlength' => 255],
						'id:preprocessing_0_params_1' => ['value' => '', 'placeholder' => 'output', 'maxlength' => 255],
						'id:preprocessing_0_on_fail' => ['value' => false]
					];
					$this->checkFieldsParameters($preprocessing_fields);

					foreach (array_keys($preprocessing_fields) as $key) {
						$this->assertTrue($form->getField($key)->isEnabled());
					}
					break;

				case 'LLD macros':
					$macros_table = $form->query('id:lld_macro_paths')->asTable()->one();
					$this->assertTrue($macros_table->isVisible());
					$this->assertTrue($macros_table->query('button', ['Add', 'Remove'])->one()->isClickable());
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
					$filters_field = $form->getFieldContainer('Filters');
					$this->assertTrue($form->query('id:conditions')->one()->isVisible());
					$this->assertTrue($filters_field->query('button', ['Add', 'Remove'])->one()->isClickable());
					$this->assertEquals(['Label', 'Macro', '', 'Regular expression', 'Action'],
							$filters_field->query('id:conditions')->asTable()->one()->getHeadersText()
					);

					$filter_fields = [
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
					break;

				case 'Overrides':
					$filters_container = $form->getFieldContainer('Overrides');
					$this->assertTrue($filters_container->query('button:Add')->one()->isClickable());
					$this->assertEquals(['', '', 'Name', 'Stop processing', 'Action'],
							$filters_container->query('id:lld-overrides-table')->asTable()->one()->getHeadersText()
					);
					break;
			}
		}

		// Check default fields' values.
		$fields = [
			// Discovery rule.
			'Name' => ['maxlength' => 255],
			'Type' => ['value' => 'Zabbix agent'],
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
			'Host interface' => ['value' => '127.0.0.1:10050'],
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
			'id:custom_timeout' => ['labels' => ['Global', 'Override'], 'value' => 'Global'],
			'Delete lost resources' => ['value' => '7d', 'maxlength' => 255],
			'Disable lost resources' => ['value' => '1h', 'maxlength' => 255],
			'id:lifetime_type' => ['labels' => ['Never', 'Immediately', 'After'], 'value' => 'After'],
			'id:enabled_lifetime_type' => ['labels' => ['Never', 'Immediately', 'After'], 'value' => 'Immediately'],
			'Enable trapping' => ['value' => false],
			'id:trapper_hosts' => ['maxlength' => 255],
			'Description' => ['value' => ''],
			'Enabled' => ['value' => true],

			// Preprocessing tab.
			'Preprocessing steps' => ['value' => NULL],

			// LLD macros tab.
			'LLD macros' => ['value' => [['lld_macro' => '', 'path' => '']]],
			'id:lld_macro_paths_0_lld_macro' => ['placeholder' => '{#MACRO}', 'maxlength' => 255],
			'id:lld_macro_paths_0_path' => ['placeholder' => '$.path.to.node', 'maxlength' => 255],

			// Filters tab.
			'Filters' => ['value' => [['macro' => '', 'operator' => 'matches']]],
			'id:conditions_0_macro' => ['placeholder' => '{#MACRO}', 'maxlength' => 64],
			'name:conditions[0][operator]' => ['options' => ['matches', 'does not match', 'exists', 'does not exist'],
					'value' => 'matches'
			],
			'id:conditions_0_value' => ['placeholder' => 'regular expression', 'maxlength' => 255],

			// Overrides tab.
			'Overrides' => ['value' => []]
		];

		$this->checkFieldsParameters($fields);

		// Check visible fields depending on LLD type.
		$permanent_fields = ['Name', 'Type', 'Key', 'Delete lost resources', 'Disable lost resources', 'Description', 'Enabled'];

		$depending_fields = [
			'Zabbix agent' => ['Host interface', 'Update interval', 'Custom intervals', 'Timeout'],
			'Zabbix agent (active)' => ['Update interval', 'Custom intervals', 'Timeout'],
			'Simple check' => ['Host interface', 'User name', 'Password', 'Update interval', 'Custom intervals', 'Timeout'],
			'SNMP agent' => ['Host interface', 'SNMP OID', 'Update interval', 'Custom intervals'],
			'Zabbix internal' => ['Update interval', 'Custom intervals'],
			'Zabbix trapper' => ['Allowed hosts'],
			'External check' => ['Host interface', 'Update interval', 'Custom intervals', 'Timeout'],
			'Database monitor' => ['User name', 'Password', 'SQL query', 'Update interval', 'Custom intervals', 'Timeout'],
			'HTTP agent' => ['URL', 'Query fields', 'Request type', 'Request body type', 'Request body', 'Headers',
				'Required status codes', 'Follow redirects', 'Retrieve mode', 'HTTP proxy', 'HTTP authentication',
				'SSL verify peer', 'SSL verify host', 'SSL certificate file', 'SSL key file', 'SSL key password',
				'Host interface', 'Update interval', 'Custom intervals', 'Timeout', 'Enable trapping'
			],
			'IPMI agent' => ['Host interface', 'IPMI sensor', 'Update interval', 'Custom intervals'],
			'SSH agent' => ['Host interface', 'Authentication method', 'User name', 'Password',
				'Executed script', 'Update interval', 'Custom intervals', 'Timeout'
			],
			'TELNET agent' => ['Host interface', 'User name', 'Password', 'Executed script',
				'Update interval', 'Custom intervals', 'Timeout'
			],
			'JMX agent' => ['Host interface', 'JMX endpoint', 'User name', 'Password', 'Update interval', 'Custom intervals'],
			'Dependent item' => ['Master item'],
			'Script' => ['Parameters', 'Script', 'Update interval', 'Custom intervals', 'Timeout']
		];

		$hints = [
			'SNMP OID' => "Field requirements:".
					"\nwalk[OID1,OID2,...] - to retrieve a subtree".
					"\ndiscovery[{#MACRO1},OID1,{#MACRO2},OID2,...] - (legacy) to retrieve a subtree in JSON",
			'Delete lost resources' => 'The value should be greater than LLD rule update interval.',
			'Disable lost resources' => 'The value should be greater than LLD rule update interval.'
		];

		foreach ($depending_fields as $type => $fields) {
			$form->fill(['Type' => $type]);

			// Get expected visible fields.
			$form_fields = array_merge($permanent_fields, array_values($fields));
			usort($form_fields, function ($a, $b) {
				return strcasecmp($a, $b);
			});

			// Get actual visible fields.
			$present_fields = $form->getLabels()->filter(CElementFilter::VISIBLE)->asText();
			usort($present_fields, function ($a, $b) {
				return strcasecmp($a, $b);
			});

			$this->assertEquals($form_fields, $present_fields);

			switch ($type) {
				case 'SNMP agent':
					// Check hints and texts.
					foreach ($hints as $label => $hint_text) {
						$form->getLabel($label)->query('xpath:./button[@data-hintbox]')->one()->click();
						$hint = $this->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent()->all()->last();
						$this->assertEquals($hint_text, $hint->getText());
						$hint->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();
					}

				case 'JMX agent':
				case 'IPMI agent':
					// Check red interface info message.
					$this->assertTrue($form->query('xpath:.//span[@class="red" and text()="No interface found"]')->one()
							->isVisible()
					);
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
						'None' => ['enabled' => true, 'visible' => false],
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
						'Global' => ['enabled' => false, 'visible' => true],
						'Override' => ['enabled' => true, 'visible' => true],
					];
					foreach ($timeout_array as $timeout => $status) {
						$this->checkFieldsDependency($form, ['id:custom_timeout' => $timeout], ['id:timeout' => $status]);
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
						// Pencil icon button.
						'Script' => 'xpath:.//button[@title="Click to view or edit"]'
					];

					foreach ($buttons as $label => $query) {
						$this->assertTrue($form->getFieldContainer($label)->query($query)->one()->isClickable());
					}
					break;
			}
		}
	}

	public function testFormLowLevelDiscovery_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::SQL);
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host');
		$this->query('link:LLD for simple update scenario')->one()->waitUntilClickable()->click();
		$this->query('button:Update')->waitUntilClickable()->one()->click();
		$this->assertMessage(TEST_GOOD, 'Discovery rule updated');
		$this->page->assertTitle('Configuration of discovery rules');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public static function getLLDData() {
		return [
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
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => 'qd1-8h9-18'
					],
					'error_details' => 'Invalid interval "qd1-8h9-18".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Scheduling interval validation',
						'Type' => 'Zabbix agent',
						'Key' => 'test1',
						'id:delay_flex_0_type' => 'Scheduling',
						'id:delay_flex_0_schedule' => 'qd1-5h9-25'
					],
					'error_details' => 'Invalid interval "qd1-5h9-25".'
				]
			],
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
						'id:enabled_lifetime' => '1d',
					],
					'error_details' => 'Incorrect value for field "Disable lost resources":'.
							' cannot be greater than or equal to the value of field "Delete lost resources".'
				]
			],
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










			[
				[
					'fields' => [
						'Name' => 'Simple LLD',
						'Type' => 'Zabbix agent',
						'Key' => 'agent'
					]
				]
			],
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
			[
				[
					'fields' => [
						'Name' => 'SNMP LLD 1',
						'Type' => 'SNMP agent',
						'Key' => 'snmp.test',
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
			[
				[
					'fields' => [
						'Name' => 'SNMP LLD 2',
						'Type' => 'SNMP agent',
						'Key' => 'snmp.test[1]',
						'SNMP OID' => 'walk[.1.3.6.1.4.1,.1.3.6.1.2.1.1.1.0,.1.3.6.1.2.1.7.3.0]',
						'id:lifetime_type' => 'After',
						'id:lifetime' => 788400000,
						'id:enabled_lifetime_type' => 'After',
						'id:enabled_lifetime' => 788399999
					]
				]
			],
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
			[
				[
					'fields' => [
						'Name' => 'Internal',
						'Type' => 'Zabbix internal',
						'Key' => 'zabbix[triggers]'
					]
				]
			],
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
			[
				[
					'fields' => [
						'Name' => 'External',
						'Type' => 'External check',
						'Key' => 'zabbix[external]'
					]
				]
			],
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
			[
				[
					'fields' => [
						'Name' => 'HTTP JSON',
						'Type' => 'HTTP agent',
						'Key' => 'http_check[2]',
						'URL' => 'www.test.com/search',
						'Request body type' => 'JSON data',
						'Request body' => '{"export": {"version": "6.0","date": "2024-03-20T20:05:14Z"}}',
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'HTTP XML',
						'Type' => 'HTTP agent',
						'Key' => 'http_check[3]',
						'URL' => 'www.test.com/search',
						'Request body type' => 'XML data',
						'Request body' => '<export><version>6.0</version><date>2024-03-20T20:05:14Z</date></export>',
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'HTTP LLD',
						'Type' => 'HTTP agent',
						'Key' => 'http_check[]',
						'URL' => 'https://www.test.com/search?q=cat&rlz=1C1GCEU_enLV1043LV1043',
						'Required status codes' => '200,200-{$M},{$M},200-400',
						'HTTP authentication' => 'NTLM',
					],
					'parse' => true,
					'parsed' => [
						'url' => 'https://www.test.com/search',
						'fields' =>[
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
//			[
//				[
//					'fields' => [
//						'Name' => 'Multiline Script LLD',
//						'Type' => 'Script',
//						'Key' => 'script_check[2]',
//						'Script' => "const = 'Hello World!';".
//								"\nlet favePhrase = const;".
//								"\nconsole.log(favePhrase);"
//					],
//					'Parameters' => [
//						['Name' => 'param_1', 'Value' => 'value_1'],
//						['Name' => 'param_2', 'Value' => 'value_2']
//					]
//				]
//			]
		];
	}

	/**
	 * @dataProvider getLLDData
	 */
	public function testFormLowLevelDiscovery_1Create($data) {
		$this->checkLowLevelDiscoveryForm($data);
	}

	/**
	 * @dataProvider getLLDData
	 */
	public function testFormLowLevelDiscovery_1Update($data) {
		$this->checkLowLevelDiscoveryForm($data, true);
	}

	public function checkLowLevelDiscoveryForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		// Make name and key unique for every case.
		if ($update && $data['fields']['Name'] !== '') {
			$data['fields']['Name'] = trim($data['fields']['Name']).'_updated';
			$data['fields']['Key'] = 'upd.'.$data['fields']['Key'];
		}

		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.
				self::$interfaces_hostid.'&context=host');
		$this->query($update ? 'link:'.self::$update_lld : 'button:Create discovery rule')->one()
				->waitUntilClickable()->click();
		$form = $this->query('id:host-discovery-form')->asForm()->one()->waitUntilVisible();
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

		// Fill headers.
		if (array_key_exists('Headers', $data)) {
			$this->fillDraggableFields($data['Headers'], $form, 'Headers');
		}

		// Fill parameters.
		if (array_key_exists('Parameters', $data)) {
			$this->fillDraggableFields($data['Parameters'], $form, 'Parameters');
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

			$this->assertEquals(1, CDBHelper::getCount(
				'SELECT * FROM items'.
					' WHERE name='.zbx_dbstr($data['fields']['Name']).
						'AND flags=1'
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
			if (CTestArrayHelper::get($data, 'checked_interface', false)) {
				$data['fields']['Host interface'] = $data['checked_interface'];
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

			// Check headers.
			if (array_key_exists('Headers', $data)) {
				$this->checkDraggableFields($data['Headers'], $form, 'headers');
			}

			// Check headers.
			if (array_key_exists('Parameters', $data)) {
				$this->checkDraggableFields($data['Parameters'], $form, 'parameters');
			}

			// Write new LLD name for the next case.
			if ($update) {
				self::$update_lld = $data['fields']['Name'];
			}
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
	 * @dataProvider getCancelData
	 */
	public function testFormLowLevelDiscovery_Cancel($data) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$lld_name = 'LLD for cancel scenario';
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host');

		if ($data['action'] === 'Add') {
			$this->query('button:Create discovery rule')->one()->waitUntilClickable()->click();
		}
		else {
			$this->query('link',$lld_name)->one()->waitUntilClickable()->click();

			if ($data['action'] === 'Clone') {
				$this->query('button:Clone')->one()->waitUntilClickable()->click();
			}

			if ($data['action'] === 'Delete') {
				$this->query('button:Delete')->waitUntilClickable()->one()->click();
				$this->assertTrue($this->page->isAlertPresent());
				$this->assertEquals('Delete discovery rule?', $this->page->getAlertText());
				$this->page->dismissAlert();
			}
		}

		if ($data['action'] !== 'Delete') {
			$form = $this->query('id:host-discovery-form')->asForm()->one()->waitUntilVisible();

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
					'id:preprocessing_0_type' => 'Prometheus to JSON',
					'id:preprocessing_0_params_0' => '{label_name!~"name"}'
				],
				'lld_macros_fields' => [
					'id:lld_macro_paths_0_lld_macro' => '{#NEW_LLDMACRO}',
					'id:lld_macro_paths_0_path' => '$.new.path.to.node',
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

			$form->fill($fields['preprocessing_fields']);

			// Change LLD Macros.
			$form->selectTab('LLD macros');
			$form->fill($fields['lld_macros_fields']);

			// Change Filters.
			$form->selectTab('Filters');
			$form->fill($fields['filters_fields']);

			// Change Filters.
			$form->selectTab('Overrides');

			if ($data['action'] === 'Add') {
				$form->getFieldContainer('Overrides')->query('button:Add')->waitUntilClickable()->one()->click();
			}
			else {
				$form->query('link:Cancel override')->waitUntilClickable()->one()->click();
			}

			$overlay_dialog = COverlayDialogElement::find()->all()->last()->asForm()->waitUntilReady();
			$overlay_dialog->fill($fields['overrides_fields']['filters']);
			$overlay_dialog->getFieldContainer('Operations')->query('button:Add')->waitUntilClickable()->one()->click();
			$operation_dialog = COverlayDialogElement::find()->all()->last()->asForm()->waitUntilReady();
			$operation_dialog->fill($fields['overrides_fields']['operations']);
			$operation_dialog->submit();
			$operation_dialog->waitUntilNotVisible();
			$overlay_dialog->submit();
			$operation_dialog->waitUntilNotVisible();
		}

		$this->query('button:Cancel')->waitUntilClickable()->one()->click();
		$this->page->assertTitle('Configuration of discovery rules');
		$this->assertTrue($this->query('link', $lld_name)->one()->isVisible());
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public function testFormLowLevelDiscovery_Delete() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host');

		$lld_name = 'LLD for delete scenario';
		$this->query('link', $lld_name)->one()->waitUntilClickable()->click();
		$this->query('button:Delete')->waitUntilClickable()->one()->click();

		// Check alert.
		$this->assertTrue($this->page->isAlertPresent());
		$this->assertEquals('Delete discovery rule?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Discovery rule deleted');

		// Check DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM hosts WHERE host='.zbx_dbstr($lld_name)));

		// Check frontend table.
		$this->assertFalse($this->query('link', $lld_name)->exists());

		// Check that user redirected on Proxies page.
		$this->page->assertTitle('Configuration of discovery rules');
	}

	/**
	 * Check inputs are visible/editable depending on other field's value.
	 *
	 * @param array $fields_array    given fields
	 */
	protected function checkFieldsParameters($fields_array) {
		$form = $this->query('id:host-discovery-form')->asForm()->one()->waitUntilVisible();

		foreach ($fields_array as $id => $parameters) {
			$this->assertEquals(CTestArrayHelper::get($parameters, 'value', ''),
					$form->getField($id)->getValue()
			);

			$this->assertEquals(CTestArrayHelper::get($parameters, 'placeholder', null),
					$form->getField($id)->getAttribute('placeholder')
			);

			$this->assertEquals(CTestArrayHelper::get($parameters, 'maxlength', null),
					$form->getField($id)->getAttribute('maxlength')
			);

			if (array_key_exists('options', $parameters)){
				$this->assertEquals($parameters['options'], $form->getField($id)->getOptions()->asText());
			}

			if (array_key_exists('labels', $parameters)){
				$this->assertEquals($parameters['labels'], $form->getField($id)->getLabels()->asText());
			}
		}
	}

	/**
	 * Check inputs are visible/editable depending on other field's value.
	 *
	 * @param CFormElement $form                  LLD edit form
	 * @param string       $master_field_value    input which is being changed
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
	 * Fill complex draggable fields in the view like name => value.
	 *
	 * @param array        $data     given array of fields
	 * @param CFormElement $form     LLD edit form
	 * @param string       $label    container's label
	 */
	protected function fillDraggableFields($data, $form, $label) {
		foreach ($data as $i => $field) {
			if ($i > 0) {
				$form->getFieldContainer($label)->query('button:Add')->waitUntilClickable()->one()->click();
			}

			$form->fill([
				'name:'.lcfirst($label).'['.$i.'][name]' => $field['Name'],
				'name:'.lcfirst($label).'['.$i.'][value]' => $field['Value']
			]);
		}
	}

	/**
	 * Check complex draggable fields.
	 *
	 * @param array        $data       given array of fields
	 * @param CFormElement $form       LLD edit form
	 * @param string       $locator    field's locator
	 */
	protected function checkDraggableFields($data, $form, $locator) {
		foreach ($data as $i => $field) {
			$form->checkValue([
				'name:'.$locator.'['.$i.'][name]' => $field['Name'],
				'name:'.$locator.'['.$i.'][value]' => $field['Value']
			]);
		}
	}
}

