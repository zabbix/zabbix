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

	private static $creation_time;
	private static $creation_day;
	private static $reporting_periods = [];
	private static $period_headers = [
		'Daily' => 'Day',
		'Weekly' => 'Week',
		'Monthly' => 'Month',
		'Quarterly' => 'Quarter',
		'Annually' => 'Year'
	];

	const SLA_CREATION_TIME = 1619827200;

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
					$months = ($months > 20) ? 20 : $months;

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

					$i = 0;
					for ($year = date('Y', self::SLA_CREATION_TIME); $year <= date('Y', time()); $year++) {
						foreach ($quarters as $quarter) {
							// Get the last month of the quarter under attention.
							$period_end = ltrim(stristr($quarter, '– '), '– ');
							$period_start = substr($quarter, 0, strpos($quarter, " –"));

							// Skip the quarters before SQL creation in SLA creation year.
							if ($year === date('Y', self::SLA_CREATION_TIME) && $period_end < date("m", self::SLA_CREATION_TIME)) {
								continue;
							}

							// Write periods into reference array if period end is not later than current month.
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

	public function checkLayoutWithService($data) {
		$table = $this->query('class:list-table')->asTable()->one();

		if (!CTestArrayHelper::get($data, 'no_data')) {
			$load_time = time();
			$reference_periods = self::$reporting_periods[$data['reporting_period']];
		}
		else {
			// Check empty result in case of selecting not related SLA and Service and proceed with next test.
			$this->assertTableData();
			$this->assertFalse($this->query('xpath://div[@class="table-stats"]')->one(false)->isValid());

			return;
		}

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
			$downtime_values = [];
			/**
			 * If the date has changed since data source was executed, then downtimes will be divided into 2 days.
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
				$row = $table->findRow(self::$period_headers[$data['reporting_period']], self::$creation_day);
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

					$row = $table->findRow(self::$period_headers[$data['reporting_period']], $day);
					$this->checkDowntimePresent($row, $downtime_values);
				}
			}
		}
		else {
			foreach ($reference_periods as $period) {
				$row = $table->findRow(self::$period_headers[$data['reporting_period']], $period['value']);
				$this->assertEquals('', $row->getColumn('Excluded downtimes')->getText());
			}
		}

		foreach ($reference_periods as $period) {
			$row = $table->findRow(self::$period_headers[$data['reporting_period']], $period['value']);
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

	public function checkLayoutWithoutService($data) {
		$reference_periods = self::$reporting_periods[$data['reporting_period']];

		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertTableStats(count($data['expected']['services']));

		$headers = ['Service', 'SLO'];
		foreach (array_reverse($reference_periods) as $period) {
			$headers[] = $period['value'];
		}
		$this->assertEquals($headers, $table->getHeadersText());

		if (CTestArrayHelper::get($data, 'check_sorting')) {
			foreach ($table->getHeaders() as $header) {
				// Only "Service" column is sortable.
				if ($header->getText() !== 'Service') {
					$this->assertFalse($header->query('tag:a')->one(false)->isValid());
				}
			}
		}

		foreach ($data['expected']['services'] as $service) {
			$row = $table->findRow('Service', $service);

			$this->assertEquals($data['expected']['SLO'].'%', $row->getColumn('SLO')->getText());

			foreach (array_reverse($reference_periods) as $period) {
				if (array_key_exists('SLI', $data['expected']) && $period['end'] > self::$creation_time) {
					$this->assertEquals($data['expected']['SLI'], $row->getColumn($period['value'])->getText());
				}
				else {
					$this->assertEquals('N/A', $row->getColumn($period['value'])->getText());
				}
			}
		}
	}

	public function openSlaReport($filter_data) {
		$this->page->login()->open('zabbix.php?action=slareport.list');
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();

		// TODO: Remove the below workaround with changing multiselect fill modes after ZBX-21264 is fixed.
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);
		$filter_form->query('button:Reset')->one()->click();
		$filter_form->fill($filter_data);
		$filter_form->submit();
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_TYPE);
	}

	public function getPeriodDataWithCustomDates($data) {
		foreach (self::$reporting_periods[$data['reporting_period']] as $period) {
			if ($period['end'] >= strtotime($data['filter']['From'])) {
				$expected_periods[] = $period['value'];
			}
			else {
				break;
			}
		}

		if (!array_key_exists('Service', $data['filter'])) {

			$expected_periods = array_reverse($expected_periods);
		}

		return $expected_periods;
	}

	public function checkCustomPeriods($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);

			return;
		}
		$table = $this->query('class:list-table')->asTable()->one();

		if (array_key_exists('Service', $data['filter'])) {
			$this->assertTableDataColumn($data['expected_periods'], self::$period_headers[$data['reporting_period']]);
		}
		else {
			$headers = $table->getHeadersText();

			unset($headers[0], $headers[1]);
			$this->assertEquals($data['expected_periods'], array_values($headers));
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
}
