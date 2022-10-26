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
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../traits/TagTrait.php';

/**
 * @backup sla, profiles
 *
 * @dataSource Services, Sla
 */
class testPageServicesSla extends CWebTest {

	use TableTrait;
	use TagTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	private static $update_sla = 'Update SLA';
	private static $delete_sla = 'SLA для удаления - 頑張って';

	public function testPageServicesSla_Layout() {
		$sla_data = [
			[
				'Name' => 'Disabled SLA',
				'SLO' => '9.99%',
				'Effective date' => '2020-01-01',
				'Reporting period' => 'Daily',
				'Timezone' => 'America/Nuuk',
				'Schedule' => 'Custom',
				'SLA report' => '',
				'Status' => 'Disabled'
			],
			[
				'Name' => 'Disabled SLA Annual',
				'SLO' => '13.01%',
				'Effective date' => '2030-12-31',
				'Reporting period' => 'Annually',
				'Timezone' => 'Pacific/Fiji',
				'Schedule' => 'Custom',
				'SLA report' => '',
				'Status' => 'Disabled'
			],
			[
				'Name' => 'SLA Annual',
				'SLO' => '44.44%',
				'Effective date' => '2021-05-01',
				'Reporting period' => 'Annually',
				'Timezone' => 'Europe/Riga',
				'Schedule' => '24x7',
				'SLA report' => 'SLA report',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'SLA Daily',
				'SLO' => '11.111%',
				'Effective date' => '2021-05-01',
				'Reporting period' => 'Daily',
				'Timezone' => 'Europe/Riga',
				'Schedule' => '24x7',
				'SLA report' => 'SLA report',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'SLA Monthly',
				'SLO' => '22.22%',
				'Effective date' => '2021-05-01',
				'Reporting period' => 'Monthly',
				'Timezone' => 'Europe/Riga',
				'Schedule' => '24x7',
				'SLA report' => 'SLA report',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'SLA Quarterly',
				'SLO' => '33.33%',
				'Effective date' => '2021-05-01',
				'Reporting period' => 'Quarterly',
				'Timezone' => 'Europe/Riga',
				'Schedule' => '24x7',
				'SLA report' => 'SLA report',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'SLA Weekly',
				'SLO' => '55.5555%',
				'Effective date' => '2021-05-01',
				'Reporting period' => 'Weekly',
				'Timezone' => 'Europe/Riga',
				'Schedule' => '24x7',
				'SLA report' => 'SLA report',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'SLA with schedule and downtime',
				'SLO' => '12.3456%',
				'Effective date' => '2022-05-01',
				'Reporting period' => 'Weekly',
				'Timezone' => 'Europe/Riga',
				'Schedule' => 'Custom',
				'SLA report' => 'SLA report',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'SLA для удаления - 頑張って',
				'SLO' => '66.6%',
				'Effective date' => '2022-04-30',
				'Reporting period' => 'Quarterly',
				'Timezone' => 'Europe/Riga',
				'Schedule' => 'Custom',
				'SLA report' => 'SLA report',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'Update SLA',
				'SLO' => '99.99%',
				'Effective date' => '2021-05-01',
				'Reporting period' => 'Daily',
				'Timezone' => 'Europe/Riga',
				'Schedule' => '24x7',
				'SLA report' => 'SLA report',
				'Status' => 'Enabled'
			]
		];

		$reference_schedules = [
			[
				'name' => 'Disabled SLA',
				'rows' => [
					'Sunday 00:00-17:00',
					'Monday 00:00-05:00',
					'Tuesday 05:00-06:00',
					'Wednesday 06:00-07:00',
					'Thursday 07:00-08:00',
					'Friday 04:00-20:00',
					'Saturday 23:00-24:00'
				],
				'check_header' => true
			],
			[
				'name' => 'Disabled SLA Annual',
				'rows' => [
					'Sunday 00:00-17:00',
					'Monday -',
					'Tuesday -',
					'Wednesday -',
					'Thursday -',
					'Friday -',
					'Saturday 23:00-24:00'
				]
			],
			[
				'name' => 'SLA для удаления - 頑張って',
				'rows' => [
					'Sunday 00:00-05:33',
					'Monday -',
					'Tuesday -',
					'Wednesday -',
					'Thursday -',
					'Friday -',
					'Saturday -'
				]
			],
			[
				'name' => 'SLA with schedule and downtime',
				'rows' => [
					'Sunday 00:00-00:04',
					'Monday -',
					'Tuesday -',
					'Wednesday -',
					'Thursday -',
					'Friday -',
					'Saturday -'
				]
			],
			[
				'name' => 'Update SLA'
			]
		];

		$reference_headers = [
			'Name' => true,
			'SLO' => true,
			'Effective date' => true,
			'Reporting period' => false,
			'Timezone' => false,
			'Schedule' => false,
			'SLA report' => false,
			'Status' => true
		];

		$form_buttons = [
			'Create SLA' => true,
			'Apply' => true,
			'Reset' => true,
			'Enable' => false,
			'Disable' => false,
			'Delete' => false
		];

		$sla_count = count($sla_data);

		// Open SLA page and check header and title.
		$this->page->login()->open('zabbix.php?action=sla.list');
		$this->page->assertHeader('SLA');
		$this->page->assertTitle('SLA');

		// Check status of buttons on the SLA page.
		foreach ($form_buttons as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		// Check displaying and hiding the filter.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_tab = $this->query('xpath://a[contains(text(), "Filter")]')->one();
		$filter = $filter_form->query('id:tab_0')->one();
		$this->assertTrue($filter->isDisplayed());
		$filter_tab->click();
		$this->assertFalse($filter->isDisplayed());
		$filter_tab->click();
		$this->assertTrue($filter->isDisplayed());

		// Check that all filter fields are present.
		$this->assertEquals(['Name', 'Status', 'Service tags'], $filter_form->getLabels()->asText());

		// Check the count of returned SLAs and the count of selected SLAs.
		$this->assertTableStats($sla_count);
		$selected_count = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_count->getText());
		$all_slas = $this->query('id:all_slas')->asCheckbox()->one();
		$all_slas->set(true);
		$this->assertEquals($sla_count.' selected', $selected_count->getText());

		// Check that buttons became enabled.
		foreach (['Enable', 'Disable', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isClickable());
		}

		$all_slas->set(false);
		$this->assertEquals('0 selected', $selected_count->getText());

		// Check SLA table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers_text = $table->getHeadersText();

		// Remove empty element from headers array.
		array_shift($headers_text);
		$this->assertSame(array_keys($reference_headers), $headers_text);

		// Check which headers are sortable.
		foreach ($reference_headers as $header => $sortable) {
			$xpath = 'xpath:.//th/a[text()='.CXPathHelper::escapeQuotes($header).']';
			if ($sortable) {
				$this->assertTrue($table->query($xpath)->one()->isClickable());
			}
			else {
				$this->assertFalse($table->query($xpath)->one(false)->isValid());
			}
		}

		// Check SLA table contents.
		$this->assertTableData($sla_data);

		// Check the links in SLA report column.
		foreach ($sla_data as $sla) {
			if ($sla['SLA report'] === 'SLA report') {
				$link = 'zabbix.php?action=slareport.list&filter_slaid='.CDataHelper::get('Sla.slaids')[$sla['Name']].'&filter_set=1';
				$this->assertStringEndsWith($link, $table->findRow('Name', $sla['Name'])->query('link:SLA report')->one()
						->getAttribute('href')
				);
			}
		}

		// Check the SLA custom schedule dialog values and its absence for 24x7 schedule.
		foreach ($reference_schedules as $schedule) {
			if (array_key_exists('rows', $schedule)) {
				// Find the corresponding row and open the Custom schedule dialog.
				$table->findRow('Name', $schedule['name'])->query('class:icon-description')->one()->click();
				$overlay = $this->query('xpath://div[@class="overlay-dialogue"]')->asOverlayDialog()->waitUntilReady()->one();
				$schedule_table = $overlay->query('class:list-table')->asTable()->one();
				$displayed_days = $schedule_table->getRows()->asText();

				// Check the header of the custom schedule table.
				if (CTestArrayHelper::get($schedule, 'check_header')) {
					$this->assertEquals(['Custom schedule'], $schedule_table->getHeadersText());
				}

				// Check records for each day in the custom schedule table.
				$this->assertEquals($schedule['rows'], $displayed_days);

				$overlay->close();
			}
			else {
				$this->assertFalse($table->findRow('Name', $schedule['name'])->query('class:icon-description')
						->one(false)->isValid()
				);
			}
		}
	}

