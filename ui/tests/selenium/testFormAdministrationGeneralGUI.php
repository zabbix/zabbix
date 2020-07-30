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
require_once dirname(__FILE__).'/behaviors/MessageBehavior.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralGUI extends CWebTest {

	private $default = [
		'Default language' => 'English (en_GB)',
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

	/**
	 * Attach MessageBehavior to the test.details
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
//			'history_period' =>
//			'period_default' =>
//			'max_period' =>
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
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Minimal valid time in seconds without 's'.
						'Max history display period' => '86400',
						'Time filter default period' => '60',
						'Max period' => '31536000'
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
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Maximal valid time in seconds without 's'.
						'Max history display period' => '604800',
						'Time filter default period' => '315360000',
						'Max period' => '315360000'
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
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Default language' => 'English (en_US)',
						'Default theme' => 'High-contrast light',
						'Limit for search and filter results' => '999999',
						'Max number of columns and rows in overview tables' => '999999',
						'Max count of elements to show inside table cell' => '99999',
						'Working time' => '7-7,23:59-24:00',
						// Maximal valid time in years.
						'Time filter default period' => '10y',
						'Max period' => '10y'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Default language' => 'English (en_US)',
						'Default theme' => 'High-contrast dark',
						'Max history display period' => '604800',
						'Time filter default period' => '315360000',
						'Max period' => '315360000'
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
						'Working time' => '0-7,09:00-18:00',
						'Max history display period' => '23h',
						'Time filter default period' => '59s',
						'Max period' => '364d'
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
						'Time filter default period' => '10y',
						'Max period' => '5y'
					],
					'message' => 'Cannot update configuration',
					'details' => [
						'Incorrect value for field "period_default": time filter default period exceeds the max period.'
					]
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
		$this->resetConfiguration($form, $this->default);
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage($data['expected'], $data['message'], CTestArrayHelper::get($data, 'details'));
		$this->page->open('zabbix.php?action=gui.edit');
		$form->invalidate();
		$values = CTestArrayHelper::get($data, 'expected') === TEST_GOOD
			? $data['fields']
			: $this->default;
		$form->checkValue($values);
	}

	public function testFormAdministrationGeneralGUI_ResetButton() {
		$custom = [
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

		$this->page->login()->open('zabbix.php?action=gui.edit');
		$form = $this->query('xpath://form[contains(@action, "gui.update")]')->waitUntilPresent()->asForm()->one();
		// Reset form in case of some previous scenario.
		$this->resetConfiguration($form, $this->default);
		// Fill form with custom data.
		$form->fill($custom);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		// Check custom data in  form.
		$this->page->refresh();
		$form->invalidate();
		$form->checkValue($custom);
		// Reset form after customly filled data and check that values are reset to default.
		$this->resetConfiguration($form, $this->default);
	}

	private function resetConfiguration($form, $default) {
		$form->query('button:Reset defaults')->one()->click();
		COverlayDialogElement::find()->waitUntilPresent()->one()->query('button:Reset defaults')->one()->click();
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->page->refresh();
		$form->invalidate();
		// Check reset form.
		$form->checkValue($default);
	}
}
