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


require_once dirname(__FILE__).'/../common/testSlaReport.php';

/**
 * @backup profiles
 *
 * @dataSource Services, Sla
 *
 * @onBefore getDateTimeData
 */
class testPageServicesSlaReport extends testSlaReport {

	public function testPageServicesSlaReport_GeneralLayout() {
		$this->page->login()->open('zabbix.php?action=slareport.list');
		$this->page->assertHeader('SLA report');

		$this->page->assertTitle('SLA report');

		// Check status of buttons on the SLA report page.
		foreach ($this->query('button', ['Apply', 'Reset'])->all() as $button) {
			$this->assertTrue($button->isClickable());
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

		// Check the list of available SLAs (disabled SLAs should not be present).
		$sla_data = [
			'field' => 'SLA',
			'headers' => ['Name'],
			'column_data' => [
				'Name' => [
					'SLA Annual',
					'SLA Daily',
					'SLA Monthly',
					'SLA Quarterly',
					'SLA Weekly',
					'SLA with schedule and downtime',
					'SLA для удаления - 頑張って', 'Update SLA'
				]
			],
			'table_selector' => 'xpath://form[@id="sla"]/table',
			'buttons' => ['Cancel']
		];

		$service_data = [
			'field' => 'Service',
			'headers' => ['Name', 'Tags', 'Problem tags'],
			'table_selector' => 'xpath://form[@name="services_form"]/table',
			'buttons' => ['Filter', 'Reset', 'Cancel'],
			'check_row' => [
				'Name' => 'Simple actions service',
				'Tags' => 'problem: falsetest: test789',
				'Problem tags' => 'problem: true'
			]
		];

		foreach ([$sla_data, $service_data] as $dialog_data) {
			$this->checkDialogContents($dialog_data);
		}

		foreach (['From', 'To'] as $field_label) {
			$field = $filter_form->getField($field_label)->query('xpath:./input')->one();
			$this->assertEquals(255, $field->getAttribute('maxlength'));
			$this->assertEquals('YYYY-MM-DD', $field->getAttribute('placeholder'));
		}

		$this->assertEquals('Select SLA to display SLA report.', $this->query('class:list-table')->one()->getText());
	}

	/**
	 * @dataProvider getSlaDataWithService
	 */
	public function testPageServicesSlaReport_LayoutWithService($data) {
		$this->openSlaReport($data['fields']);
		$this->checkLayoutWithService($data);
	}

	/**
	 * @dataProvider getSlaDataWithoutService
	 */
	public function testPageServicesSlaReport_LayoutWithoutService($data) {
		$this->openSlaReport($data['fields']);
		$this->checkLayoutWithoutService($data);
	}

	public function testPageServicesSlaReport_Sort() {
		$data = [
			'fields' => ['SLA' => 'SLA Monthly'],
			'expected' => ['Service with multiple service tags', 'Simple actions service']
		];
		$this->openSlaReport($data['fields']);

		$table = $this->query('class:list-table')->asTable()->one();
		$column_header = $table->query('xpath:.//th/a[text()="Service"]')->one();

		// Check initial sorting of services.
		$this->assertTableDataColumn($data['expected'], 'Service');

		// Check updated service sorting.
		foreach (['desc', 'asc'] as $sort) {
			$column_header->click();
			$this->assertTableDataColumn(($sort === 'asc') ? $data['expected'] : array_reverse($data['expected']), 'Service');
		}
	}

	public function getSlaDataWithCustomDates() {
		return [
			// Daily with custom dates.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => '2020-02-28',
						'To' => '2020-03-02'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2020-03-02',
						'2020-03-01',
						'2020-02-29',
						'2020-02-28'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => '2021-06-29'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-07-18',
						'2021-07-17',
						'2021-07-16',
						'2021-07-15',
						'2021-07-14',
						'2021-07-13',
						'2021-07-12',
						'2021-07-11',
						'2021-07-10',
						'2021-07-09',
						'2021-07-08',
						'2021-07-07',
						'2021-07-06',
						'2021-07-05',
						'2021-07-04',
						'2021-07-03',
						'2021-07-02',
						'2021-07-01',
						'2021-06-30',
						'2021-06-29'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'To' => '2021-06-29'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-06-29',
						'2021-06-28',
						'2021-06-27',
						'2021-06-26',
						'2021-06-25',
						'2021-06-24',
						'2021-06-23',
						'2021-06-22',
						'2021-06-21',
						'2021-06-20',
						'2021-06-19',
						'2021-06-18',
						'2021-06-17',
						'2021-06-16',
						'2021-06-15',
						'2021-06-14',
						'2021-06-13',
						'2021-06-12',
						'2021-06-11',
						'2021-06-10'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'To' => '2021-05-06'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-05-06',
						'2021-05-05',
						'2021-05-04',
						'2021-05-03',
						'2021-05-02',
						'2021-05-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => 'yesterday'
					],
					'reporting_period' => 'Daily'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2021-06-29',
						'To' => '2021-07-05'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-06-29',
						'2021-06-30',
						'2021-07-01',
						'2021-07-02',
						'2021-07-03',
						'2021-07-04',
						'2021-07-05'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2021-12-20'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-12-20',
						'2021-12-21',
						'2021-12-22',
						'2021-12-23',
						'2021-12-24',
						'2021-12-25',
						'2021-12-26',
						'2021-12-27',
						'2021-12-28',
						'2021-12-29',
						'2021-12-30',
						'2021-12-31',
						'2022-01-01',
						'2022-01-02',
						'2022-01-03',
						'2022-01-04',
						'2022-01-05',
						'2022-01-06',
						'2022-01-07',
						'2022-01-08'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => '2022-01-08'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-12-20',
						'2021-12-21',
						'2021-12-22',
						'2021-12-23',
						'2021-12-24',
						'2021-12-25',
						'2021-12-26',
						'2021-12-27',
						'2021-12-28',
						'2021-12-29',
						'2021-12-30',
						'2021-12-31',
						'2022-01-01',
						'2022-01-02',
						'2022-01-03',
						'2022-01-04',
						'2022-01-05',
						'2022-01-06',
						'2022-01-07',
						'2022-01-08'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => '2021-05-06'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-05-01',
						'2021-05-02',
						'2021-05-03',
						'2021-05-04',
						'2021-05-05',
						'2021-05-06'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => 'yesterday'
					],
					'reporting_period' => 'Daily'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'From' => '2021-09-25',
						'To' => '2021-10-04'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-10-03 – 10-09',
						'2021-09-26 – 10-02',
						'2021-09-19 – 09-25'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'From' => '2021-09-25'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2022-01-30 – 02-05',
						'2022-01-23 – 01-29',
						'2022-01-16 – 01-22',
						'2022-01-09 – 01-15',
						'2022-01-02 – 01-08',
						'2021-12-26 – 01-01',
						'2021-12-19 – 12-25',
						'2021-12-12 – 12-18',
						'2021-12-05 – 12-11',
						'2021-11-28 – 12-04',
						'2021-11-21 – 11-27',
						'2021-11-14 – 11-20',
						'2021-11-07 – 11-13',
						'2021-10-31 – 11-06',
						'2021-10-24 – 10-30',
						'2021-10-17 – 10-23',
						'2021-10-10 – 10-16',
						'2021-10-03 – 10-09',
						'2021-09-26 – 10-02',
						'2021-09-19 – 09-25'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'To' => '2022-02-02'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2022-01-30 – 02-05',
						'2022-01-23 – 01-29',
						'2022-01-16 – 01-22',
						'2022-01-09 – 01-15',
						'2022-01-02 – 01-08',
						'2021-12-26 – 01-01',
						'2021-12-19 – 12-25',
						'2021-12-12 – 12-18',
						'2021-12-05 – 12-11',
						'2021-11-28 – 12-04',
						'2021-11-21 – 11-27',
						'2021-11-14 – 11-20',
						'2021-11-07 – 11-13',
						'2021-10-31 – 11-06',
						'2021-10-24 – 10-30',
						'2021-10-17 – 10-23',
						'2021-10-10 – 10-16',
						'2021-10-03 – 10-09',
						'2021-09-26 – 10-02',
						'2021-09-19 – 09-25'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'To' => '2021-06-01'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-05-30 – 06-05',
						'2021-05-23 – 05-29',
						'2021-05-16 – 05-22',
						'2021-05-09 – 05-15',
						'2021-05-02 – 05-08',
						'2021-04-25 – 05-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'From' => 'today - 2 weeks'
					],
					'reporting_period' => 'Weekly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'From' => '2021-12-29',
						'To' => '2022-01-09'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-12-26 – 01-01',
						'2022-01-02 – 01-08',
						'2022-01-09 – 01-15'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'From' => '2021-12-29'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-12-26 – 01-01',
						'2022-01-02 – 01-08',
						'2022-01-09 – 01-15',
						'2022-01-16 – 01-22',
						'2022-01-23 – 01-29',
						'2022-01-30 – 02-05',
						'2022-02-06 – 02-12',
						'2022-02-13 – 02-19',
						'2022-02-20 – 02-26',
						'2022-02-27 – 03-05',
						'2022-03-06 – 03-12',
						'2022-03-13 – 03-19',
						'2022-03-20 – 03-26',
						'2022-03-27 – 04-02',
						'2022-04-03 – 04-09',
						'2022-04-10 – 04-16',
						'2022-04-17 – 04-23',
						'2022-04-24 – 04-30',
						'2022-05-01 – 05-07',
						'2022-05-08 – 05-14'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'To' => '2022-05-13'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-12-26 – 01-01',
						'2022-01-02 – 01-08',
						'2022-01-09 – 01-15',
						'2022-01-16 – 01-22',
						'2022-01-23 – 01-29',
						'2022-01-30 – 02-05',
						'2022-02-06 – 02-12',
						'2022-02-13 – 02-19',
						'2022-02-20 – 02-26',
						'2022-02-27 – 03-05',
						'2022-03-06 – 03-12',
						'2022-03-13 – 03-19',
						'2022-03-20 – 03-26',
						'2022-03-27 – 04-02',
						'2022-04-03 – 04-09',
						'2022-04-10 – 04-16',
						'2022-04-17 – 04-23',
						'2022-04-24 – 04-30',
						'2022-05-01 – 05-07',
						'2022-05-08 – 05-14'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'To' => '2021-06-01'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-04-25 – 05-01',
						'2021-05-02 – 05-08',
						'2021-05-09 – 05-15',
						'2021-05-16 – 05-22',
						'2021-05-23 – 05-29',
						'2021-05-30 – 06-05'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'From' => 'today - 3 weeks'
					],
					'reporting_period' => 'Weekly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => '2020-01-01',
						'To' => '2020-02-29'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-02',
						'2020-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => '2020-01-01'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2021-08',
						'2021-07',
						'2021-06',
						'2021-05',
						'2021-04',
						'2021-03',
						'2021-02',
						'2021-01',
						'2020-12',
						'2020-11',
						'2020-10',
						'2020-09',
						'2020-08',
						'2020-07',
						'2020-06',
						'2020-05',
						'2020-04',
						'2020-03',
						'2020-02',
						'2020-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'To' => '2023-02-15'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2023-02',
						'2023-01',
						'2022-12',
						'2022-11',
						'2022-10',
						'2022-09',
						'2022-08',
						'2022-07',
						'2022-06',
						'2022-05',
						'2022-04',
						'2022-03',
						'2022-02',
						'2022-01',
						'2021-12',
						'2021-11',
						'2021-10',
						'2021-09',
						'2021-08',
						'2021-07'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'To' => '2021-08-01'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2021-08',
						'2021-07',
						'2021-06',
						'2021-05'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => 'first day of this month - 2 months'
					],
					'reporting_period' => 'Monthly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => '2020-01-01',
						'To' => '2020-02-29'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-01',
						'2020-02'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => '2020-01-01'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-01',
						'2020-02',
						'2020-03',
						'2020-04',
						'2020-05',
						'2020-06',
						'2020-07',
						'2020-08',
						'2020-09',
						'2020-10',
						'2020-11',
						'2020-12',
						'2021-01',
						'2021-02',
						'2021-03',
						'2021-04',
						'2021-05',
						'2021-06',
						'2021-07',
						'2021-08'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'To' => '2023-02-15'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2021-07',
						'2021-08',
						'2021-09',
						'2021-10',
						'2021-11',
						'2021-12',
						'2022-01',
						'2022-02',
						'2022-03',
						'2022-04',
						'2022-05',
						'2022-06',
						'2022-07',
						'2022-08',
						'2022-09',
						'2022-10',
						'2022-11',
						'2022-12',
						'2023-01',
						'2023-02'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'To' => '2021-08-01'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2021-05',
						'2021-06',
						'2021-07',
						'2021-08'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => 'first day of this month - 2 months'
					],
					'reporting_period' => 'Monthly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'From' => '2021-05-01',
						'To' => '2021-10-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-10 – 12',
						'2021-07 – 09',
						'2021-04 – 06'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'From' => '2017-12-03'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2022-07 – 09',
						'2022-04 – 06',
						'2022-01 – 03',
						'2021-10 – 12',
						'2021-07 – 09',
						'2021-04 – 06',
						'2021-01 – 03',
						'2020-10 – 12',
						'2020-07 – 09',
						'2020-04 – 06',
						'2020-01 – 03',
						'2019-10 – 12',
						'2019-07 – 09',
						'2019-04 – 06',
						'2019-01 – 03',
						'2018-10 – 12',
						'2018-07 – 09',
						'2018-04 – 06',
						'2018-01 – 03',
						'2017-10 – 12'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'To' => '2026-05-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2026-04 – 06',
						'2026-01 – 03',
						'2025-10 – 12',
						'2025-07 – 09',
						'2025-04 – 06',
						'2025-01 – 03',
						'2024-10 – 12',
						'2024-07 – 09',
						'2024-04 – 06',
						'2024-01 – 03',
						'2023-10 – 12',
						'2023-07 – 09',
						'2023-04 – 06',
						'2023-01 – 03',
						'2022-10 – 12',
						'2022-07 – 09',
						'2022-04 – 06',
						'2022-01 – 03',
						'2021-10 – 12',
						'2021-07 – 09'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'To' => '2021-08-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-07 – 09',
						'2021-04 – 06'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'From' => 'first day of this month - 6 months'
					],
					'reporting_period' => 'Quarterly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'From' => '2021-05-01',
						'To' => '2021-10-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-04 – 06',
						'2021-07 – 09',
						'2021-10 – 12'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'From' => '2017-12-03'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2017-10 – 12',
						'2018-01 – 03',
						'2018-04 – 06',
						'2018-07 – 09',
						'2018-10 – 12',
						'2019-01 – 03',
						'2019-04 – 06',
						'2019-07 – 09',
						'2019-10 – 12',
						'2020-01 – 03',
						'2020-04 – 06',
						'2020-07 – 09',
						'2020-10 – 12',
						'2021-01 – 03',
						'2021-04 – 06',
						'2021-07 – 09',
						'2021-10 – 12',
						'2022-01 – 03',
						'2022-04 – 06',
						'2022-07 – 09'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'To' => '2026-05-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-07 – 09',
						'2021-10 – 12',
						'2022-01 – 03',
						'2022-04 – 06',
						'2022-07 – 09',
						'2022-10 – 12',
						'2023-01 – 03',
						'2023-04 – 06',
						'2023-07 – 09',
						'2023-10 – 12',
						'2024-01 – 03',
						'2024-04 – 06',
						'2024-07 – 09',
						'2024-10 – 12',
						'2025-01 – 03',
						'2025-04 – 06',
						'2025-07 – 09',
						'2025-10 – 12',
						'2026-01 – 03',
						'2026-04 – 06'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'To' => '2021-08-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-04 – 06',
						'2021-07 – 09'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'From' => 'first day of this month - 6 months'
					],
					'reporting_period' => 'Quarterly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'From' => '2020-05-01',
						'To' => '2025-12-31'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2025',
						'2024',
						'2023',
						'2022',
						'2021',
						'2020'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'From' => '2002-12-03'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2021',
						'2020',
						'2019',
						'2018',
						'2017',
						'2016',
						'2015',
						'2014',
						'2013',
						'2012',
						'2011',
						'2010',
						'2009',
						'2008',
						'2007',
						'2006',
						'2005',
						'2004',
						'2003',
						'2002'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'To' => '2037-01-01'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2037',
						'2036',
						'2035',
						'2034',
						'2033',
						'2032',
						'2031',
						'2030',
						'2029',
						'2028',
						'2027',
						'2026',
						'2025',
						'2024',
						'2023',
						'2022',
						'2021'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'From' => 'today - 13 months'
					],
					'reporting_period' => 'Annually'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'From' => '2019-05-01',
						'To' => '2024-10-01'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2019',
						'2020',
						'2021',
						'2022',
						'2023',
						'2024'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'From' => '2002-12-03'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2002',
						'2003',
						'2004',
						'2005',
						'2006',
						'2007',
						'2008',
						'2009',
						'2010',
						'2011',
						'2012',
						'2013',
						'2014',
						'2015',
						'2016',
						'2017',
						'2018',
						'2019',
						'2020',
						'2021'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'To' => '2037-02-01'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2021',
						'2022',
						'2023',
						'2024',
						'2025',
						'2026',
						'2027',
						'2028',
						'2029',
						'2030',
						'2031',
						'2032',
						'2033',
						'2034',
						'2035',
						'2036',
						'2037'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'From' => 'today - 13 months'
					],
					'reporting_period' => 'Annually'
				]
			],
			// Using non-complete date in From and To fields.
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => '2021',
						'To' => '2021'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2021-01',
						'2021-02',
						'2021-03',
						'2021-04',
						'2021-05',
						'2021-06',
						'2021-07',
						'2021-08',
						'2021-09',
						'2021-10',
						'2021-11',
						'2021-12'
					]
				]
			],
			// Returning more than 20 periods with service.
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => '2020-01-01',
						'To' => '2022-12-10'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2022-12',
						'2022-11',
						'2022-10',
						'2022-09',
						'2022-08',
						'2022-07',
						'2022-06',
						'2022-05',
						'2022-04',
						'2022-03',
						'2022-02',
						'2022-01',
						'2021-12',
						'2021-11',
						'2021-10',
						'2021-09',
						'2021-08',
						'2021-07',
						'2021-06',
						'2021-05',
						'2021-04',
						'2021-03',
						'2021-02',
						'2021-01',
						'2020-12',
						'2020-11',
						'2020-10',
						'2020-09',
						'2020-08',
						'2020-07',
						'2020-06',
						'2020-05',
						'2020-04',
						'2020-03',
						'2020-02',
						'2020-01'
					]
				]
			],
			// Returning more than 20 periods without service.
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => '2020-01-01',
						'To' => '2022-12-10'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-01',
						'2020-02',
						'2020-03',
						'2020-04',
						'2020-05',
						'2020-06',
						'2020-07',
						'2020-08',
						'2020-09',
						'2020-10',
						'2020-11',
						'2020-12',
						'2021-01',
						'2021-02',
						'2021-03',
						'2021-04',
						'2021-05',
						'2021-06',
						'2021-07',
						'2021-08',
						'2021-09',
						'2021-10',
						'2021-11',
						'2021-12',
						'2022-01',
						'2022-02',
						'2022-03',
						'2022-04',
						'2022-05',
						'2022-06',
						'2022-07',
						'2022-08',
						'2022-09',
						'2022-10',
						'2022-11',
						'2022-12'
					]
				]
			],
			// "To" value chronologically before "From" value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2022-06-25',
						'To' => '2022-06-23'
					],
					'error' => '"From" date must be less than "To" date.'
				]
			],
			// Non existing date in "From" and "To" fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2022-06-32',
						'To' => '2022-07-32'
					],
					'error' => [
						'Incorrect value for field "From": a date is expected.',
						'Incorrect value for field "To": a date is expected.'
					]
				]
			],
			// Trailing and leading spaces in "From" and "To" fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2022-06 ',
						'To' => ' 2022-06-13'
					],
					'error' => [
						'Incorrect value for field "From": a date is expected.',
						'Incorrect value for field "To": a date is expected.'
					]
				]
			],
			// Wrong value format in "From" and "To" fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '13-12-2022',
						'To' => '12/31/2022'
					],
					'error' => [
						'Incorrect value for field "From": a date is expected.',
						'Incorrect value for field "To": a date is expected.'
					]
				]
			],
			// Unix time in "From" and "To" fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '1641340800',
						'To' => '1641340801'
					],
					'error' => [
						'Incorrect value for field "From": a date is expected.',
						'Incorrect value for field "To": a date is expected.'
					]
				]
			],
			// Fields "From" and "To" too far in the past.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '1969-12-30',
						'To' => '1969-12-31'
					],
					'error' => [
						'Incorrect value for field "From": a date is expected.',
						'Incorrect value for field "To": a date is expected.'
					]
				]
			],
			// Fields "From" and "To" too far in the future.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2039-01-01',
						'To' => '2039-01-02'
					],
					'error' => [
						'Incorrect value for field "From": a date is expected.',
						'Incorrect value for field "To": a date is expected.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSlaDataWithCustomDates
	 */
	public function testPageServicesSlaReport_CheckCustomPeriods($data) {
		// Construct the expected result array if such is not present in the data provider.
		if (!array_key_exists('expected_periods', $data) && !array_key_exists('error', $data)) {
			$data['expected_periods'] = $this->getPeriodDataWithCustomDates($data);
			$data['fields']['From'] = date('Y-m-d', strtotime($data['fields']['From']));
		}

		$this->openSlaReport($data['fields']);

		$this->checkCustomPeriods($data);
	}

	/**
	 * Open the SLA report with configuration specified in the data provider.
	 *
	 * @param array $filter_data	SLA report parameters.
	 */
	public function openSlaReport($filter_data) {
		$this->page->login()->open('zabbix.php?action=slareport.list');
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();

		// Expand filter if it is collapsed.
		CFilterElement::find()->one()->setContext(CFilterElement::CONTEXT_RIGHT)->expand();

		// Usage of Select mode is required as in Type mode a service that contains the name of required service is chosen.
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);
		$filter_form->query('button:Reset')->one()->click();
		$filter_form->fill($filter_data);
		$filter_form->submit();
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_TYPE);
	}

	/**
	 * Retrieve array with reference reporting periods modified according to From field (all cases with To field
	 * are covered by data provider).
	 *
	 * @param	array	$data	data provider
	 * @return	array
	 */
	public function getPeriodDataWithCustomDates($data) {
		foreach (self::$reporting_periods[$data['reporting_period']] as $period) {
			// Write all periods that end after the value in From field into the reference array.
			if ($period['end'] >= strtotime($data['fields']['From'])) {
				$expected_periods[] = $period['value'];
			}
			else {
				break;
			}
		}

		if (!array_key_exists('Service', $data['fields'])) {
			// If SLA report is shown without selecting a service, then periods are displayed in reverse order.
			$expected_periods = array_reverse($expected_periods);
		}

		return $expected_periods;
	}

	/**
	 * Check reporting periods values in SLA report with custom dates.
	 *
	 * @param	array	$data	data provider
	 */
	public function checkCustomPeriods($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);

			return;
		}
		$table = $this->query('class:list-table')->asTable()->one();

		if (array_key_exists('Service', $data['fields'])) {
			$this->assertTableDataColumn($data['expected_periods'], self::$period_headers[$data['reporting_period']]);
		}
		else {
			$headers = $table->getHeadersText();

			unset($headers[0], $headers[1]);
			$this->assertEquals($data['expected_periods'], array_values($headers));
		}
	}
}
