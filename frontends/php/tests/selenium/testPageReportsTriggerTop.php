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
					'data_from' => '01.01.2016 00:00',
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
					'data_from' => '01.01.2016 00:00',
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
					'data_from' => '01.01.2016 15:15',
					'data_till' => '01.01.2017 15:15'
				]
			],
			[
				[
					'host_gr_name' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
					'data_from' => '22.10.2017 01:01',
					'data_till' => '24.10.2017 01:01',
					'result' =>
						[
							'Test trigger to check tag filter on problem page',
							'Test trigger with tag'
						]
				]
			],
			[
				[
					'data_from' => '23.10.2017 12:35',
					'data_till' => '23.10.2017 12:36',
					'result' =>
						[
							'Trigger for tag permissions MySQL'
						]
				]
			],
			[
				[
					'data_from' => '23.10.2017 12:33',
					'data_till' => '23.10.2017 12:36',
					'result' =>
						[
							'Test trigger to check tag filter on problem page',
							'Trigger for tag permissions MySQL'
						]
				]
			],
			[
				[
					'data_from' => '01.01.2016 00:00',
					'severities' =>
						[
							'Not classified',
							'Information',
							'Warning'
						],
					'result' =>
						[
							'Test trigger to check tag filter on problem page'
						]
				]
			],
			[
				[
					'data_from' => '01.01.2016 00:00',
					'severities' =>
						[
							'Not classified',
							'Warning',
							'Information',
							'Average'
						]
				]
			]
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
			$this->zbxTestDropdownHasOptions('groupid', ['Host group for tag permissions', 'Zabbix servers', 'ZBX6648 All Triggers','ZBX6648 Disabled Triggers','ZBX6648 Enabled Triggers']);
			$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
			$this->zbxTestClickLinkTextWait($data['host']);
			$this->zbxTestAssertElementText('//div[@id=\'hostids_\']/div/ul/li/span/span', $data['host']);
		}

		// Update date in 'From' field
		if (array_key_exists('data_from',$data)) {
			$data1 = explode(' ',$data['data_from']);
			$day_f = explode('.',$data1[0]);
			$time_f = explode(':',$data1[1]);
			$this->zbxTestInputTypeOverwrite('filter_from_day',$day_f[0]);
			$this->zbxTestInputTypeOverwrite('filter_from_month',$day_f[1]);
			$this->zbxTestInputTypeOverwrite('filter_from_year',$day_f[2]);
			$this->zbxTestInputTypeOverwrite('filter_from_hour',$time_f[0]);
			$this->zbxTestInputTypeOverwrite('filter_from_minute',$time_f[1]);
		}

		// Update date in 'Till' field
		if (array_key_exists('data_till',$data)) {
			$data2 = explode(' ',$data['data_till']);
			$day_t = explode('.',$data2[0]);
			$time_t = explode(':',$data2[1]);
			$this->zbxTestInputTypeOverwrite('filter_till_day',$day_t[0]);
			$this->zbxTestInputTypeOverwrite('filter_till_month',$day_t[1]);
			$this->zbxTestInputTypeOverwrite('filter_till_year',$day_t[2]);
			$this->zbxTestInputTypeOverwrite('filter_till_hour',$time_t[0]);
			$this->zbxTestInputTypeOverwrite('filter_till_minute',$time_t[1]);
		}

		if (array_key_exists('severities', $data)) {
			foreach ($data['severities'] as $severity) {
				$severity_id = $this->zbxTestGetAttributeValue('//label[text()=\''.$severity.'\']', 'for');
				$this->zbxTestClick($severity_id);
			}
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
