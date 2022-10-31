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

class testSlaReport extends CWebTest {

	use TableTrait;

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

	public static $reporting_periods = [];
	public static $period_headers = [
		'Daily' => 'Day',
		'Weekly' => 'Week',
		'Monthly' => 'Month',
		'Quarterly' => 'Quarter',
		'Annually' => 'Year'
	];

	private static $actual_creation_time;	// Actual timestamp when data source was executed.
	private static $service_creation_time;	// Service "Service with problem" creation time, needed for downtime calculation.

	const SLA_CREATION_TIME = 1619827200; // SLA creation timestamp as per scenario - 01.05.2021

	public function getSlaDataWithService() {
		return [
			// Daily with downtime.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem'
					],
					'reporting_period' => 'Daily',
					'downtimes' => ['EXCLUDED DOWNTIME', 'Second downtime'],
					'check_sorting' => true,
					'expected' => [
						'SLO' => '11.111'
					]
				]
			],
			// Daily without downtime.
			[
				[
					'fields' => [
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
					'fields' => [
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
					'fields' => [
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
					'fields' => [
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
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem'
					],
					'reporting_period' => 'Annually',
					'expected' => [
						'SLO' => '44.44',
						'SLI' => 100
					]
				]
			],
			// Incorrect SLA and Service combination.
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Child 1'
					],
					'reporting_period' => 'Annually',
					'no_data' => true
				]
			]
		];
	}

	public function getSlaDataWithoutService() {
		return [
			// Daily with downtime.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily'
					],
					'reporting_period' => 'Daily',
					'expected' => [
						'SLO' => '11.111',
						'services' => ['Service with problem']
					]
				]
			],
			// Daily without downtime.
			[
				[
					'fields' => [
						'SLA' => 'Update SLA'
					],
					'reporting_period' => 'Daily',
					'expected' => [
						'SLO' => '99.99',
						'SLI' => 100,
						'services' => ['Parent for 2 levels of child services']
					]
				]
			],
			// Weekly SLA.
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly'
					],
					'reporting_period' => 'Weekly',
					'expected' => [
						'SLO' => '55.5555',
						'SLI' => 100,
						'services' => ['Service with multiple service tags', 'Simple actions service']
					]
				]
			],
			// Monthly SLA.
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly'
					],
					'reporting_period' => 'Monthly',
					'expected' => [
						'SLO' => '22.22',
						'SLI' => 100,
						'services' => ['Service with multiple service tags', 'Simple actions service']
					]
				]
			],
			// Quarterly SLA.
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly'
					],
					'reporting_period' => 'Quarterly',
					'expected' => [
						'SLO' => '33.33',
						'SLI' => 100,
						'services' => ['Service with multiple service tags', 'Simple actions service']
					]
				]
			],
			// Annual SLA.
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual'
					],
					'reporting_period' => 'Annually',
					'expected' => [
						'SLO' => '44.44',
						'SLI' => 100,
						'services' => ['Service with problem']
					]
				]
			]
		];
	}

	/**
	 * Create the reference array with reporting periods based on the SLA creation time and current date.
	 */
	public function getDateTimeData() {
		self::$actual_creation_time = CDataHelper::get('Sla.creation_time');
		self::$service_creation_time = CDBHelper::getValue(
				'SELECT created_at FROM services WHERE name='.zbx_dbstr('Service with problem')
		);

		// Construct the reference reporting period array based on the period type.
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
						$end = strtotime(date('M-d', $start).' + 6 days');

						$period_values[$i]['value'] = date('Y-m-d', $start).' – '.date('m-d', $end);
						$period_values[$i]['start'] = $start;
						$period_values[$i]['end'] = strtotime(date("M-d", $start)." + 1 week - 1 second");
					}
					break;

				case 'Monthly':
					// Get the number of Months to be displayed as difference between today and SLA creation day in months.
					$months = CDateTimeHelper::countMonthsBetweenDates(self::SLA_CREATION_TIME, time());

					$months = ($months > 20) ? 20 : $months;

					for ($i = 0; $i <= $months; $i++) {
						$month = strtotime('first day of this month '.-$i.' month');
						$period_values[$i]['value'] = date('Y-m', $month);
						$period_values[$i]['start'] = strtotime(date('Y-m').' '.-$i.' month');
						$period_values[$i]['end'] = strtotime(date('Y-m').' '.(-$i+1).' month - 1 second');
					}
					break;

				case 'Quarterly':
					$quarters = ['01 – 03', '04 – 06', '07 – 09', '10 – 12'];
					$current_year = date('Y');
					$current_month = date('m');

					$i = 0;
					for ($year = date('Y', self::SLA_CREATION_TIME); $year <= date('Y'); $year++) {
						foreach ($quarters as $quarter) {
							// Get the last and the first month of the quarter under attention.
							$period_end = ltrim(stristr($quarter, '– '), '– ');
							$period_start = substr($quarter, 0, strpos($quarter, " –"));

							// Skip the quarters before SLA creation quarter in SLA creation year.
							if ($year === date('Y', self::SLA_CREATION_TIME) && $period_end < date("m", self::SLA_CREATION_TIME)) {
								continue;
							}

							// Write periods into reference array if period start is not later than current month.
							if ($year < $current_year || ($year == $current_year && $period_start <= $current_month)) {
								$period_values[$i]['value'] = $year.'-'.$quarter;
								$period_values[$i]['start'] = strtotime($year.'-'.$period_start);
								$period_values[$i]['end'] = strtotime($year.'-'.$period_end.' + 1 month - 1 second');

								$i++;
							}
						}
					}
					$period_values = array_reverse($period_values);
					break;

				case 'Annually':
					// Get the number of Years to be displayed as difference between this year and SLA creation year.
					$years = (date('Y') - date('Y', self::SLA_CREATION_TIME));

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

	/**
	 * Check SLA report when SLA is specified together with the corresponding Service.
	 *
	 * @param array		$data		test case related data from data provider.
	 * @param boolean	$widget		flag that specifies whether the check is made in the SLA report or SLA report widget.
	 */
	public function checkLayoutWithService($data, $widget = false) {
		$creation_day = date('Y-m-d', self::$actual_creation_time);

		$table = ($widget)
			? CDashboardElement::find()->one()->getWidget($data['fields']['Name'])->query('class:list-table')->asTable()->one()
			: $this->query('class:list-table')->asTable()->one();

		// Check empty result if non-related SLA + Service or disabled SLA (in widget) is selected and proceed with next test.
		if (CTestArrayHelper::get($data, 'no_data')) {
			$string = (array_key_exists('expected', $data)) ? $data['expected'] : 'No data found.';
			$this->assertEquals([$string], $table->getRows()->asText());
			$this->assertFalse($table->query('xpath://div[@class="table-stats"]')->one(false)->isValid());

			return;
		}

		// Get the timestamp when screen was loaded and the reference reporting periods.
		$load_time = time();
		$reference_periods = self::$reporting_periods[$data['reporting_period']];

		// Check table headers text and check that none of them are clickable.
		$this->assertEquals([self::$period_headers[$data['reporting_period']], 'SLO', 'SLI', 'Uptime', 'Downtime',
				'Error budget', 'Excluded downtimes'], $table->getHeadersText()
		);

		if (CTestArrayHelper::get($data, 'check_sorting')) {
			foreach ($table->getHeaders() as $header) {
				$this->assertFalse($header->query('tag:a')->one(false)->isValid());
			}
		}

		// This test is written taking into account that only SLA with daily reporting period has ongoing downtimes.
		if (array_key_exists('downtimes', $data)) {
			// Downtime starts from min(SLA creation timestamp, Service creation timestamp).
			$downtime_start = min(self::$actual_creation_time, self::$service_creation_time);
			$downtime_values = [];
			/**
			 * If the date has changed since data source was executed, then downtimes will be divided into 2 days.
			 * Such case is covered in the else statement.
			 */
			if (date('Y-m-d') === $creation_day) {
				foreach ($data['downtimes'] as $downtime_name) {
					/**
					 * A second or two can pass from Downtime duration calculation till report is loaded.
					 * So an array of expected results is created and the presence of actual value in array is checked.
					 */
					$single_downtime = [];

					for ($i = 0; $i <= 3; $i++) {
						$single_downtime[] = date('Y-m-d H:i', $downtime_start).' '.$downtime_name.': '
								.convertUnitsS($load_time - $downtime_start + $i);
					}

					$downtime_values[$downtime_name] = $single_downtime;

					unset($single_downtime);
				}
				// Check that each of the obtained downtimes is present in the created reference arrays.
				$row = $table->findRow(self::$period_headers[$data['reporting_period']], $creation_day);
				$this->checkDowntimePresent($row, $downtime_values);
			}
			else {
				foreach ([date('Y-m-d'), $creation_day] as $day) {
					if ($day === $creation_day) {
						foreach ($data['downtimes'] as $downtime_name) {
							/**
							 * Time is counted from min(SLA creation timestamp, Service creation timestamp) till the start
							 * of next period. This time difference is not dependent on view load time, so no need for "for" cycle.
							 */
							$single_downtime = [];
							$single_downtime[] = date('Y-m-d H:i', $downtime_start).' '.$downtime_name.': '
									.convertUnitsS(strtotime('today') - $downtime_start);
							$downtime_values[] = $single_downtime;

							unset($single_downtime);
						}
					}
					else {
						foreach ($data['downtimes'] as $downtime_name) {
							// Time is counted from  period start till page load time.
							$single_downtime = [];
							for ($i = 0; $i <= 3; $i++) {
								$single_downtime[] = date('Y-m-d H:i', strtotime('today')).' '.$downtime_name.': '
										.convertUnitsS($load_time - strtotime('today') + $i);
							}
							$downtime_values[] = $single_downtime;

							unset($single_downtime);
						}
					}

					$row = $table->findRow(self::$period_headers[$data['reporting_period']], $day);
					$this->checkDowntimePresent($row, $downtime_values);
				}
			}
		}
		else {
			foreach ($reference_periods as $period) {
				// If no downtime is expected, then check that the Downtime column is empty.
				$row = $table->findRow(self::$period_headers[$data['reporting_period']], $period['value']);
				$this->assertEquals('', $row->getColumn('Excluded downtimes')->getText());
			}
		}

		// Check other columns of the displayed report.
		foreach ($reference_periods as $period) {
			$row = $table->findRow(self::$period_headers[$data['reporting_period']], $period['value']);
			$this->assertEquals($data['expected']['SLO'].'%', $row->getColumn('SLO')->getText());

			/**
			 * SLI is displayed for periods from SLA actual creation time to page load time.
			 * If SLI is expected, then Uptime and Error budget should be calculated and checked.
			 */
			if (array_key_exists('SLI', $data['expected']) && $period['end'] > self::$actual_creation_time) {
				$this->assertEquals($data['expected']['SLI'], $row->getColumn('SLI')->getText());

				// Check Uptime and Error budget values. These values are calcullated only from the actual SLA creation time.
				$uptime = $row->getColumn('Uptime')->getText();
				if ($period['end'] > $load_time) {
					$reference_uptime = [];
					// If SLA created in current period, calculation starts from creation timestamp, else from period start.
					$start_time = max($period['start'], min(self::$actual_creation_time, self::$service_creation_time));

					// Get array of Utime possible values and check that the correct one is there.
					for ($i = 0; $i <= 3; $i++) {
						$reference_uptime[] = convertUnitsS($load_time - $start_time + $i);
					}

					$this->assertTrue(in_array($uptime, $reference_uptime));

					// Calculate the error budet based on the actual uptime and compare with actual error budget.
					$uptime_seconds = 0;
					foreach (explode(' ', $uptime) as $time_unit) {
						$uptime_seconds = $uptime_seconds + timeUnitToSeconds($time_unit);
					}

					$error_budget[] = convertUnitsS(intval($uptime_seconds / floatval($data['expected']['SLO']) * 100)
						- $uptime_seconds
					);

					$this->assertTrue(in_array($row->getColumn('Error budget')->getText(), $error_budget));
				}
				else {
					$reference_uptime = [];
					$uptime_start = min(self::$actual_creation_time, self::$service_creation_time);
					for ($i = 0; $i <= 3; $i++) {
						$reference_uptime[] = convertUnitsS($period['end'] - $uptime_start + $i);
					}
					$this->assertTrue(in_array($uptime, $reference_uptime));

					// Error budget is always 0 for periods that have already passed.
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

	/**
	 * Check the SLA report in case if only SLA is specified (without Service).
	 *
	 * @param array		$data		test case related data from data provider
	 * @param boolean	$widget		flag that specifies whether the check is made in the SLA report or SLA report widget.
	 */
	public function checkLayoutWithoutService($data, $widget = false) {
		// This if condition is here specifically to check case when displaying disabled SLA on SLA report widget.
		if (array_key_exists('no_data', $data)) {
			$table = CDashboardElement::find()->one()->getWidget($data['fields']['Name'])->query('class:list-table')->asTable()->one();
			$this->assertEquals([$data['expected']], $table->getRows()->asText());

			return;
		}
		$reference_periods = self::$reporting_periods[$data['reporting_period']];
		$count = count($data['expected']['services']);

		if ($widget) {
			$table = CDashboardElement::find()->one()->getWidget($data['fields']['Name'])->query('class:list-table')->asTable()->one();
			$this->assertEquals('Displaying '.$count.' of '.$count.' found',
				$table->query('xpath:.//td[@class="list-table-footer"]')->one()->getText()
			);
		}
		else {
			$table = $this->query('class:list-table')->asTable()->one();
			$this->assertTableStats($count);
		}

		$headers = ['Service', 'SLO'];
		foreach (array_reverse($reference_periods) as $period) {
			$headers[] = $period['value'];
		}
		$this->assertEquals($headers, $table->getHeadersText());

		if (CTestArrayHelper::get($data, 'check_sorting')) {
			foreach ($table->getHeaders() as $header) {
				// Only "Service" column is sortable.
				if ($header->getText() !== 'Service' || $widget) {
					$this->assertFalse($header->query('tag:a')->one(false)->isValid());
				}
			}
		}

		foreach ($data['expected']['services'] as $service) {
			$row = $table->findRow('Service', $service);

			$this->assertEquals($data['expected']['SLO'].'%', $row->getColumn('SLO')->getText());

			// For SLA without service periods are shown in ascending order, so reference array should be reversed.
			foreach (array_reverse($reference_periods) as $period) {
				if (array_key_exists('SLI', $data['expected']) && $period['end'] > self::$actual_creation_time) {
					$this->assertEquals($data['expected']['SLI'], $row->getColumn($period['value'])->getText());
				}
				else {
					$this->assertEquals('N/A', $row->getColumn($period['value'])->getText());
				}
			}
		}
	}

	/**
	 * Split cell into active downtimes and check that it is present in the reference array.
	 *
	 * @param CTableRowElement	$row				row that contains the downtime values to be checked
	 * @param array				$downtime_values	reference array that should contain the downtime to be checked
	 */
	private function checkDowntimePresent($row, $downtime_values) {
		// Split column value into downtimes.
		foreach (explode("\n", $row->getColumn('Excluded downtimes')->getText()) as $downtime) {
			// Record if downtime found in reference downtime arrays.
			$match_found = false;
			foreach ($downtime_values as $downtime_array) {
				if (in_array($downtime, $downtime_array)) {
					$match_found = true;

					break;
				}
			}

			$this->assertTrue($match_found);
		}
	}

	/**
	 * Check the layout and the contents on the dialogs in SLA and Service multiselect elements.
	 *
	 * @param array		$dialog_data	array that contains all of the reference data needed to check dialog layout
	 * @param boolean	$widget			flag that specified whether the check is made in the SLA report or SLA report widget
	 */
	public function checkDialogContents($dialog_data, $widget = false) {
		$form_selector = ($widget) ? 'name:widget_dialogue_form' : 'name:zbx_filter';
		$form = $this->query($form_selector)->one()->asForm();
		$form->getField($dialog_data['field'])->query('button:Select')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();

		$this->assertEquals($dialog_data['field'], $dialog->getTitle());

		if ($dialog_data['field'] === 'Service') {
			// Check filter form.
			$filter_form = $dialog->query('name:services_filter_form')->one();
			$this->assertEquals('Name', $filter_form->query('xpath:.//label')->one()->getText());
			$filter_input = $filter_form->query('name:filter_name')->one();
			$this->assertEquals(255, $filter_input->getAttribute('maxlength'));

			// Filter out all unwanted services before checking table content.
			$filter_input->fill($dialog_data['check_row']['Name']);
			$dialog->query('button:Filter')->one()->click();
			$dialog->waitUntilReady();

			// Check the content of the services list.
			$this->assertTableData([$dialog_data['check_row']], $dialog_data['table_selector']);

			$filter_form->query('button:Reset')->one()->click();
			$dialog->waitUntilReady();
		}

		$this->assertEquals($dialog_data['headers'], $dialog->query('class:list-table')->asTable()->one()->getHeadersText());

		if (array_key_exists('column_data', $dialog_data)) {
			foreach ($dialog_data['column_data'] as $column => $values) {
				$this->assertTableDataColumn($values, $column, $dialog_data['table_selector']);
			}
		}
		else {
			$table = $dialog->query('class:list-table')->asTable()->one();
			$this->assertEquals(CDBHelper::getCount('SELECT serviceid FROM services'), $table->getRows()->count());
		}

		$this->assertEquals(count($dialog_data['buttons']), $dialog->query('button', $dialog_data['buttons'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
		$dialog->query('button:Cancel')->one()->click();
	}
}