	public function testPageServicesSla_ChangeStatus() {
		$this->page->login()->open('zabbix.php?action=sla.list');

		// Disable SLA.
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', self::$update_sla);
		$status = $row->getColumn('Status')->query('xpath:.//a')->one();
		$status->click();
		// Check that SLA is disabled.
		$this->checkSlaStatus($row, 'disabled', self::$update_sla);

		// Enable SLA.
		$status->click();

		// Check SLA enabled.
		$this->checkSlaStatus($row, 'enabled', self::$update_sla);

		// Disable SLA via button.
		foreach (['Disable' => 'disabled', 'Enable' => 'enabled'] as $button => $status) {
			$row->select();
			$this->query('button', $button)->one()->waitUntilClickable()->click();
			$this->checkSlaStatus($row, $status, self::$update_sla);
		}
	}

	/**
	 * Check the status of the SLA in the SLA list table.
	 *
	 * @param CTableRow	$row		Table row that contains the SLA with changed status.
	 * @param string	$expected	Flag that determines if the SLA should be enabled or disabled.
	 * @param string	$sla		SLA name the status of which was changed.
	 */
	private function checkSlaStatus($row, $expected, $sla) {
		if ($expected === 'enabled') {
			$message_title = 'SLA enabled';
			$column_status = 'Enabled';
			$db_status = '1';
		}
		else {
			$message_title = 'SLA disabled';
			$column_status = 'Disabled';
			$db_status = '0';
		}

		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $message_title);
		CMessageElement::find()->one()->close();
		$this->assertEquals($column_status, $row->getColumn('Status')->getText());
		$this->assertEquals($db_status, CDBHelper::getValue('SELECT status FROM sla WHERE name='.zbx_dbstr($sla)));
	}

	public function getFilterData() {
		return [
			// Name with special symbols.
			[
				[
					'filter' => [
						'Name' => '頑張って'
					],
					'expected' => [
						'SLA для удаления - 頑張って'
					]
				]
			],
			// Exact match for field Name .
			[
				[
					'filter' => [
						'Name' => 'Disabled SLA Annual'
					],
					'expected' => [
						'Disabled SLA Annual'
					]
				]
			],
			// Partial and exact match for field Name within ona search.
			[
				[
					'filter' => [
						'Name' => 'Disabled SLA'
					],
					'expected' => [
						'Disabled SLA',
						'Disabled SLA Annual'
					]
				]
			],
			// Partial name match with space in between.
			[
				[
					'filter' => [
						'Name' => 'd S'
					],
					'expected' => [
						'Disabled SLA',
						'Disabled SLA Annual'
					]
				]
			],
			// Partial name match with spaces on the sides.
			[
				[
					'filter' => [
						'Name' => ' SLA '
					],
					'expected' => [
						'Disabled SLA Annual'
					]
				]
			],
			// Wrong name in filter field "Name".
			[
				[
					'filter' => [
						'Name' => 'No data should be returned'
					]
				]
			],
			// Search should not be case sensitive.
			[
				[
					'filter' => [
						'Name' => 'sla WITH'
					],
					'expected' => [
						'SLA with schedule and downtime'
					]
				]
			],
			// Space in search field Name.
			[
				[
					'filter' => [
						'Name' => ' '
					],
					'expected' => [
						'Disabled SLA',
						'Disabled SLA Annual',
						'SLA Annual',
						'SLA Daily',
						'SLA Monthly',
						'SLA Quarterly',
						'SLA Weekly',
						'SLA with schedule and downtime',
						'SLA для удаления - 頑張って',
						'Update SLA'
					]
				]
			],
			// Retrieve only Enabled SLAs.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'expected' => [
						'SLA Annual',
						'SLA Daily',
						'SLA Monthly',
						'SLA Quarterly',
						'SLA Weekly',
						'SLA with schedule and downtime',
						'SLA для удаления - 頑張って',
						'Update SLA'
					]
				]
			],
			// Retrieve only Disabled SLAs.
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
						'Disabled SLA',
						'Disabled SLA Annual'
					]
				]
			],
			// Tag that is present on multiple hosts - Equals operator.
			[
				[
					'Tags' => [
						[
							'name' => 'old_tag_1',
							'operator' => 'Equals',
							'value' => 'old_value_1'
						]
					],
					'expected' => [
						'Disabled SLA Annual',
						'SLA Annual',
						'SLA Daily',
						'SLA with schedule and downtime'
					]
				]
			],
			// Tag that is present on multiple hosts - Contains operator.
			[
				[
					'Tags' => [
						[
							'name' => 'old_tag_1',
							'operator' => 'Contains',
							'value' => 'old_value_1'
						]
					],
					'expected' => [
						'Disabled SLA',
						'Disabled SLA Annual',
						'SLA Annual',
						'SLA Daily',
						'SLA with schedule and downtime'
					]
				]
			],
			// Exists operator.
			[
				[
					'Tags' => [
						[
							'name' => 'Unique TAG',
							'operator' => 'Exists'
						]
					],
					'expected' => [
						'Disabled SLA'
					]
				]
			],
			// Does not exist operator.
			[
				[
					'Tags' => [
						[
							'name' => 'old_tag_1',
							'operator' => 'Does not exist'
						]
					],
					'expected' => [
						'SLA Monthly',
						'SLA Quarterly',
						'SLA Weekly',
						'SLA для удаления - 頑張って',
						'Update SLA'
					]
				]
			],
			// Does not equal operator.
			[
				[
					'Tags' => [
						[
							'name' => 'old_tag_1',
							'operator' => 'Does not equal',
							'value' => 'old_value_1'
						]
					],
					'expected' => [
						'Disabled SLA',
						'SLA Monthly',
						'SLA Quarterly',
						'SLA Weekly',
						'SLA для удаления - 頑張って',
						'Update SLA'
					]
				]
			],
			// Does not equal operator.
			[
				[
					'Tags' => [
						[
							'name' => 'old_tag_1',
							'operator' => 'Does not contain',
							'value' => 'new'
						]
					],
					'expected' => [
						'Disabled SLA Annual',
						'SLA Annual',
						'SLA Daily',
						'SLA Monthly',
						'SLA Quarterly',
						'SLA Weekly',
						'SLA with schedule and downtime',
						'SLA для удаления - 頑張って',
						'Update SLA'
					]
				]
			],
			// Tag names are case-sensitive.
			[
				[
					'Tags' => [
						[
							'name' => 'Unique Tag',
							'operator' => 'Exists'
						]
					]
				]
			],
			// Tags evaluation: And/Or.
			[
				[
					'filter' => [
						'id:filter_evaltype' => 'And/Or'
					],
					'Tags' => [
						[
							'name' => 'old_tag_1',
							'operator' => 'Does not contain',
							'value' => 'new'
						],
						[
							'name' => 'sla',
							'operator' => 'Exists'
						]
					],
					'expected' => [
						'Disabled SLA Annual'
					]
				]
			],
			// Tags evaluation: Or.
			[
				[
					'filter' => [
						'id:filter_evaltype' => 'Or'
					],
					'Tags' => [
						[
							'name' => 'old_tag_1',
							'operator' => 'Does not contain',
							'value' => 'new'
						],
						[
							'name' => 'sla',
							'operator' => 'Exists'
						]
					],
					'expected' => [
						'Disabled SLA Annual',
						'SLA Annual',
						'SLA Daily',
						'SLA Monthly',
						'SLA Quarterly',
						'SLA Weekly',
						'SLA with schedule and downtime',
						'SLA для удаления - 頑張って',
						'Update SLA'
					]
				]
			],
			// All filter fields involved.
			[
				[
					'filter' => [
						'Name' => ' SLA',
						'Status' => 'Enabled',
						'id:filter_evaltype' => 'Or'
					],
					'Tags' => [
						[
							'name' => 'old_tag_1',
							'operator' => 'Does not contain',
							'value' => 'new'
						],
						[
							'name' => 'sla',
							'operator' => 'Exists'
						]
					],
					'expected' => [
						'Update SLA'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageServicesSla_Filter($data) {
		$this->page->login()->open('zabbix.php?action=sla.list');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));

		// Fill in tags filtering information.
		if (CTestArrayHelper::get($data, 'Tags')) {
			$this->setTags($data['Tags']);
		}
		$form->submit();
		$this->page->waitUntilReady();

		if (!array_key_exists('expected', $data)) {
			// Check that 'No data found.' string is returned if no results are expected.
			$this->assertTableData();
		}
		else {
			// Using column Name check that only the expected SLAs are returned in the list.
			$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected'));
		}

		// Reset the filter and check that all SLAs are displayed.
		$this->query('button:Reset')->one()->click();
		$this->assertTableStats(count(CDataHelper::get('Sla.slaids')));
	}

	public function getSortData() {
		return [
			[
				[
					'sort_field' => 'Name',
					'expected' => [
						'Update SLA',
						'SLA для удаления - 頑張って',
						'SLA with schedule and downtime',
						'SLA Weekly',
						'SLA Quarterly',
						'SLA Monthly',
						'SLA Daily',
						'SLA Annual',
						'Disabled SLA Annual',
						'Disabled SLA'
					]
				]
			],
			[
				[
					'sort_field' => 'SLO',
					'expected' => [
						'9.99%',
						'11.111%',
						'12.3456%',
						'13.01%',
						'22.22%',
						'33.33%',
						'44.44%',
						'55.5555%',
						'66.6%',
						'99.99%'
					]
				]
			],
			[
				[
					'sort_field' => 'Effective date',
					'expected' => [
						'2020-01-01',
						'2021-05-01',
						'2021-05-01',
						'2021-05-01',
						'2021-05-01',
						'2021-05-01',
						'2021-05-01',
						'2022-04-30',
						'2022-05-01',
						'2030-12-31'
					]
				]
			],
			[
				[
					'sort_field' => 'Status',
					'expected' => [
						'Disabled',
						'Disabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSortData
	 */
	public function testPageServicesSla_Sort($data) {
		$this->page->login()->open('zabbix.php?action=sla.list');
		$table = $this->query('class:list-table')->asTable()->one();
		$header = $table->query('xpath:.//a[text()="'.$data['sort_field'].'"]')->one();

		foreach(['desc', 'asc'] as $sorting) {
			$expected = ($sorting === 'desc') ? $data['expected'] : array_reverse($data['expected']);
			$header->click();
			$this->assertTableDataColumn($expected, $data['sort_field']);
		}
	}

	public function testPageServicesSla_Delete() {
		$this->page->login()->open('zabbix.php?action=sla.list');

		// Delete SLA.
		$this->selectTableRows(self::$delete_sla);
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that SLA is deleted.
		$this->assertMessage(TEST_GOOD, 'SLA deleted');
		$this->assertEquals(0, CDBHelper::getCount('SELECT slaid FROM sla WHERE name='.zbx_dbstr(self::$delete_sla)));
	}
}
