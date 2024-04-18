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

/**
 * @backup hosts
 *
 * @onBefore prepareLLDData
 */
class testFormLowLevelDiscovery extends CWebTest {

	protected static $hostid;

	public function prepareLLDData() {
		$result = CDataHelper::createHosts([
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
						'name' => 'LLD for update test',
						'key_' => 'vfs.fs.discovery',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 30
					]
				]
			]
		]);

		self::$hostid = $result['hostids']['Host for LLD form test'];
	}

	public function testFormLowLevelDiscovery_Layout() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid.'&context=host');
		$this->query('button:Create discovery rule')->one()->waitUntilClickable()->click();

		$form = $this->query('id:host-discovery-form')->asForm()->one()->waitUntilVisible();

		$this->page->assertHeader('Discovery rules');
		$this->page->assertTitle('Configuration of discovery rules');

		$this->assertEquals(['Discovery rule', 'Preprocessing', 'LLD macros', 'Filters', 'Overrides'], $form->getTabs());

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
			'Request type' => ['options' => ['GET', 'POST', 'PUT', 'HEAD', 'value' => 'GET'], 'value' => 'GET'],
			'Request body type' => ['labels' => ['Raw data', 'JSON data', 'XML data'], 'value' => 'Raw data'],
			'Request body' => ['value' => ''],
			'Headers' => ['value' => [['name' => '', 'value' => '']]],
			'name:headers[0][name]' => ['maxlength' => 255, 'placeholder' => 'name'],
			'name:headers[0][value]' => ['maxlength' => 2000, 'placeholder' => 'value'],
			'Required status codes' => ['value' => 200, 'maxlength' => 255],
			'Follow redirects' => ['value' => true],
			'Retrieve mode' => ['labels' => ['Body', 'Headers', 'Body and heraders'], 'value' => 'Body'],
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
			'id:jmx_endpoint' => ['value'=> 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi', 'maxlength' => 255],
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

			// Preprocessing.
			'Preprocessing steps' => ['value' => NULL],

			// LLD macros.
			'LLD macros' => ['value' => [['lld_macro' => '', 'path' => '']]],
			'id:lld_macro_paths_0_lld_macro' => ['placeholder' => '{#MACRO}', 'maxlength' => 255],
			'id:lld_macro_paths_0_path' => ['placeholder' => '$.path.to.node', 'maxlength' => 255],

			// Filters.
			'Filters' => ['value' => [['macro' => '', 'operator' => 'matches']]],
			'id:conditions_0_macro' => ['placeholder' => '{#MACRO}', 'maxlength' => 64],
			'name:conditions[0][operator]' => ['options' => ['matches', 'does ot match', 'exists', 'does not exist'],
					'value' => 'matches'
			],
			'id:conditions_0_value' => ['placeholder' => 'regular expression', 'maxlength' => 255],

			// Overrides.
			'Overrides' => ['value' => []]
		];

		foreach ( $fields as $field => $parameters) {
			$this->assertEquals(CTestArrayHelper::get($parameters, 'value', ''),
					$form->getField($field)->getValue()
			);

			$this->assertEquals(CTestArrayHelper::get($parameters, 'placeholder', null),
					$form->getField($field)->getAttribute('placeholder')
			);

			$this->assertEquals(CTestArrayHelper::get($parameters, 'maxlength', null),
					$form->getField($field)->getAttribute('maxlength')
			);
		}

		foreach (['xpath:.//div[@class="form-actions"]/button[@id="add"]', 'button:Test', 'button:Cancel'] as $query) {
			$this->assertTrue($form->query($query)->one()->isClickable());
		}

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
			usort($form_fields, function($a, $b) {
				return strcasecmp($a, $b);
			});

			// Get actual visible fields.
			$present_fields = $form->getLabels()->filter(CElementFilter::VISIBLE)->asText();
			usort($present_fields, function($a, $b) {
				return strcasecmp($a, $b);
			});

			$this->assertEquals($form_fields, $present_fields);

			switch($type) {
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
					$timeout_array =[
						'Global' => ['enabled' => false, 'visible' => true],
						'Override' => ['enabled' => true, 'visible' => true],
					];
					foreach ($timeout_array as $timeout => $status) {
						$this->checkFieldsDependency($form, ['id:custom_timeout' => $timeout], ['id:timeout' => $status]);
					}

					$lifetime_array = [
						'Never' => ['enabled' => true, 'visible' => false],
						'Immediately' => ['enabled' => true, 'visible' => false],
						'After' => ['enabled' => true, 'visible' => true]
					];

					// Check Timeouts link.
					$this->assertTrue($form->query('link:Timeouts')->one()->isClickable());

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
						'Script' => 'xpath:.//button[@title="Click to view or edit"]'
					];

					foreach ($buttons as $label => $query) {
						$this->assertTrue($form->getFieldContainer($label)->query($query)->one()->isClickable());
					}
					break;
			}
		}
	}

	/**
	 * Check inputs are visible/editable depending on other field's value.
	 *
	 * @param CFormElement    $form                  LLD edit form
	 * @param string          $master_field_value    input which is being checked
	 * @param array           $dependant_array       given array of labels
	 */
	protected function checkFieldsDependency($form, $master_field_value, $dependant_array) {
		$form->fill($master_field_value);

		foreach ($dependant_array as $label => $status) {
			$dependant_field = $form->getField($label);
			$this->assertTrue($dependant_field->isVisible($status['visible']));
			$this->assertTrue($dependant_field->isEnabled($status['enabled']));
		}
	}
}

