<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageReportsTriggerTop extends CWebTest {

	public function testPageReportsTriggerTop_CheckLayout(){
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestCheckTitle('100 busiest triggers');
		$this->zbxTestCheckHeader('100 busiest triggers');
		$this->zbxTestTextPresent('Host groups','Hosts','Severity','Filter','From', 'Till');

		$this->zbxTestClickButtonText('Reset');

		// Check Host groups "Select" button
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[3]', 'Select');
		// Check Hosts "Select" button
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[2]', 'Select');
		// Check date button for 'From' field
		$this->zbxTestAssertElementPresentXpath('//form[@id=\'id\']/div/div/div[2]/ul/li/div[2]/button');
		// Check date button for 'Till' field
		$this->zbxTestAssertElementPresentXpath('//form[@id=\'id\']/div/div/div[2]/ul/li[2]/div[2]/button');

		// Check selected severities
		$severities = ['Not classified', 'Warning', 'High', 'Information', 'Average', 'Disaster'];
		foreach ($severities as $severity) {
			$severity_id = $this->zbxTestGetAttributeValue('//label[text()=\''.$severity.'\']', 'for');
			$this->assertTrue($this->zbxTestCheckboxSelected($severity_id));
		}

		// Check closed filter
		$this->zbxTestClickWait('filter-mode');
		$this->zbxTestAssertNotVisibleId('filter-space');
		$this->zbxTestAssertNotVisibleId('groupids_');

		// Check opened filter
		$this->zbxTestClickWait('filter-mode');
		$this->zbxTestAssertVisibleId('filter-space');
		$this->zbxTestAssertVisibleId('groupids_');

		// Ckeck empty trigger list
		$this->zbxTestAssertElementText('//table//td[@colspan=\'4\']', 'No data found.');
	}

	public function testPageReportsTriggerTop_CheckDataFilter() {
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestClickButtonText('Reset');

		// Check default values of date filter
		$this->zbxTestAssertElementValue('filter_from_year', date('Y'));
		$this->zbxTestAssertElementValue('filter_from_month', date('m'));
		$this->zbxTestAssertElementValue('filter_from_day', date('d'));
		$this->zbxTestAssertElementValue('filter_from_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_from_minute', date('00'));
		$this->zbxTestAssertElementValue('filter_till_year', date('Y'));
		$this->zbxTestAssertElementValue('filter_till_month', date('m'));
		$this->zbxTestAssertElementValue('filter_till_day', date('d',strtotime('+1 day')));
		$this->zbxTestAssertElementValue('filter_till_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_till_minute', date('00'));
		// Check 'Yesterday' button
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[7]');
		$this->zbxTestAssertElementValue('filter_from_year', date('Y',strtotime('-1 day')));
		$this->zbxTestAssertElementValue('filter_from_month', date('m',strtotime('-1 day')));
		$this->zbxTestAssertElementValue('filter_from_day', date('d',strtotime('-1 day')));
		$this->zbxTestAssertElementValue('filter_from_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_from_minute', date('00'));
		$this->zbxTestAssertElementValue('filter_till_year', date('Y',strtotime('-1 day')));
		$this->zbxTestAssertElementValue('filter_till_month', date('m',strtotime('-1 day')));
		$this->zbxTestAssertElementValue('filter_till_day', date('d'));
		$this->zbxTestAssertElementValue('filter_till_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_till_minute', date('00'));
		// Check 'Current week' button
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[8]');
		$this->zbxTestAssertElementValue('filter_from_year', date('Y',strtotime('monday this week')));
		$this->zbxTestAssertElementValue('filter_from_month', date('m',strtotime('monday this week')));
		$this->zbxTestAssertElementValue('filter_from_day', date('d',strtotime('monday this week')));
		$this->zbxTestAssertElementValue('filter_from_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_from_minute', date('00'));
		$this->zbxTestAssertElementValue('filter_till_year', date('Y',strtotime('monday next week')));
		$this->zbxTestAssertElementValue('filter_till_month', date('m',strtotime('monday next week')));
		$this->zbxTestAssertElementValue('filter_till_day', date('d',strtotime('monday next week')));
		$this->zbxTestAssertElementValue('filter_till_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_till_minute', date('00'));
		// Check 'Current month' button
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[9]');
		$this->zbxTestAssertElementValue('filter_from_year', date('Y'));
		$this->zbxTestAssertElementValue('filter_from_month', date('m'));
		$this->zbxTestAssertElementValue('filter_from_day', date('01'));
		$this->zbxTestAssertElementValue('filter_from_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_from_minute', date('00'));
		$this->zbxTestAssertElementValue('filter_till_year', date('Y',strtotime('+1 month')));
		$this->zbxTestAssertElementValue('filter_till_month', date('m',strtotime('+1 month')));
		$this->zbxTestAssertElementValue('filter_till_day', date('01'));
		$this->zbxTestAssertElementValue('filter_till_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_till_minute', date('00'));
		// Check 'Current year' button
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[10]');
		$this->zbxTestAssertElementValue('filter_from_year', date('Y'));
		$this->zbxTestAssertElementValue('filter_from_month', date('01'));
		$this->zbxTestAssertElementValue('filter_from_day', date('01'));
		$this->zbxTestAssertElementValue('filter_from_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_from_minute', date('00'));
		$this->zbxTestAssertElementValue('filter_till_year', date('Y',strtotime('+1 year')));
		$this->zbxTestAssertElementValue('filter_till_month', date('01'));
		$this->zbxTestAssertElementValue('filter_till_day', date('01'));
		$this->zbxTestAssertElementValue('filter_till_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_till_minute', date('00'));
		// Check 'Last week' button
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[11]');
		$this->zbxTestAssertElementValue('filter_from_year', date('Y',strtotime('monday last week')));
		$this->zbxTestAssertElementValue('filter_from_month', date('m',strtotime('monday last week')));
		$this->zbxTestAssertElementValue('filter_from_day', date('d',strtotime('monday last week')));
		$this->zbxTestAssertElementValue('filter_from_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_from_minute', date('00'));
		$this->zbxTestAssertElementValue('filter_till_year', date('Y',strtotime('monday this week')));
		$this->zbxTestAssertElementValue('filter_till_month', date('m',strtotime('monday this week')));
		$this->zbxTestAssertElementValue('filter_till_day', date('d',strtotime('monday this week')));
		$this->zbxTestAssertElementValue('filter_till_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_till_minute', date('00'));
		// Check 'Last month' button
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[12]');
		$this->zbxTestAssertElementValue('filter_from_year', date('Y',strtotime('-1 month')));
		$this->zbxTestAssertElementValue('filter_from_month', date('m',strtotime('-1 month')));
		$this->zbxTestAssertElementValue('filter_from_day', date('01'));
		$this->zbxTestAssertElementValue('filter_from_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_from_minute', date('00'));
		$this->zbxTestAssertElementValue('filter_till_year', date('Y'));
		$this->zbxTestAssertElementValue('filter_till_month', date('m'));
		$this->zbxTestAssertElementValue('filter_till_day', date('01'));
		$this->zbxTestAssertElementValue('filter_till_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_till_minute', date('00'));
		// Check 'Last year' button
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[13]');
		$this->zbxTestAssertElementValue('filter_from_year', date('Y',strtotime('-1 year')));
		$this->zbxTestAssertElementValue('filter_from_month', date('01'));
		$this->zbxTestAssertElementValue('filter_from_day', date('01'));
		$this->zbxTestAssertElementValue('filter_from_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_from_minute', date('00'));
		$this->zbxTestAssertElementValue('filter_till_year', date('Y'));
		$this->zbxTestAssertElementValue('filter_till_month', date('01'));
		$this->zbxTestAssertElementValue('filter_till_day', date('01'));
		$this->zbxTestAssertElementValue('filter_till_hour', date('00'));
		$this->zbxTestAssertElementValue('filter_till_minute', date('00'));
	}

	public static function filter() {
		return [
			[
				[
					'host_gr_name' => 'Zabbix servers'
				]
			],
			[
				[
					'host_gr_name' => 'Zabbix servers',
					'filter_from_year' => '2016',
					'result'=>
						[
							'Test trigger to check tag filter on problem page',
							'Test trigger with tag'
						]
				]
			],
			[
				[
					'host_gr_name' => 'Zabbix servers',
					'host' => 'Host ZBX6663',
					'filter_from_year' => '2016'
				]
			],
			[
				[
					'host_gr_name' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
					'filter_from_year' => '2016',
					'result' =>
						[
							'Test trigger to check tag filter on problem page',
							'Test trigger with tag'
						]
				]
			],
			[
				[
					'host_gr_name' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
				]
			],
			[
				[
					'host_gr_name' => 'Zabbix servers',
					'filter_from_year' => '2016',
					'filter_from_month' => '01',
					'filter_from_day' => '01',
					'filter_from_hour' => '15',
					'filter_from_minute' => '15',
					'filter_till_year' => '2017',
					'filter_till_month' => '01',
					'filter_till_day' => '01',
					'filter_till_hour' => '15',
					'filter_till_minute' => '15'
				]
			],
			[
				[
					'host_gr_name' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
					'filter_from_year' => '2017',
					'filter_from_month' => '10',
					'filter_from_day' => '22',
					'filter_from_hour' => '01',
					'filter_from_minute' => '01',
					'filter_till_year' => '2017',
					'filter_till_month' => '10',
					'filter_till_day' => '24',
					'filter_till_hour' => '01',
					'filter_till_minute' => '01',
					'result' =>
						[
							'Test trigger to check tag filter on problem page',
							'Test trigger with tag'
						]
				]
			],
			[
				[
					'filter_from_year' => '2016',
					'severity' => '2',
					'result' =>
						[
							'Test trigger to check tag filter on problem page'
						]
				]
			],
			[
				[
					'filter_from_year' => '2016',
					'severity' => '5'
				]
			],
			[
				[
					'calendar'=>true,
				]
			],
		];
}

	/**
	 * @dataProvider filter
	 */
	public function testPageReportsTriggerTop_CheckFilter($data) {
		$this->zbxTestLogin('toptriggers.php');
		// Click button 'Reset'
		$this->zbxTestClickButtonText('Reset');

		if (array_key_exists('host_gr_name', $data)) {
			$this->zbxTestClickButtonMultiselect('groupids_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkTextWait($data['host_gr_name']);
			$this->zbxTestAssertElementText('//div[@id=\'groupids_\']/div/ul/li/span/span', $data['host_gr_name']);
		}

		if (array_key_exists('host', $data)) {
			$this->zbxTestClickButtonMultiselect('hostids_');
			$this->zbxTestLaunchOverlayDialog('Hosts');
			$this->zbxTestClickLinkTextWait($data['host']);
			$this->zbxTestAssertElementText('//div[@id=\'hostids_\']/div/ul/li/span/span', $data['host']);
		}

		// Update date in 'From' field
		if (array_key_exists('filter_from_year', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_from_year',$data['filter_from_year']);
		}

		if (array_key_exists('filter_from_month', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_from_month',$data['filter_from_month']);
		}

		if (array_key_exists('filter_from_day', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_from_day',$data['filter_from_day']);
		}

		if (array_key_exists('filter_from_hour', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_from_hour',$data['filter_from_hour']);
		}

		if (array_key_exists('filter_from_minute', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_from_minute',$data['filter_from_minute']);
		}

		if (array_key_exists('filter_till_year', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_till_year',$data['filter_till_year']);
		}

		if (array_key_exists('filter_till_month', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_till_month',$data['filter_till_month']);
		}

		if (array_key_exists('filter_till_day', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_till_day',$data['filter_till_day']);
		}

		if (array_key_exists('filter_till_hour', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_till_hour',$data['filter_till_hour']);
		}

		if (array_key_exists('filter_till_minute', $data)) {
			$this->zbxTestInputTypeOverwrite('filter_till_minute',$data['filter_till_minute']);
		}

		$severity_count=0;
		if (array_key_exists('severity', $data)) {
			while ($severity_count <= $data['severity']) {
				$this->zbxTestCheckboxSelect('severities_'.$severity_count, false);
				$severity_count++;
			}
		}

		if (array_key_exists('calendar_from', $data)) {
			$this->zbxTestClickXpathWait('//form[@id=\'id\']/div/div/div[2]/ul/li/div[2]/button');
			$this->zbxTestClickXpath('(//button[@value=\'Now\'])[1]');
			$this->zbxTestClickXpath('(//button[@value=\'Now\'])[2]');
			$this->zbxTestClickXpathWait('(//button[@type=\'button\'])[20]');
		}

		$this->zbxTestClickXpath('//button[@name=\'filter_set\']');
		if (array_key_exists('result', $data)) {
			$this->zbxTestTextPresent($data['result']);
		}
		else {
			$this->zbxTestAssertElementText('//table//td[@colspan=\'4\']', 'No data found.');
		}
	}
}
