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

require_once dirname(__FILE__).'/common/testFormAdministrationGeneral.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralGUI extends testFormAdministrationGeneral {

	public $config_link = 'zabbix.php?action=gui.edit';
	public $form_selector = 'xpath://form[contains(@action, "gui.update")]';

	public $default_values = [
		'Default language' => 'English (en_US)',
		'Default time zone' => 'System',
		'Default theme' => 'Blue',
		'Limit for search and filter results' => '1000',
		'Max number of columns and rows in overview tables' => '50',
		'Max count of elements to show inside table cell' => '50',
		'Show warning if Zabbix server is down' => true,
		'Working time' => '1-5,09:00-18:00',
		'Show technical errors' => false,
		'Max history display period' => '24h',
		'Time filter default period' => '1h',
		'Max period for time selector' => '2y'
	];

	public $db_default_values = [
		'default_lang' => 'en_US',
		'default_timezone' => 'system',
		'default_theme' => 'blue-theme',
		'search_limit' => 1000,
		'max_overview_table_size' => 50,
		'max_in_table' => 50,
		'server_check_interval' => 10,
		'work_period' => '1-5,09:00-18:00',
		'show_technical_errors' => 0,
		'history_period' => '24h',
		'period_default' => '1h',
		'max_period' => '2y'
	];

	public $custom_values = [
		'Default language' => 'English (en_US)',
		'Default theme' => 'Dark',
		'Limit for search and filter results' => '50',
		'Max number of columns and rows in overview tables' => '25',
		'Max count of elements to show inside table cell' => '100',
		'Show warning if Zabbix server is down' => false,
		'Working time' => '1-3,03:15-22:45',
		'Show technical errors' => true,
		'Max history display period' => '24h',
		'Time filter default period' => '1h',
		'Max period for time selector' => '2y'
	];

	public function testFormAdministrationGeneralGUI_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=gui.edit');
		$this->page->assertTitle('Configuration of GUI');
		$this->page->assertHeader('GUI');

		$limits = [
			'search_limit' => 6,
			'max_overview_table_size' => 6,
			'max_in_table' => 5,
			'work_period' => 255,
			'history_period' => 32,
			'period_default' => 32,
			'max_period' => 32
		];
		foreach ($limits as $id => $limit) {
			$this->assertEquals($limit, $this->query('id', $id)->one()->getAttribute('maxlength'));
		}

		$this->query('xpath://a[@class="icon-info status-red"]')->one()->click();
		$this->assertEquals(
			'You are not able to choose some of the languages,'.
				' because locales for them are not installed on the web server.',
			$this->query('class:red')->one()->getText()
		);
	}

	/**
	 * Test for checking form update without changing any data.
	 */
	public function testFormAdministrationGeneralGUI_SimpleUpdate() {
		$this->executeSimpleUpdate();
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 */
	public function testFormAdministrationGeneralGUI_ResetButton() {
		$this->executeResetButtonTest();
	}

	/**
	 * Test data for GUI form.
	 */
	public function getCheckFormData() {
		return [
			// Minimal valid values. In period fields minimal valid time in seconds with 's'.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Default language' => 'English (en_US)',
						'Default time zone' => 'UTC',
						'Default theme' => 'Dark',
						'Limit for search and filter results' => '1',
						'Max number of columns and rows in overview tables' => '5',
						'Max count of elements to show inside table cell' => '1',
						'Show warning if Zabbix server is down' => false,
						'Working time' => '1-1,00:00-00:01',
						'Show technical errors' => true,
						'Max history display period' => '86400s',
						'Time filter default period' => '60s',
						'Max period for time selector' => '31536000s'
					],
					'db' => [
						'default_lang' => 'en_US',
						'default_timezone' => 'UTC',
						'default_theme' => 'dark-theme',
						'search_limit' => 1,
						'max_overview_table_size' => 5,
						'max_in_table' => 1,
						'server_check_interval' => 0,
						'work_period' => '1-1,00:00-00:01',
						'show_technical_errors' => 1,
						'history_period' => '86400s',
						'period_default' => '60s',
						'max_period' => '31536000s'
					]
				]
			],
			// In period fields minimal valid time in seconds without 's'.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Working time' => '1-5,09:00-18:00;5-7,12:00-16:00',
						'Max history display period' => '86400',
						'Time filter default period' => '60',
						'Max period for time selector' => '31536000'
					],
					'db' => [
						'work_period' => '1-5,09:00-18:00;5-7,12:00-16:00',
						'history_period' => '86400',
						'period_default' => '60',
						'max_period' => '31536000'
					]
				]
			],
			// In period fields minimal valid time in minutes.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max history display period' => '1440m',
						'Time filter default period' => '1m',
						'Max period for time selector' => '525600m'
					],
					'db' => [
						'history_period' => '1440m',
						'period_default' => '1m',
						'max_period' => '525600m'
					]
				]
			],
			// In period fields minimal valid time in hours.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max history display period' => '24h',
						'Max period for time selector' => '8760h'
					],
					'db' => [
						'history_period' => '24h',
						'max_period' => '8760h'
					]
				]
			],
			// In period fields minimal valid time in days.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max history display period' => '1d',
						'Max period for time selector' => '365d'
					],
					'db' => [
						'history_period' => '1d',
						'max_period' => '365d'
					]
				]
			],
			// In period fields minimal valid time in weeks.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max period for time selector' => '53w'
					],
					'db' => [
						'max_period' => '53w'
					]
				]
			],
			// In period fields minimal valid time in Months.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max period for time selector' => '13M'
					],
					'db' => [
						'max_period' => '13M'
					]
				]
			],
			// In period fields minimal valid time in years.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max period for time selector' => '1y'
					],
					'db' => [
						'max_period' => '1y'
					]
				]
			],
			// In period fields maximal valid time in seconds with 's'.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max history display period' => '604800s',
						'Time filter default period' => '315360000s',
						'Max period for time selector' => '315360000s'
					],
					'db' => [
						'history_period' => '604800s',
						'period_default' => '315360000s',
						'max_period' => '315360000s'
					]
				]
			],
			// In period fields maximal valid time in seconds without 's'.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Default time zone' => 'System',
						'Default theme' => 'High-contrast dark',
						'Max history display period' => '604800',
						'Time filter default period' => '315360000',
						'Max period for time selector' => '315360000'
					],
					'db' => [
						'default_timezone' => 'system',
						'default_theme' => 'hc-dark',
						'history_period' => '604800',
						'period_default' => '315360000',
						'max_period' => '315360000'
					]
				]
			],
			// In period fields maximal valid time in minutes.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max history display period' => '10080m',
						'Time filter default period' => '5256000m',
						'Max period for time selector' => '5256000m'
					],
					'db' => [
						'history_period' => '10080m',
						'period_default' => '5256000m',
						'max_period' => '5256000m'
					]
				]
			],
			// In period fields maximal valid time in days.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max history display period' => '7d',
						'Time filter default period' => '3650d',
						'Max period for time selector' => '3650d'
					],
					'db' => [
						'history_period' => '7d',
						'period_default' => '3650d',
						'max_period' => '3650d'
					]
				]
			],
			// In period fields maximal valid time in weeks.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Max history display period' => '7d',
						'Time filter default period' => '3650d',
						'Max period for time selector' => '3650d'
					],
					'db' => [
						'history_period' => '7d',
						'period_default' => '3650d',
						'max_period' => '3650d'
					]
				]
			],
			// In period fields maximal valid time in Months.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Time filter default period' => '121M',
						'Max period for time selector' => '121M'
					],
					'db' => [
						'period_default' => '121M',
						'max_period' => '121M'
					]
				]
			],
			// Maximal valid values. In period fields time in years.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Default time zone' => 'Pacific/Kiritimati',
						'Default theme' => 'High-contrast light',
						'Limit for search and filter results' => '999999',
						'Max number of columns and rows in overview tables' => '999999',
						'Max count of elements to show inside table cell' => '99999',
						'Working time' => '{$WORKING_HOURS}',
						'Time filter default period' => '10y',
						'Max period for time selector' => '10y'
					],
					'db' => [
						'default_timezone' => 'Pacific/Kiritimati',
						'default_theme' => 'hc-light',
						'search_limit' => 999999,
						'max_overview_table_size' => 999999,
						'max_in_table' => 99999,
						'work_period' => '{$WORKING_HOURS}',
						'period_default' => '10y',
						'max_period' => '10y'
					]
				]
			],
			// Zero values without 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Limit for search and filter results' => '0',
						'Max number of columns and rows in overview tables' => '0',
						'Max count of elements to show inside table cell' => '0',
						'Working time' => '0',
						'Max history display period' => '0',
						'Time filter default period' => '0',
						'Max period for time selector' => '0'
					],
					'details' => [
						'Incorrect value for field "search_limit": value must be no less than "1".',
						'Incorrect value for field "max_overview_table_size": value must be no less than "5".',
						'Incorrect value for field "max_in_table": value must be no less than "1".',
						'Incorrect value for field "work_period": a time period is expected.',
						'Incorrect value for field "history_period": value must be one of 86400-604800.',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// Zero values with 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '0s',
						'Time filter default period' => '0s',
						'Max period for time selector' => '0s'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800.',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// Empty values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Limit for search and filter results' => '',
						'Max number of columns and rows in overview tables' => '',
						'Max count of elements to show inside table cell' => '',
						'Working time' => '',
						'Max history display period' => '',
						'Time filter default period' => '',
						'Max period for time selector' => ''
					],
					'details' => [
						'Incorrect value for field "search_limit": value must be no less than "1".',
						'Incorrect value for field "max_overview_table_size": value must be no less than "5".',
						'Incorrect value for field "max_in_table": value must be no less than "1".',
						'Incorrect value for field "work_period": a time period is expected.',
						'Incorrect value for field "history_period": a time unit is expected.',
						'Incorrect value for field "period_default": a time unit is expected.',
						'Incorrect value for field "max_period": a time unit is expected.'
					]
				]
			],
			// Invalid string values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Limit for search and filter results' => 'text',
						'Max number of columns and rows in overview tables' => 'text',
						'Max count of elements to show inside table cell' => 'text',
						'Working time' => 'text',
						'Max history display period' => 'text',
						'Time filter default period' => 'text',
						'Max period for time selector' => 'text'
					],
					'details' => [
						'Incorrect value for field "search_limit": value must be no less than "1".',
						'Incorrect value for field "max_overview_table_size": value must be no less than "5".',
						'Incorrect value for field "max_in_table": value must be no less than "1".',
						'Incorrect value for field "work_period": a time period is expected.',
						'Incorrect value for field "history_period": a time unit is expected.',
						'Incorrect value for field "period_default": a time unit is expected.',
						'Incorrect value for field "max_period": a time unit is expected.'
					]
				]
			],
			// Invalid special symbol values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Limit for search and filter results' => '!@#$%^&*()_+',
						'Max number of columns and rows in overview tables' => '!@#$%^&*()_+',
						'Max count of elements to show inside table cell' => '!@#$%^&*()_+',
						'Working time' => '!@#$%^&*()_+',
						'Max history display period' => '!@#$%^&*()_+',
						'Time filter default period' => '!@#$%^&*()_+',
						'Max period for time selector' => '!@#$%^&*()_+'
					],
					'details' => [
						'Incorrect value for field "search_limit": value must be no less than "1".',
						'Incorrect value for field "max_overview_table_size": value must be no less than "5".',
						'Incorrect value for field "max_in_table": value must be no less than "1".',
						'Incorrect value for field "work_period": a time period is expected.',
						'Incorrect value for field "history_period": a time unit is expected.',
						'Incorrect value for field "period_default": a time unit is expected.',
						'Incorrect value for field "max_period": a time unit is expected.'
					]
				]
			],
			// Invalid values. In period fields minimal invalid time in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max number of columns and rows in overview tables' => '4',
						'Max history display period' => '86399s',
						'Time filter default period' => '59s',
						'Max period for time selector' => '31535999s'
					],
					'details' => [
						'Incorrect value for field "max_overview_table_size": value must be no less than "5".',
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields minimal invalid time in seconds without "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '86399',
						'Time filter default period' => '59',
						'Max period for time selector' => '31535999'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields minimal invalid time in minutes.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '1439m',
						'Time filter default period' => '0m',
						'Max period for time selector' => '525599m'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields minimal invalid time in hours.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '23h',
						'Time filter default period' => '0h',
						'Max period for time selector' => '8759h'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields minimal invalid time in weeks.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '0w',
						'Time filter default period' => '0w',
						'Max period for time selector' => '52w'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields minimal invalid time in Month.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '0M',
						'Time filter default period' => '0M',
						'Max period for time selector' => '12M'
					],
					'details' => [
						'Incorrect value for field "history_period": a time unit is expected.',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields minimal invalid time in years.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Time filter default period' => '0y',
						'Max period for time selector' => '0y'
					],
					'details' => [
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time in seconds without 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '604801',
						'Time filter default period' => '315360001',
						'Max period for time selector' => '315360001'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time in seconds with 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '604801s',
						'Time filter default period' => '315360001s',
						'Max period for time selector' => '315360001s'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time in minutes.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '10081m',
						'Time filter default period' => '5256001m',
						'Max period for time selector' => '5256001m'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time in hours.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '169h',
						'Time filter default period' => '87601h',
						'Max period for time selector' => '87601h'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time in days.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '8d',
						'Time filter default period' => '3651d',
						'Max period for time selector' => '3651d'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time in weeks.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '2w',
						'Time filter default period' => '522w',
						'Max period for time selector' => '522w'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time in months.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Time filter default period' => '122M',
						'Max period for time selector' => '122M'
					],
					'details' => [
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time in years.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Time filter default period' => '11y',
						'Max period for time selector' => '11y'
					],
					'details' => [
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// In period fields maximal invalid time values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '99999999999999999999999999999999',
						'Time filter default period' => '99999999999999999999999999999999',
						'Max period for time selector' => '99999999999999999999999999999999'
					],
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			// Default period value larger than Max period.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Time filter default period' => '10y',
						'Max period for time selector' => '5y'
					],
					'details' => [
						'Incorrect value for field "period_default": time filter default period exceeds the max period.'
					]
				]
			],
			// Working time field values validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7 09:00-24:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '0-7,09:00-24:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-5,09:00-18:00,6-7,10:00-16:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-8,09:00-24:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,09:00-25:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
						'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,24:00-00:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,14:00-13:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,25:00-26:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,13:60-14:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-0'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '09:00-24:00'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '{WORKING_HOURS}'
					],
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			// Negative values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Limit for search and filter results' => '-1',
						'Max number of columns and rows in overview tables' => '-1',
						'Max count of elements to show inside table cell' => '-1',
						'Working time' => '-1',
						'Max history display period' => '-1',
						'Time filter default period' => '-1',
						'Max period for time selector' => '-1'
					],
					'details' => [
						'Incorrect value for field "search_limit": value must be no less than "1".',
						'Incorrect value for field "max_overview_table_size": value must be no less than "5".',
						'Incorrect value for field "max_in_table": value must be no less than "1".',
						'Incorrect value for field "work_period": a time period is expected.',
						'Incorrect value for field "history_period": a time unit is expected.',
						'Incorrect value for field "period_default": a time unit is expected.',
						'Incorrect value for field "max_period": a time unit is expected.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFormData
	 */
	public function testFormAdministrationGeneralGUI_CheckForm($data) {
		$this->executeCheckForm($data);
	}


	/**
	 * Test data for settings submit.
	 */
	public function getCheckSavedValuesData() {
		return [
			[
				[
					'field' =>  [
						'Default theme' => 'High-contrast dark'
					],
					'link' => 'templates.php?filter_name=cisco',
					'color' => 'rgba(224, 224, 224, 1)'
				]
			],
			[
				[
					'field' =>  [
						'Default theme' => 'High-contrast light'
					],
					'link' => 'templates.php?filter_name=cisco',
					'color' => 'rgba(85, 85, 85, 1)'
				]
			],
			[
				[
					'field' =>  [
						'Default theme' => 'Dark'
					],
					'link' => 'templates.php?filter_name=cisco',
					'color' => 'rgba(105, 128, 141, 1)'
				]
			],
			[
				[
					'field' =>  [
						'Limit for search and filter results' => '2'
					],
					'link' => 'templates.php?filter_name=cisco',
					'row_count' => 2
				]
			],
			[
				[
					'field' =>  [
						'Max count of elements to show inside table cell' => '2'
					],
					'link' => 'hostgroups.php?filter_name=Templates&filter_set=1',
					'element_count' => 2
				]
			],
			[
				[
					'field' =>  [
						'Time filter default period' => '5h'
					],
					'link' => 'zabbix.php?action=dashboard.view&dashboardid=2'
				]
			],
			[
				[
					'field' =>  [
						'Max period for time selector' => '1y'
					],
					'link' => 'zabbix.php?action=dashboard.view&dashboardid=2'
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckSavedValuesData
	 */
	public function testFormAdministrationGeneralGUI_CheckSavedValues($data) {
		$this->page->login()->open('zabbix.php?action=gui.edit');
		$form = $this->query($this->form_selector)->waitUntilReady()->asForm()->one();
		// Reset form in case of previous test case.
		$this->resetConfiguration($form, $this->default_values, 'Reset defaults');
		// Fill nesessary settings.
		$form->fill($data['field']);
		$form->submit();
		// Check saved settings.
		$this->page->open($data['link']);

		switch ((array_keys($data['field']))[0]) {
			case 'Default theme':
				$this->assertEquals($data['color'], $this->query('button:Import')->waitUntilPresent()
						->one()->getCSSValue('background-color'));
				break;

			case 'Limit for search and filter results':
			case 'Max number of columns and rows in overview tables':
				$table = $this->query('class:list-table')->waitUntilPresent()->asTable()->one();
				$this->assertEquals($data['row_count'], $table->getRows()->count());
				break;

			case 'Max count of elements to show inside table cell':
				$table = $this->query('class:list-table')->waitUntilPresent()->asTable()->one();
				$element_count = $table->findRow('Name', 'Templates/Applications')->getColumn('Members')
						->query('xpath:.//a[@class="link-alt grey"]')->all()->count();
				$this->assertEquals(CTestArrayHelper::get($data, 'element_count'), $element_count);
				break;

			case 'Time filter default period':
				$this->assertEquals('now-5h', $this->query('id:from')->one()->getValue());
				break;

			case 'Max period for time selector':
				$this->query('id:from')->one()->fill('now-5y');
				$this->query('button:Apply')->one()->click();
				// Days count for the case when current or past year is leap year.
				$days_count = CDateTimeHelper::countDays();
				$this->assertEquals('Maximum time period to display is '.$days_count.' days.',
						$this->query('class:time-input-error')->waitUntilVisible()->one()->getText());
				break;
		}
	}
}
