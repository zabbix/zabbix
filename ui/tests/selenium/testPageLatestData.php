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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

/**
 * @backup history_uint, profiles
 *
 * @onBefore prepareItemTagsData
 */
class testPageLatestData extends CWebTest {

	use TableTrait;

	private function getTableSelector() {
		return 'xpath://table['.CXPathHelper::fromClass('overflow-ellipsis').']';
	}

	private function getTable() {
		return $this->query($this->getTableSelector())->asTable()->one();
	}

	const HOSTNAME = 'Host for items tags filtering';

	// Host with items for filtering Latest data by item tags.
	protected static $data = [
		'hostgroupid' => null,
		'hostid' => null,
		'itemids' => [
			'tag_item_1',
			'tag_item_2',
			'tag_item_3',
			'tag_item_4'
		]
	];

	public function prepareItemTagsData() {
		// Create hostgroup for host with items and tags.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Items With tags']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		self::$data['hostgroupid'] = $hostgroups['groupids'][0];

		// Create host for items.
		$hosts = CDataHelper::call('host.create', [
			'host' => self::HOSTNAME,
			'groups' => [['groupid' => self::$data['hostgroupid']]]
		]);
		$this->assertArrayHasKey('hostids', $hosts);

		$hostids = CDataHelper::getIds('host');
		self::$data['hostid'] = $hostids['Host for items tags filtering'];

		// Create items on previously created host.
		$items_tags_data = [];
		foreach (self::$data['itemids'] as $i => $item) {
			$items_tags_data[] = [
				'hostid' => self::$data['hostid'],
				'name' => $item,
				'key_' => 'trapper'.$i,
				'type' => 2,
				'value_type' => 0,
				'tags' => [
					[
						'tag' => 'tag',
						'value' => 'filtering_value'
					],
					[
						'tag' => 'tag_number',
						'value' => strval($i)
					],
					[
						'tag' => 'component',
						'value' => 'name:'.$item
					]
				]
			];
		}

		$items = CDataHelper::call('item.create', $items_tags_data);

		self::$data['itemids']['tag_item_1'] = $items['itemids'][0];
		self::$data['itemids']['tag_item_2'] = $items['itemids'][1];
		self::$data['itemids']['tag_item_3'] = $items['itemids'][2];
		self::$data['itemids']['tag_item_4'] = $items['itemids'][3];

		// Add data to one item to see "With data"/"Without data" subfilter.
		$time = time() - 100;
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['tag_item_1']).
				", ".zbx_dbstr($time).", 1, 0)");
	}

	public function testPageLatestData_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$this->page->assertTitle('Latest data');
		$this->page->assertHeader('Latest data');
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$this->assertEquals(['Host groups', 'Hosts', 'Name', 'Tags', 'Show tags', 'Tag display priority', 'Show details'],
				$form->getLabels()->asText()
		);
		$this->assertTrue($this->query('button:Apply')->one()->isClickable());

		$subfilter = $this->query('id:latest-data-subfilter')->asTable()->one();
		$this->assertTrue($subfilter->query('xpath:.//h4[text()="Subfilter "]/span[@class="grey" and '.
				'text()="affects only filtered data"]')->one()->isValid()
		);

		foreach (['Hosts', 'Tags', 'Tag values'] as $header) {
			$this->assertTrue($subfilter->query("xpath:.//h3[text()=".CXPathHelper::escapeQuotes($header)."]")
					->one()->isValid()
			);
		}

		// With data/Without data subfilter shows only when some host is filtered.
		foreach ([false, true] as $status) {
			$this->assertEquals($status, $this->query('link:With data')->one(false)->isValid());
			$this->assertEquals($status, $this->query('link:Without data')->one(false)->isValid());

			if (!$status) {
				$form->fill(['Hosts' => self::HOSTNAME]);
				$form->submit();
				$this->page->waitUntilReady();
			}
			else {
				$this->assertTrue($subfilter->query('xpath:.//h3[text()="Data"]')->one()->isValid());
			}
		}

		$this->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		// Check table headers.
		$details_headers = [
			true => ['', 'Host', 'Name', 'Interval', 'History', 'Trends', 'Type', 'Last check', 'Last value',
				'Change', 'Tags', '', 'Info'],
			false => ['', 'Host', 'Name', 'Last check', 'Last value', 'Change', 'Tags', '', 'Info']
		];

		foreach ($details_headers as $status => $headers) {
			$this->query('name:show_details')->one()->asCheckbox()->set($status);
			$form->submit();
			$this->page->waitUntilReady();
			$this->assertEquals($headers, $this->getTable()->getHeadersText());
		}

		// Check that sortable headers are clickable.
		foreach (['Host', 'Name'] as $header) {
			$this->assertTrue($this->getTable()->query('xpath:.//th/a[text()="'.$header.'"]')->one()->isClickable());
		}

		// Check filter collapse/expand.
		$filter_tab = $this->query('xpath://a[contains(@class, "tabfilter-item-link")]')->one();
		foreach ([false, true] as $status) {
			$this->assertEquals($status, $this->query('xpath://div[contains(@class, "tabfilter-collapsed")]')
					->one(false)->isValid()
			);
			$filter_tab->click();
		}
	}

	public static function getFilterData() {
		return [
			// Host groups and Show details.
			[
				[
					'filter' => [
						'Host groups' => 'Another group to check Overview',
						'Show details' => true
					],
					'result' => [
						[
							'Name' => "4_item".
							"\ntrap[4]"
						]
					]
				]
			],
			// Hosts.
			[
				[
					'filter' => [
						'Hosts' => '1_Host_to_check_Monitoring_Overview'
					],
					'result' => [
						['Name' => '1_item'],
						['Name' => '2_item']
					]
				]
			],
			// Name.
			[
				[
					'filter' => [
						'Name' => '3_item'
					],
					'result' => [
						['Name' => '3_item']
					]
				]
			],
			// Evaluation: And/Or, Operator Exists
			[
				[
					'Tags' => [
						'Evaluation' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'DataBase',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						['Name' => '1_item'],
						['Name' => '2_item'],
						['Name' => '3_item'],
						['Name' => '4_item']
					]
				]
			],
			// Evaluation: Or, Operators: Equals, Contains.
			[
				[
					'Tags' => [
						'Evaluation' => 'Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'tag_number',
								'operator' => 'Contains',
								'value' => '0'
							],
							[
								'tag' => 'DataBase',
								'operator' => 'Equals',
								'value' => 'mysql'
							]
						]
					],
					'result' => [
						['Name' => '1_item'],
						['Name' => 'tag_item_1']
					]
				]
			],
			// The same tags as previous case, but Evaluation: And. Result: Empty table.
			[
				[
					'Tags' => [
						'Evaluation' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'tag_number',
								'operator' => 'Contains',
								'value' => '0'
							],
							[
								'tag' => 'DataBase',
								'operator' => 'Equals',
								'value' => 'mysql'
							]
						]
					],
					'result' => []
				]
			],
			// Operators: Does not contain, Does not equal, Exists.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'tag_number',
								'operator' => 'Does not equal',
								'value' => '1'
							],
							[
								'tag' => 'component',
								'operator' => 'Does not contain',
								'value' => '1'
							],
							[
								'tag' => 'tag',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						['Name' => 'tag_item_3'],
						['Name' => 'tag_item_4']
					]
				]
			],
			// Operator: Does not exist and Exists.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'tag',
								'operator' => 'Exists'
							],
							[
								'tag' => 'DataBase',
								'operator' => 'Does not exist'
							]
						]
					],
					'result' => [
						['Name' => 'tag_item_1'],
						['Name' => 'tag_item_2'],
						['Name' => 'tag_item_3'],
						['Name' => 'tag_item_4']
					]
				]
			],
			// Tags None.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Show tags' => 'None',
					'result' => [
						['Name' => 'tag_item_1']
					]
				]
			],
			// Tags: 1.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Show tags' => '1',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'component: name:tag_item_1']
					]
				]
			],
			// Tags: 2.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Show tags' => '2',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'component: name:tag_item_1tag: filtering_value']
					]
				]
			],
			// Tags: 3. Tag name: Full.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Show tags' => '3',
					'Tags name' => 'Full',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'component: name:tag_item_1tag: filtering_valuetag_number: 0']
					]
				]
			],
			// Tag name: Shortened.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Tags name' => 'Shortened',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'com: name:tag_item_1tag: filtering_valuetag: 0']
					]
				]
			],
			// Tag name: None.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Tags name' => 'None',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'name:tag_item_1filtering_value0']
					]
				]
			],
			// Tag priority: no such tags.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1',
						'Tag display priority' => 'tag_'
					],
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'component: name:tag_item_1tag: filtering_valuetag_number: 0']
					]
				]
			],
			// Tag priority: opposite alphabetic.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1',
						'Tag display priority' => 'tag_number,tag,component'
					],
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'tag_number: 0tag: filtering_valuecomponent: name:tag_item_1']
					]
				]
			],
			// Tag priority: one first.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1',
						'Tag display priority' => 'tag'
					],
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'tag: filtering_valuecomponent: name:tag_item_1tag_number: 0']
					]
				]
			]
		];
	}

	/**
	 * Test for checking filtered results by values in main filter.
	 *
	 * @dataProvider getFilterData
	 */
	public function testPageLatestData_Filter($data) {
		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Reset filter in case if some filtering remained before ongoing test case.
		$this->query('button:Reset')->one()->click();

		// Fill filter form with data.
		$form->fill(CTestArrayHelper::get($data, 'filter'));

		// If data contains Tags and their settings, fill them separataly, because tags form is more complicated.
		if (CTestArrayHelper::get($data, 'Tags')) {
			$form->getField('id:evaltype_0')->asSegmentedRadio()->fill(CTestArrayHelper::get($data, 'Tags.Evaluation', 'And/Or'));
			$form->getField('id:tags_0')->asMultifieldTable()->fill(CTestArrayHelper::get($data, 'Tags.tags', []));
		}

		$form->getField('id:show_tags_0')->asSegmentedRadio()->fill(CTestArrayHelper::get($data, 'Show tags', '3'));
		$form->getField('id:tag_name_format_0')->asSegmentedRadio()->fill(CTestArrayHelper::get($data, 'Tags name', 'Full'));

		$form->submit();
		$this->page->waitUntilReady();

		// Check filtered result.
		$this->assertTableData($data['result'], $this->getTableSelector());

		// Check Show tags filter setting.
		if (CTestArrayHelper::get($data, 'Show tags') === 'None') {
			$this->assertEquals(['', 'Host', 'Name', 'Last check', 'Last value', 'Change', '', 'Info'],
					$this->getTable()->getHeadersText()
			);
		}

		// Reset filter not to impact the results of next tests.
		$this->query('button:Reset')->one()->click();
	}

	public static function getSubfilterData() {
		return [
			// Tag values.
			[
				[
					'subfilter' => [
						'Tag values' => [
							'name:tag_item_1',
							'name:tag_item_2'
						]
					],
					'result' => [
						['Name' => 'tag_item_1'],
						['Name' => 'tag_item_2']
					]
				]
			],
			// Tag values and Data.
			[
				[
					'subfilter' => [
						'Tag values' => [
							'name:tag_item_1',
							'name:tag_item_2'
						],
						'Data' => [
							'With data'
						]
					],
					'result' => [
						['Name' => 'tag_item_1']
					]
				]
			],
			// Hosts and Tag values.
			[
				[
					'subfilter' => [
						'Hosts' => [
							'1_Host_to_check_Monitoring_Overview',
							'3_Host_to_check_Monitoring_Overview'
						],
						'Tag values' => [
							'Oracle'
						]
					],
					'result' => [
						['Name' => '3_item']
					]
				]
			]
		];
	}

	/**
	 * Test for checking filtered results clicking on subfilter.
	 *
	 * @dataProvider getSubfilterData
	 */
	public function testPageLatestData_Subfilter($data) {
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr(self::HOSTNAME));

		$link = (CTestArrayHelper::get($data['subfilter'], 'Data'))
			? 'zabbix.php?action=latest.view&hostids%5B%5D='.$hostid
			: 'zabbix.php?action=latest.view';

		$this->page->login()->open($link)->waitUntilReady();

		foreach ($data['subfilter'] as $header => $values) {
			foreach ($values as $value) {
				$this->query("xpath://h3[text()=".CXPathHelper::escapeQuotes($header)."]/..//a[text()=".
						CXPathHelper::escapeQuotes($value)."]")->waitUntilClickable()->one()->click();
				$this->page->waitUntilReady();
			}
		}

		// Check that page remained the same.
		$this->page->assertTitle('Latest data');
		$this->page->assertHeader('Latest data');

		$this->assertTableData($data['result'], $this->getTableSelector());
		$this->query('button:Reset')->waitUntilClickable()->one()->click();
	}

	/**
	 * Test for clicking on particular item tag in table and checking that items are filtered by this tag.
	 */
	public function testPageLatestData_ClickTag() {
		$tag = ['tag' => 'component: ', 'value' => 'storage'];

		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr('ЗАББИКС Сервер'));
		$this->page->login()->open('zabbix.php?action=latest.view&hostids%5B%5D='.$hostid)->waitUntilReady();

		$this->getTable()->query('button', $tag['tag'].$tag['value'])->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		// Check that page remained the same.
		$this->page->assertTitle('Latest data');
		$this->page->assertHeader('Latest data');

		// Check that tag value is selected in subfilter under correct header.
		$this->assertTrue($this->query("xpath://td/h3[text()='Tag values']/..//label[text()=".
				CXPathHelper::escapeQuotes($tag['tag'])."]/../..//span[@class=".
				CXPathHelper::fromClass('subfilter-enabled')."]/a[text()=".
				CXPathHelper::escapeQuotes($tag['value'])."]")->exists()
		);

		$this->assertTableData([
				['Name' => 'Free swap space'],
				['Name' => 'Free swap space in %'],
				['Name' => 'Total swap space']
			], $this->getTableSelector()
		);
	}

	/**
	 * Test that checks if host has visible name, it cannot be found by host name on Latest Data page.
	 */
	public function testPageLatestData_NoHostNames() {
		$result = [
			CDBHelper::getRandom(
				'SELECT host'.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_MONITORED.')'.
					' AND name <> host', 3
			),

			CDBHelper::getRandom(
				'SELECT host'.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_NOT_MONITORED.')'.
					' AND name <> host', 3
			),

			CDBHelper::getRandom(
				'SELECT host'.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_TEMPLATE.')'.
					' AND name <> host', 3
			)
		];

		$this->page->login()->open('zabbix.php?action=latest.view');
		$this->query('button:Reset')->waitUntilClickable()->one()->click();

		foreach ($result as $hosts) {
			foreach ($hosts as $host) {
				/*
				 * Check if hostname is present on page, if not, go to next page.
				 * Now there are 3 pages for unfiltered Latest data.
				 */
				for ($i = 1; $i < 3; $i++) {
					$this->assertFalse($this->query('link', $host['host'])->one(false)->isValid());
					$this->query('class:arrow-right')->waitUntilClickable()->one()->click();
					$this->page->waitUntilReady();
				}

				$this->query('button:Reset')->waitUntilClickable()->one()->click();
			}
		}
	}

	public static function getItemDescription() {
		return [
			// Item without description.
			[
				[
					'hostid' => '15003',
					'Item name' => 'item_testPageHistory_CheckLayout_Log'
				]
			],
			// Item with plain text in the description.
			[
				[
					'hostid' => '15003',
					'Item name' => 'item_testPageHistory_CheckLayout_Log_2',
					'description' => 'Non-clickable description'
				]
			],
			// Item with only 1 url in description.
			[
				[
					'hostid' => '15003',
					'Item name' => 'item_testPageHistory_CheckLayout_Eventlog',
					'description' => 'https://zabbix.com'
				]
			],
			// Item with text and url in description.
			[
				[
					'hostid' => '15003',
					'Item name' => 'item_testPageHistory_CheckLayout_Eventlog_2',
					'description' => 'The following url should be clickable: https://zabbix.com'
				]
			],
			// Item with multiple urls in description.
			[
				[
					'hostid' => '15003',
					'Item name' => 'item_testPageHistory_CheckLayout_Character',
					'description' => 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact'
				]
			],
			// Item with text and 2 urls in description.
			[
				[
					'hostid' => '15003',
					'Item name' => 'item_testPageHistory_CheckLayout_Text',
					'description' => 'These urls should be clickable: https://zabbix.com https://www.zabbix.com/career'
				]
			],
			// Item with underscore in macros name and one non existing macros  in description .
			[
				[
					'hostid' => '50010',
					'Item name' => 'Http agent item form',
					'description' => 'Underscore {$NONEXISTING}'
				]
			],
			// Item with 2 macros in description.
			[
				[
					'hostid' => '50010',
					'Item name' => 'Http agent item for update',
					'description' => '127.0.0.1 Some text'
				]
			],
			// Item with 2 macros and text in description.
			[
				[
					'hostid' => '50010',
					'Item name' => 'Http agent item for delete',
					'description' => 'Some text and IP number 127.0.0.1'
				]
			],
			// Item with macros inside curly brackets.
			[
				[
					'hostid' => '50007',
					'Item name' => 'Item-layout-test-002',
					'description' => '{Some text}'
				]
			],
			// Item with macros in description.
			[
				[
					'hostid' => '99027',
					'Item name' => 'Item to check graph',
					'description' => 'Some text'
				]
			]
		];
	}

	/**
	 * @dataProvider getItemDescription
	 */
	public function testPageLatestData_checkItemDescription($data) {
		// Open Latest data for host 'testPageHistory_CheckLayout'
		$this->page->login()->open('zabbix.php?&action=latest.view&show_details=0&hostids%5B%5D='.$data['hostid'])
				->waitUntilReady();

		// Find rows from the data provider and click on the description icon if such should persist.
		$row = $this->getTable()->findRow('Name', $data['Item name'], true);

		if (CTestArrayHelper::get($data,'description', false)) {
			$row->query('class:icon-description')->one()->click()->waitUntilReady();
			$overlay = $this->query('xpath://div[@class="overlay-dialogue"]')->one();

			// Verify the real description with the expected one.
			$this->assertEquals($data['description'], $overlay->getText());

			// Get urls form description.
			$urls = [];
			preg_match_all('/https?:\/\/\S+/', $data['description'], $urls);
			// Verify that each of the urls is clickable.
			foreach ($urls[0] as $url) {
				$this->assertTrue($overlay->query('xpath:./div/a[@href="'.$url.'"]')->one()->isClickable());
			}

			// Verify that the tool-tip can be closed.
			$overlay->query('xpath:./button[@title="Close"]')->one()->click();
			$this->assertFalse($overlay->isDisplayed());
		}
		// If the item has no description the description icon should not be there.
		else {
			$this->assertTrue($row->query('class:icon-description')->count() === 0);
		}
	}

	/**
	 * Maintenance icon hintbox.
	 */
	public function testPageLatestData_checkMaintenanceIcon() {
		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill(['Hosts' => 'Available host in maintenance']);
		$form->submit();

		$this->query('xpath://a['.CXPathHelper::fromClass('icon-maintenance').']')->waitUntilClickable()->one()->click();
		$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last()->getText();
		$hint_text = "Maintenance for Host availability widget [Maintenance with data collection]\n".
				"Maintenance for checking Show hosts in maintenance option in Host availability widget";
		$this->assertEquals($hint_text, $hint);
	}

	/**
	 * Check hint text for Last check and Last value columns
	 */
	public function testPageLatestData_checkHints() {
		$itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.zbx_dbstr('4_item'));
		$time = time();
		$value = '15';
		$true_time = date("Y-m-d H:i:s", $time);

		DBexecute('INSERT INTO history_uint (itemid, clock, value, ns) VALUES ('.zbx_dbstr($itemid).
				', '.zbx_dbstr($time).', '.zbx_dbstr($value).', 0)'
		);

		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$this->query('button:Reset')->one()->click();
		$form->fill(['Name' => '4_item'])->submit();
		$this->page->waitUntilReady();

		foreach (['Last check', 'Last value'] as $column) {
			if ($column === 'Last value') {
				$this->assertEquals('15 UNIT', $this->getTable()->getRow(0)->getColumn($column)->getText());
			}

			$this->getTable()->getRow(0)->getColumn($column)->query('class:cursor-pointer')->one()->click();
			$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last()->getText();
			$compare_hint = ($column === 'Last check') ? $true_time : $value;
			$this->assertEquals($compare_hint, $hint);
		}
	}
}
