<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * Test the mass update of items.
 *
 * @backup items
 */
class testPageMassUpdateItems extends CWebTest{

	const HOSTID = 40001;	// Simple form test host.

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
	 * Add items for mass updating.
	 */
	public function prepareItemData() {
		CDataHelper::call('item.create', [
			[
				'hostid' => self::HOSTID,
				'name' => '1_Item',
				'key_' => '1agent',
				'type' => 0,
				'value_type' => 0,
				'interfaceid'=> '40011',
				'delay' => '1m',
				'preprocessing' => [
					[
						'type' => '5',
						'params' => "regular expression pattern \noutput template",
						'error_handler' => 0,
						'error_handler_params' => ''
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item',
				'key_' => '2agent',
				'type' => 0,
				'value_type' => 1,
				'interfaceid'=> '40011',
				'delay' => '2m',
				'preprocessing' => [
					[
						'type' => '1',
						'params' => "2",
						'error_handler' => 0,
						'error_handler_params' => ''
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '3_SNMP_trap',
				'key_' => 'snmptrap.fallback',
				'type' => 17,
				'value_type' => 0,
				'interfaceid'=> '40012',
				'delay' => '3m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '4_SNMP_trap',
				'key_' => 'snmptrap[regexp]',
				'type' => 17 ,
				'value_type' => 1,
				'interfaceid'=> '40012',
				'delay' => '4m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '5_Aggregate',
				'key_' => 'grpavg["host group","key",avg,last]',
				'type' => 8,
				'value_type' => 0,
				'interfaceid'=> '40012',
				'delay' => '9m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '6_Aggregate',
				'key_' => 'grpmin["host group","key",avg,min]',
				'type' => 8 ,
				'value_type' => 3,
				'interfaceid'=> '40012',
				'delay' => '30s'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '7_IPMI',
				'key_' => 'ipmi1',
				'type' => 12,
				'value_type' => 0,
				'interfaceid'=> '40013',
				'delay' => '10m',
				'ipmi_sensor' => 'temp'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '8_IPMI',
				'key_' => 'gipmi2',
				'type' => 12 ,
				'value_type' => 3,
				'interfaceid'=> '40013',
				'delay' => '11s',
				'ipmi_sensor' => 'temp'
			]
		]);
	}

	public function getItemChangeData() {
		return [
			[
				[
					'names'=> [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent (active)'],
						'Type of information'=> ['id' => 'value_type', 'value' => 'Numeric (float)'],
						'Units'=> ['id' => 'units', 'value' => '$'],
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
						'Show value' => ['id' => 'valuemapid', 'value' => 'TruthValue'],
						'Applications' => [
							'action' => 'Add',
							'application' => 'New application'
						],
						'Description' => ['id' => 'description', 'value' => 'New mass updated description'],
						'Status' => ['id' => 'status', 'value' => 'Disabled']
					]
				]
			],
			[
				[
					'names'=> [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Simple check'],
						'Type of information'=> ['id' => 'value_type', 'value' => 'Log'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.2 : 10052'],
						'User name' => ['id' => 'username', 'value' => 'test_username'],
						'Password' => ['id' => 'password', 'value' => 'test_password'],
						'Log time format' => ['id' => 'logtimefmt', 'value' => 'PPPPPP:YYYYMMDD:HHMMSS.mmm']
					]
				]
			],
			[
				[
					'names'=> [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix internal'],
						'Type of information'=> ['id' => 'value_type', 'value' => 'Text'],
						'Update interval' => [
							'Delay' => '90s',
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
						],
//						'Applications' => [
//							'action' => 'Replace',
//							'applications' => ['1', '2']
//						],
//						'expected_applications' => []
					]
				]
			],
			[
				[
					'names'=> [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix trapper'],
						'Type of information'=> ['id' => 'value_type', 'value' => 'Numeric (unsigned)'],
						'Show value' => ['id' => 'valuemapid', 'value' => 'Alarm state'],
						'Allowed hosts' => ['id' => 'trapper_hosts', 'value' => '127.0.0.1'],  // Validation - "zabbix host"
					]
				]
			],
			[
				[
					'names'=> [
						'5_Aggregate',
						'6_Aggregate'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix aggregate'],  // validate key
						'Type of information'=> ['id' => 'value_type', 'value' => 'Numeric (float)'],
						'Units' => ['id' => 'units', 'value' => 'kB'],
						'Update interval' => ['Delay' => '55s'] // Validate delay
					]
				]
			],
			[
				[
					'names'=> [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'External check'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1 : 10051'],
						'Update interval' => ['Delay' => '200s'] // Validate delay
					]
				]
			],
			[
				[
					'names'=> [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'HTTP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1 : 10051'],
						'URL'=> ['id' => 'url', 'value' => 'https//:zabbix.com'],
						'Request body type' => ['id' => 'post_type', 'value' => 'JSON data'],
						'Request body' => ['id' => 'posts', 'value' => '{"request": "active checks", "host": "host"}'],  // Validate JSON
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
						]
					]
				]
			],
			[
				[
					'names'=> [
						'7_IPMI',
						'8_IPMI'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'IPMI agent'],
						'Type of information'=> ['id' => 'value_type', 'value' => 'Numeric (float)'],
						'Units' => ['id' => 'units', 'value' => 'kB'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.3 : 10053'], // Add interface to host
					]
				]
			],
			[
				[
					'names'=> [
						'3_SNMP_trap',
						'4_SNMP_trap'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SNMP trap'], // Validate key
						'Type of information'=> ['id' => 'value_type', 'value' => 'Character'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.2 : 10052'], // Add interface to host
						'History storage period' => [
							'radio' => ['id' => 'history_mode', 'value' => 'Do not keep history']
						],
						'Trend storage period' => [
							'radio' => ['id' => 'trends_mode', 'value' => 'Storage period'],
							'input' => ['id' => 'trends', 'value' => '99d']
						],
						'Status' => ['id' => 'status', 'value' => 'Enabled'],
//						'Applications' => [
//							'action' => 'Remove',
//							'applications' => ['1']
//						],
//						'expected_applications' => []
					]
				]
			]
		];
	}

	/**
	 * @on-before-once prepareItemData
	 *
	 * @dataProvider getItemChangeData
	 */
	public function testPageMassUpdateItems_ChangeItems($data) {
		$this->page->login()->open('items.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID);

		// Get item table.
		$table = $this->query('xpath://form[@name="items"]/table[@class="list-table"]')->asTable()->one();
		foreach ($data['names'] as $name) {
			$table->findRow('Name', $name)->select();
		}

		// Open mass update form.
		$this->query('button:Mass update')->one()->click();
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();

		// Set field value.
		foreach ($data['change'] as $field => $value) {
			// Click on a label to show input control.
			$form->getLabel($field)->click();
			// Set field value.
			switch ($field) {
				case 'Type':
				case 'Host interface':
				case 'Type of information':
				case 'Status':
				case 'Show value':
					$form->query('id', $value['id'])->asZDropdown()->one()->select($value['value']);
					break;

				case 'Units':
				case 'Description':
				case 'User name':
				case 'Password':
				case 'User name':
				case 'Password':
				case 'Log time format':
				case 'Allowed hosts':
				case 'Request body' :
				case 'URL':
					$form->query('id', $value['id'])->one()->fill($value['value']);
					break;

				case 'Request body type':
					$form->query('id', $value['id'])->one()->asSegmentedRadio()->fill($value['value']);
					break;

				case 'Update interval':
					$container_table = $form->query('id:update_interval')->asTable()->one();
					$container_table->getRow(0)->getColumn(1)->query('id:delay')->one()->fill($value['Delay']);

					if(array_key_exists('Custom intervals', $value)){
						$container_table->getRow(1)->getColumn(1)->query('id:custom_intervals')->asMultifieldTable(
								['mapping' => self::INTERVAL_MAPPING])->one()->fill($value['Custom intervals']);

					}
					break;

				case 'History storage period':
				case 'Trend storage period':
					$form->query('id', $value['radio']['id'])->one()->asSegmentedRadio()->fill($value['radio']['value']);
					if(array_key_exists('input', $value)){
						$form->query('id', $value['input']['id'])->one()->fill($value['input']['value']);
					}
					break;

				case 'Applications':
					$form->query('id:massupdate_app_action')->asSegmentedRadio()->one()->fill($value['action']);
					$form->query('xpath://*[@id="applications_"]/..')->asMultiselect()->one()->fill($value['application']);
					break;

				case 'Headers':
					$form->query('xpath:.//div[@id="headers_pairs"]/table')->asMultifieldTable()->one()->fill($value);
					break;

				case 'Security name':
				case 'Security level':
				case 'Authentication protocol':
				case 'Authentication passphrase':
				case 'Privacy protocol':
				case 'Privacy passphrase':
				case 'Type of information':

				case 'SNMP community':
				case 'JMX endpoint':

				case 'Authentication method':
				case 'Public key file':
				case 'Private key file':
			}
		}
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Items updated');

		// Check changed fields in saved item form.
		foreach ($data['names'] as $name) {
			$table->query('link', $name)->one()->waitUntilClickable()->click();
			$form->invalidate();

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
						$this->assertEquals($value['value'], $form->getField($field)->getValue());
						break;

					case 'Status':
						$status = ($value['value'] === 'Enabled') ? true : false;
						$this->assertEquals($status, $form->getField('Enabled')->getValue());
						break;

					case 'Update interval':
						$this->assertEquals($value['Delay'], $form->getField($field)->getValue());
						if(array_key_exists('Custom intervals', $value)){
							// Remove action and index fields.
							foreach ($value['Custom intervals'] as &$interval) {
								unset($interval['action'], $interval['index']);
							}
							unset($interval);

							$this->assertEquals($value['Custom intervals'], $form->query('id:delayFlexTable')
									->asMultifieldTable(['mapping' => self::INTERVAL_MAPPING])->one()->getValue());
						}
						break;

					case 'Headers':
						// Remove action and index fields.
						foreach ($value as &$header) {
							unset($header['action'], $header['index']);
						}
						unset($header);

						$this->assertEquals($value, $form->query('xpath:.//div[@id="headers_pairs"]/table')
								->asMultifieldTable()->one()->getValue());
				}
			}

			$form->query('button:Cancel')->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
		}
	}


	public function testPageMassUpdateItems_ChangePreprocessing($data) {

	}
}

