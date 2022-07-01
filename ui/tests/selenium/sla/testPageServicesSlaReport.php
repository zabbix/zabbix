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
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup profiles
 *
 * @dataSource Services, Sla
 */
class testPageServicesSlaReport extends CWebTest {

	use TableTrait;

	private static $creation_time;
	private static $creation_day;
	private static $reporting_periods = [];

	const SLA_CREATION_TIME = 1619827200;

	public function testPageServicesSlaReport_GeneralLayout() {
		$this->page->login()->open('zabbix.php?action=slareport.list');
		$this->page->assertHeader('SLA report');
		// TODO: Uncomment the below check after ZBX-21264 is fixed.
		// $this->page->assertTitle('SLA report');

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
				'SLA Annual',
				'SLA Daily',
				'SLA Monthly',
				'SLA Quarterly',
				'SLA Weekly',
				'SLA with schedule and downtime',
				'SLA для удаления - 頑張って', 'Update SLA'
			],
			'table_selector' => 'xpath://form[@id="sla"]/table',
			'buttons' => ['Cancel']
		];

		$service_data = [
			'field' => 'Service',
			'headers' => ['Name', 'Tags', 'Problem tags'],
			'rows_count' => 26,
			'table_selector' => 'xpath://form[@name="services_form"]/table',
			'buttons' => ['Filter', 'Reset', 'Cancel'],
			'check_row' => [
				'Name' => 'Simple actions service',
				'Tags' => 'problem: falsetest: test789',
				'Problem tags' => 'problem: true'
			]
		];

		foreach ([$sla_data, $service_data] as $dialog_data) {
			$this->assertDialogContents($dialog_data);
		}

		foreach (['From', 'To'] as $field_label) {
			$field = $filter_form->getField($field_label)->query('xpath:./input')->one();
			$field->highlight();
			$this->assertEquals(10, $field->getAttribute('maxlength'));
			$this->assertEquals('YYYY-MM-DD', $field->getAttribute('placeholder'));
		}

		$this->assertEquals('Select SLA to display SLA report.', $this->query('class:list-table')->one()->getText());
	}

	public function getDateTimeData() {
		self::$creation_time = CDataHelper::get('Sla.creation_time');
		self::$creation_day = date('Y-m-d', self::$creation_time);

		foreach (['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Annually'] as $reporting_period) {
			$period_values = [];

			switch ($reporting_period) {
				case 'Daily':
					// By default the last 20 periods are displayed.
					for ($i = 0; $i < 20; $i++) {
						$day = strtotime('today '.-$i.' day');
						$period_values[$i]['value'] = date('Y-m-d', $day);
						$period_values[$i]['start'] = $day;
						$period_values[$i]['end'] = strtotime('tomorrow '.-$i.' day - 1 second');
					}
					break;

				case 'Weekly':
					for ($i = 1; $i <= 20; $i++) {
						$start = strtotime('next Sunday '.-$i.' week');

						// On Sundays calculation is different to avoid selecting the previous week instead of current.
//						$start = (date('w', $start) == date('w')) ? strtotime(date("Y-m-d", $start)." +7 days") : $start;
						$end = strtotime(date('M-d', $start).' + 6 days');

						$period_values[$i]['value'] = date('Y-m-d', $start).' – '.date('m-d', $end);
						$period_values[$i]['start'] = $start;
						$period_values[$i]['end'] = strtotime(date("M-d", $start)." + 1 week - 1 second");
					}
					break;

				case 'Monthly':
					// Get the number of Months to be displayed.
					$months = ((date('Y', time()) - date('Y', self::SLA_CREATION_TIME)) * 12) + ((date('m', time()) -
							date('m', self::SLA_CREATION_TIME))
					);

					for ($i = 0; $i <= $months; $i++) {
						$month = strtotime('this month '.-$i.' month');
						$period_values[$i]['value'] = date('Y-m', $month);
						$period_values[$i]['start'] = strtotime(date('Y-m', time()).' '.-$i.' month');
						$period_values[$i]['end'] = strtotime(date('Y-m', time()).' '.(-$i+1).' month - 1 second');
					}
					break;

				case 'Quarterly':
					$quarters = ['01 – 03', '04 – 06', '07 – 09', '10 – 12'];
					$current_year = date('Y', time());
					$current_month = date('m', time());

					for ($year = date('Y', self::SLA_CREATION_TIME); $year <= date('Y', time()); $year++) {
						foreach ($quarters as $quarter) {
							// Get the last month of the quarter under attention.
							$period_end = ltrim(stristr($quarter, '– '), '– ');
							$period_start = substr($quarter, 0, strpos($quarter, " –"));

							// Skip the quarters before SQL creation in SLA creation year.
							if ($year === date('Y', self::SLA_CREATION_TIME) && $period_end < $current_month) {

								continue;
							}

							// Write periods into reference array if period end is not later than current month.
							if ($year < $current_year || ($year == $current_year && $period_end <= $current_month)) {
								$period_values[$i]['value'] = $year.'-'.$quarter;
								$period_values[$i]['start'] = strtotime($year.'-'.$period_start);
								$period_values[$i]['end'] = strtotime($year.'-'.$period_end.' + 1 month - 1 second');

							}
						}
					}
					$period_values = array_reverse($period_values);
					break;

				case 'Annually':
					// Get the number of Years to be displayed.
					$years = (date('Y', time()) - date('Y', self::SLA_CREATION_TIME));

					for ($i = 0; $i <= $years; $i++) {
						$year = strtotime('this year '.-$i.' years');
						$period_values[$i]['value'] = date('Y', $year);
						$period_values[$i]['start'] = strtotime(date('Y', $year).'-01-01');
						$period_values[$i]['end'] = strtotime(date('Y', $year).'-01-01 +1 year -1 second');
					}
					break;
			}

			self::$reporting_periods[$reporting_period] = $period_values;
		}
	}

	public function getSlaDataWithService() {
		return [
			// Daily with downtime.
			[
				[
					'filter' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem'
					],
					'reporting_period' => 'Daily',
					'downtimes' => [
						'names' => ['EXCLUDED DOWNTIME', 'Second downtime']
					],
					'expected' => [
						'SLO' => '11.111'
					]
				]
			],
			// Daily without downtime.
			[
				[
					'filter' => [
						'SLA' => 'Update SLA',
						'Service' => 'Parent for 2 levels of child services'
					],
					'reporting_period' => 'Daily',
					'expected' => [
						'SLO' => '99.99',
						'SLI' => 100
					]
				]
			],
			// Weekly SLA.
			[
				[
					'filter' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service'
					],
					'reporting_period' => 'Weekly',
					'expected' => [
						'SLO' => '55.5555',
						'SLI' => 100
					]
				]
			],
			// Monthly SLA.
			[
				[
					'filter' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service'
					],
					'reporting_period' => 'Monthly',
					'expected' => [
						'SLO' => '22.22',
						'SLI' => 100
					]
				]
			],
			// Quarterly SLA.
			[
				[
					'filter' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service'
					],
					'reporting_period' => 'Quarterly',
					'expected' => [
						'SLO' => '33.33',
						'SLI' => 100
					]
				]
			],
			// Annual SLA.
			[
				[
					'filter' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem'
					],
					'reporting_period' => 'Annually',
					'expected' => [
						'SLO' => '44.44',
						'SLI' => 100
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSlaDataWithService
	 *
	 * @onBefore getDateTimeData
	 */
	public function testPageServicesSlaReport_LayoutWithService($data) {
		$this->page->login()->open('zabbix.php?action=slareport.list');
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();

		// TODO: Remove the below workaround with changing multiselect fill modes after ZBX-21264 is fixed.
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);
		$filter_form->fill($data['filter']);
		$filter_form->submit();
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_TYPE);

		$load_time = time();

		$table = $this->query('class:list-table')->asTable()->one();

		$period_headers = [
			'Daily' => 'Day',
			'Weekly' => 'Week',
			'Monthly' => 'Month',
			'Quarterly' => 'Quarter',
			'Annually' => 'Year'
		];
		$this->assertEquals([$period_headers[$data['reporting_period']], 'SLO', 'SLI', 'Uptime', 'Downtime',
				'Error budget', 'Excluded downtimes'], $table->getHeadersText()
		);

		/**
		 * This test is written taking into account that only SLA with daily reporting period has ongoing downtimes.
		 * Checking downtimes for other reporting periods would require a more complex solution.
		 */
		if (array_key_exists('downtimes', $data)) {
			$downtime_values = [];

			/***
			 * If the date has changed since data source was executed, then Downtimes will be divided into 2 days.
			 * Such case is covered in the else statement.
			 */
			if (date('Y-m-d', time()) === self::$creation_day) {
				foreach ($data['downtimes']['names'] as $downtime_name) {
					/**
					 * A second or two can pass from Downtime duration calculation till report is loaded.
					 * So an array of expected results is created.
					 */
					$single_downtime = [];
					for ($i = 0; $i <= 2; $i++) {
						$single_downtime[] = date('Y-m-d H:i', self::$creation_time).' '.$downtime_name.': '
								.convertUnitsS($load_time - self::$creation_time + $i);
					}

					$downtime_values[$downtime_name] = $single_downtime;

					unset($single_downtime);
				}
				// Check that each of the obtained downtimes is present in the created reference arrays.
				$row = $table->findRow($period_headers[$data['reporting_period']], self::$creation_day);
				$this->checkDowntimePresent($row, $downtime_values);
			}
			else {
				foreach ([date('Y-m-d', time()), self::$creation_day] as $day) {
					if ($day === self::$creation_day) {
						foreach ($data['downtimes']['names'] as $downtime_name) {
							// The time is not dependent on view load time, so no nee for "for" cycle.
							$single_downtime = [];
							$single_downtime[] = date('Y-m-d H:i', self::$creation_time).' '.$downtime_name.': '
									.convertUnitsS(strtotime('today') - self::$creation_time);
							$downtime_values[] = $single_downtime;

							unset($single_downtime);
						}
					}
					else {
						foreach ($data['downtimes']['names'] as $downtime_name) {
							$single_downtime = [];
							for ($i = 0; $i <= 2; $i++) {
								$single_downtime[] = date('Y-m-d H:i', strtotime('today')).' '.$downtime_name.': '
										.convertUnitsS($load_time - strtotime('today') + $i);
							}
							$downtime_values[] = $single_downtime;

							unset($single_downtime);
						}
					}

					$row = $table->findRow($period_headers[$data['reporting_period']], $day);
					$this->checkDowntimePresent($row, $downtime_values);
				}
			}
		}
		else {
			foreach (self::$reporting_periods[$data['reporting_period']] as $period) {
				$row = $table->findRow($period_headers[$data['reporting_period']], $period['value']);
				$this->assertEquals('', $row->getColumn('Excluded downtimes')->getText());
			}
		}

		foreach (self::$reporting_periods[$data['reporting_period']] as $period) {
			$row = $table->findRow($period_headers[$data['reporting_period']], $period['value']);
			$this->assertEquals($data['expected']['SLO'].'%', $row->getColumn('SLO')->getText());

			if (array_key_exists('SLI', $data['expected']) && $period['end'] > self::$creation_time) {
				$this->assertEquals($data['expected']['SLI'], $row->getColumn('SLI')->getText());

				// Check Uptime and Error budget values
				$uptime = $row->getColumn('Uptime')->getText();
				if ($period['end'] > $load_time) {
					$reference_uptime = [];
					$start_date = ($period['start'] < self::$creation_time) ? self::$creation_time : $period['start'];

					for ($i = 0; $i <= 2; $i++) {
						$reference_uptime[] = convertUnitsS($load_time - $start_date + $i);
					}

					$this->assertTrue(in_array($uptime, $reference_uptime));

					// Calculate the error budet based on the actual uptime and compare with actual error budget.
					$uptime_seconds = 0;
					foreach (explode(' ', $uptime) as $time_unit) {
						$uptime_seconds = $uptime_seconds + timeUnitToSeconds($time_unit);
					}

					$error_budget = convertUnitsS(intval($uptime_seconds / floatval($data['expected']['SLO']) * 100)
							- $uptime_seconds
					);
					$this->assertEquals($error_budget, $row->getColumn('Error budget')->getText());

				}
				else {
					$reference_uptime = [];
					for ($i = 0; $i <= 2; $i++) {
						$reference_uptime[] = convertUnitsS($period['end'] - self::$creation_time + $i);
					}
					$this->assertTrue(in_array($uptime, $reference_uptime));

					$this->assertEquals('0', $row->getColumn('Error budget')->getText());
				}
			}
			else {
				$this->assertEquals('N/A', $row->getColumn('SLI')->getText());
				$this->assertEquals('0', $row->getColumn('Uptime')->getText());
				$this->assertEquals('0', $row->getColumn('Error budget')->getText());
			}

			$this->assertEquals('0', $row->getColumn('Downtime')->getText());

		}
	}

	private function checkDowntimePresent($row, $downtime_values) {
		// Split column value into downtimes.
		foreach (explode("\n", $row->getColumn('Excluded downtimes')->getText()) as $downtime) {
			// Record if downtime found in reference downtime arrays.
			$match_found = false;
			foreach ($downtime_values as $downtime_array) {
				if (in_array($downtime, $downtime_array)) {
					$match_found = true;
				}
			}
			$this->assertTrue($match_found);
		}
	}

	public function testPageServicesSlaReport_LayoutWithoutService() {
		var_dump('hello');
	}

	private function assertDialogContents($dialog_data) {
		$filter_form = $this->query('name:zbx_filter')->one()->asForm();
		$filter_form->getField($dialog_data['field'])->query('button:Select')->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		$this->assertEquals($dialog_data['field'], $dialog->getTitle());

		if ($dialog_data['field'] === 'Service') {
			// Check filter form.
			$filter_form = $dialog->query('name:services_filter_form')->one();
			$this->assertEquals('Name', $filter_form->query('xpath:.//label')->one()->getText());
			$filter_input = $filter_form->query('name:filter_name')->one();
			$this->assertEquals(255, $filter_input->getAttribute('maxlength'));

			// Filter out all unwanted services befoce checking table content.
			$filter_input->fill($dialog_data['check_row']['Name']);
			$filter_button = $dialog->query('button:Filter')->one();
			$filter_button->click();
			$dialog->waitUntilReady();

			// Check the content of the services list.
			$this->assertTableData([$dialog_data['check_row']], $dialog_data['table_selector']);

			$filter_form->query('button:Reset')->one()->click();
			$dialog->waitUntilReady();
		}

		$this->assertEquals($dialog_data['headers'], $dialog->query('class:list-table')->asTable()->one()->getHeadersText());

		if (array_key_exists('column_data', $dialog_data)) {
			$this->assertTableDataColumn($dialog_data['column_data'], 'Name', $dialog_data['table_selector']);
		}
		else {
			$table = $dialog->query('class:list-table')->asTable()->one();
			$this->assertEquals($dialog_data['rows_count'], $table->getRows()->count());
		}

		foreach ($dialog_data['buttons'] as $button) {
			$this->assertTrue($dialog->query('button', $button)->one()->isClickable());
		}
		$dialog->close();
	}
}
