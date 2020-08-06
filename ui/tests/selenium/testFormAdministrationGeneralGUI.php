<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralGUI extends CWebTest {

	private $default = [
		'Default language' => 'English (en_GB)',
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
		'Max period' => '2y'
	];

	private $db_default = [
		'default_lang' => 'en_GB',
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

	private $custom = [
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
		'Max period' => '2y'
	];

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	public function testFormAdministrationGeneralGUI_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=gui.edit');
		$this->assertPageTitle('Configuration of GUI');
		$this->assertPageHeader('GUI');

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

		$this->query('xpath://span[@class="icon-info status-red"]')->one()->click();
		$this->assertEquals(
			'You are not able to choose some of the languages,'.
				' because locales for them are not installed on the web server.',
			$this->query('class:red')->one()->getText()
		);
	}

	public function testFormAdministrationGeneralGUI_SimpleUpdate() {
		$sqlHash = 'SELECT * FROM config ORDER BY configid';
		$oldHash = CDBHelper::getHash($sqlHash);

		$this->page->login()->open('zabbix.php?action=gui.edit');
		$form = $this->query('xpath://form[contains(@action, "gui.update")]')->waitUntilPresent()->asForm()->one();
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->assertEquals($oldHash, CDBHelper::getHash($sqlHash));
	}

	/**
	 * Test data for GUI form.
	 */
	public function getCheckFormData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Default language' => 'English (en_US)',
						'Default time zone' => 'Africa/Bissau',
						'Default theme' => 'Dark',
						'Limit for search and filter results' => '1',
						'Max number of columns and rows in overview tables' => '5',
						'Max count of elements to show inside table cell' => '1',
						'Show warning if Zabbix server is down' => false,
						'Working time' => '1-1,00:00-00:01',
						'Show technical errors' => true,
						// Minimal valid time in seconds with 's'.
						'Max history display period' => '86400s',
						'Time filter default period' => '60s',
						'Max period' => '31536000s'
					],
					'db' => [
						'default_lang' => 'en_US',
						'default_timezone' => 'Africa/Bissau',
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
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Working time' => '1-5,09:00-18:00;5-7,12:00-16:00',
						// Minimal valid time in seconds without 's'.
						'Max history display period' => '86400',
						'Time filter default period' => '60',
						'Max period' => '31536000'
					],
					'db' => [
						'work_period' => '1-5,09:00-18:00;5-7,12:00-16:00',
						'history_period' => '86400',
						'period_default' => '60',
						'max_period' => '31536000'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Minimal valid time in minutes.
						'Max history display period' => '1440m',
						'Time filter default period' => '1m',
						'Max period' => '525600m'
					],
					'db' => [
						'history_period' => '1440m',
						'period_default' => '1m',
						'max_period' => '525600m'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Minimal valid time in hours.
						'Max history display period' => '24h',
						'Max period' => '8760h'
					],
					'db' => [
						'history_period' => '24h',
						'max_period' => '8760h'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Minimal valid time in days.
						'Max history display period' => '1d',
						'Max period' => '365d'
					],
					'db' => [
						'history_period' => '1d',
						'max_period' => '365d'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Minimal valid time in weeks.
						'Max period' => '53w'
					],
					'db' => [
						'max_period' => '53w'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Minimal valid time in Months.
						'Max period' => '13M'
					],
					'db' => [
						'max_period' => '13M'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Minimal valid time in years.
						'Max period' => '1y'
					],
					'db' => [
						'max_period' => '1y'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Maximal valid time in seconds with 's'.
						'Max history display period' => '604800s',
						'Time filter default period' => '315360000s',
						'Max period' => '315360000s'
					],
					'db' => [
						'history_period' => '604800s',
						'period_default' => '315360000s',
						'max_period' => '315360000s'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Default time zone' => 'Europe/Riga',
						'Default theme' => 'High-contrast dark',
						// Maximal valid time in seconds without 's'.
						'Max history display period' => '604800',
						'Time filter default period' => '315360000',
						'Max period' => '315360000'
					],
					'db' => [
						'default_timezone' => 'Europe/Riga',
						'default_theme' => 'hc-dark',
						'history_period' => '604800',
						'period_default' => '315360000',
						'max_period' => '315360000'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Maximal valid time in minutes.
						'Max history display period' => '10080m',
						'Time filter default period' => '5256000m',
						'Max period' => '5256000m'
					],
					'db' => [
						'history_period' => '10080m',
						'period_default' => '5256000m',
						'max_period' => '5256000m'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Maximal valid time in days.
						'Max history display period' => '7d',
						'Time filter default period' => '3650d',
						'Max period' => '3650d'
					],
					'db' => [
						'history_period' => '7d',
						'period_default' => '3650d',
						'max_period' => '3650d'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Maximal valid time in weeks.
						'Max history display period' => '7d',
						'Time filter default period' => '3650d',
						'Max period' => '3650d'
					],
					'db' => [
						'history_period' => '7d',
						'period_default' => '3650d',
						'max_period' => '3650d'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Maximal valid time in Months.
						'Time filter default period' => '121M',
						'Max period' => '121M'
					],
					'db' => [
						'period_default' => '121M',
						'max_period' => '121M'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Default time zone' => 'UTC',
						'Default theme' => 'High-contrast light',
						'Limit for search and filter results' => '999999',
						'Max number of columns and rows in overview tables' => '999999',
						'Max count of elements to show inside table cell' => '99999',
						'Working time' => '{$WORKING_HOURS}',
						// Maximal valid time in years.
						'Time filter default period' => '10y',
						'Max period' => '10y'
					],
					'db' => [
						'default_timezone' => 'UTC',
						'default_theme' => 'hc-light',
						'search_limit' => 999999,
						'max_overview_table_size' => 999999,
						'max_in_table' => 99999,
						'work_period' => '{$WORKING_HOURS}',
						'period_default' => '10y',
						'max_period' => '10y'
					],
					'message' => 'Configuration updated'
				]
			],
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
						'Max period' => '0'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value "0" for "search_limit" field.',
						'Incorrect value "0" for "max_overview_table_size" field.',
						'Incorrect value "0" for "max_in_table" field.',
						'Incorrect value for field "work_period": a time period is expected.',
						'Incorrect value for field "history_period": value must be one of 86400-604800.',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
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
						'Max period' => ''
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value "0" for "search_limit" field.',
						'Incorrect value "0" for "max_overview_table_size" field.',
						'Incorrect value "0" for "max_in_table" field.',
						'Incorrect value for field "work_period": a time period is expected.',
						'Incorrect value for field "history_period": a time unit is expected.',
						'Incorrect value for field "period_default": a time unit is expected.',
						'Incorrect value for field "max_period": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max number of columns and rows in overview tables' => '4',
						'Working time' => 'test',
						// Minimal invalid time in seconds with "s".
						'Max history display period' => '86399s',
						'Time filter default period' => '59s',
						'Max period' => '31535999s'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value "4" for "max_overview_table_size" field.',
						'Incorrect value for field "work_period": a time period is expected.',
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Minimal invalid time in seconds without "s".
						'Max history display period' => '86399',
						'Time filter default period' => '59',
						'Max period' => '31535999'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Minimal invalid time in minutes.
						'Max history display period' => '1439m',
						'Time filter default period' => '0m',
						'Max period' => '525599m'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Minimal invalid time in hours.
						'Max history display period' => '23h',
						'Time filter default period' => '0h',
						'Max period' => '8759h'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Minimal invalid time in weeks.
						'Max history display period' => '0w',
						'Time filter default period' => '0w',
						'Max period' => '52w'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Minimal invalid time in Month.
						'Max history display period' => '0M',
						'Time filter default period' => '0M',
						'Max period' => '12M'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": a time unit is expected.',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Minimal invalid time in years.
						'Time filter default period' => '0y',
						'Max period' => '0y'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Maximal invalid time in seconds without 's'.
						'Max history display period' => '604801',
						'Time filter default period' => '315360001',
						'Max period' => '315360001'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Maximal invalid time in seconds with 's'.
						'Max history display period' => '604801s',
						'Time filter default period' => '315360001s',
						'Max period' => '315360001s'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Maximal invalid time in minutes.
						'Max history display period' => '10081m',
						'Time filter default period' => '5256001m',
						'Max period' => '5256001m'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [

						// Maximal invalid time in hours.
						'Max history display period' => '169h',
						'Time filter default period' => '87601h',
						'Max period' => '87601h'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Maximal invalid time in days.
						'Max history display period' => '8d',
						'Time filter default period' => '3651d',
						'Max period' => '3651d'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Maximal invalid time in weeks.
						'Max history display period' => '2w',
						'Time filter default period' => '522w',
						'Max period' => '522w'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Maximal invalid time in months.
						'Time filter default period' => '122M',
						'Max period' => '122M'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Maximal invalid time in years.
						'Time filter default period' => '11y',
						'Max period' => '11y'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Max history display period' => '99999999999999999999999999999999',
						'Time filter default period' => '99999999999999999999999999999999',
						'Max period' => '99999999999999999999999999999999'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "history_period": value must be one of 86400-604800',
						'Incorrect value for field "period_default": value must be one of 60-315360000.',
						'Incorrect value for field "max_period": value must be one of 31536000-315360000.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Time filter default period' => '10y',
						'Max period' => '5y'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "period_default": time filter default period exceeds the max period.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7 09:00-24:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '0-7,09:00-24:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-5,09:00-18:00,6-7,10:00-16:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-8,09:00-24:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,09:00-25:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
						'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,24:00-00:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,14:00-13:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,25:00-26:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-7,13:60-14:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '1-0'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '09:00-24:00'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Working time' => '{WORKING_HOURS}'
					],
					'message' => 'Cannot update configuration',
					'details' => 'Incorrect value for field "work_period": a time period is expected.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFormData
	 */
	public function testFormAdministrationGeneralGUI_CheckForm($data) {
		$this->page->login()->open('zabbix.php?action=gui.edit');
		$form = $this->query('xpath://form[contains(@action, "gui.update")]')->waitUntilPresent()->asForm()->one();
		// Reset form in case of previous test case.
		$this->resetConfiguration($form, $this->default, 'Reset defaults');
		// Fill form with new data.
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage($data['expected'], $data['message'], CTestArrayHelper::get($data, 'details'));
		// Check saved configuration in frontend.
		$this->page->open('zabbix.php?action=gui.edit');
		$form->invalidate();
		$values = (CTestArrayHelper::get($data, 'expected')) === TEST_GOOD
			? $data['fields']
			: $this->default;
		$form->checkValue($values);
		// Check saved configuration in database.
		$sql = CDBHelper::getRow('SELECT * FROM config');
		$db = (CTestArrayHelper::get($data, 'expected')) === TEST_GOOD
			? CTestArrayHelper::get($data, 'db', [])
			: $this->db_default;
		foreach ($db as $key => $value) {
			$this->assertArrayHasKey($key, $sql);
			$this->assertEquals($value, $sql[$key]);
		}
	}

	public function getResetButtonData() {
		return [
			[
				[
					'action' => 'Reset defaults',
				]
			],
			[
				[
					'action' => 'Cancel',
				]
			]
		];
	}

	/**
	 * @dataProvider getResetButtonData
	 */
	public function testFormAdministrationGeneralGUI_ResetButton($data) {
		$this->page->login()->open('zabbix.php?action=gui.edit');
		$form = $this->query('xpath://form[contains(@action, "gui.update")]')->waitUntilPresent()->asForm()->one();
		// Reset form in case of some previous scenario.
		$this->resetConfiguration($form, $this->default, 'Reset defaults');
		$default_sql = CDBHelper::getRow('SELECT * FROM config');
		// Fill form with custom data.
		$form->fill($this->custom);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$custom_sql = CDBHelper::getRow('SELECT * FROM config');
		// Check custom data in form.
		$this->page->refresh();
		$form->invalidate();
		$form->checkValue($this->custom);
		// Reset form after customly filled data and check that values are reset to default or reset is cancelled.
		$this->resetConfiguration($form, $this->default, $data['action'],  $this->custom);
		$sql = ($data['action'] === 'Reset defaults') ? $default_sql : $custom_sql;
		$this->assertEquals($sql, CDBHelper::getRow('SELECT * FROM config'));
	}

	private function resetConfiguration($form, $default, $action, $custom = null) {
		$form->query('button:Reset defaults')->one()->click();
		COverlayDialogElement::find()->waitUntilPresent()->one()->query('button', $action)->one()->click();
		switch ($action) {
			case 'Reset defaults':
				$form->submit();
				$this->assertMessage(TEST_GOOD, 'Configuration updated');
				$this->page->refresh();
				$form->invalidate();
				// Check reset form.
				$form->checkValue($default);
				break;
			case 'Cancel':
				$form->checkValue($custom);
				break;
		}
	}
}
