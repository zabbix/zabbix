<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

class testPageLatestData extends CLegacyWebTest {
	public function testPageLatestData_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=latest.view');
		$this->zbxTestCheckTitle('Latest data');
		$this->zbxTestCheckHeader('Latest data');
		$this->zbxTestTextPresent(['Host groups', 'Hosts', 'Application', 'Name', 'Show items without data', 'Show details']);
		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent(['Host', 'Name', 'Last check', 'Last value', 'Change']);
	}

	// Check that no real host or template names displayed
	public function testPageLatestData_NoHostNames() {
		$this->zbxTestLogin('zabbix.php?action=latest.view');
		$this->zbxTestCheckTitle('Latest data');
		$this->zbxTestCheckNoRealHostnames();
	}

	public static function getItemDescription() {
		return [
			// Item without description.
			[
				[
					'Item name' => 'item_testPageHistory_CheckLayout_Log'
				]
			],
			// Item with plain text in the description.
			[
				[
					'Item name' => 'item_testPageHistory_CheckLayout_Log_2',
					'description' => 'Non-clickable description'
				]
			],
			// Item with only 1 url in description.
			[
				[
					'Item name' => 'item_testPageHistory_CheckLayout_Eventlog',
					'description' => 'https://zabbix.com'
				]
			],
			// Item with text and url in description.
			[
				[
					'Item name' => 'item_testPageHistory_CheckLayout_Eventlog_2',
					'description' => 'The following url should be clickable: https://zabbix.com'
				]
			],
			// Item with multiple urls in description.
			[
				[
					'Item name' => 'item_testPageHistory_CheckLayout_Character',
					'description' => 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact'
				]
			],
			// Item with text and 2 urls in description.
			[
				[
					'Item name' => 'item_testPageHistory_CheckLayout_Text',
					'description' => 'These urls should be clickable: https://zabbix.com https://www.zabbix.com/career'
				]
			]
		];
	}

	/**
	 * @dataProvider getItemDescription
	 */
	public function testPageLatestData_checkItemDescription($data) {
		// Open Latest data for host 'testPageHistory_CheckLayout'
		$this->page->login()->open('zabbix.php?action=latest.view&filter_hostids%5B%5D=15003&filter_application=&filter_select=&filter_show_without_data=1&filter_set=1');
		$table = $this->query('class:list-table')->asTable()->one();

		// Find rows from the data provider and click on the description icon if such should persist.
		$row = $table->findRow('Name', $data['Item name'], true);

		if (CTestArrayHelper::get($data,'description', false)) {
			$row->query('class:icon-description')->one()->click()->waitUntilReady();
			$overlay = $this->query('xpath://div[@class="overlay-dialogue"]')->one();

			// Verify the real description with the expected one.
			$this->assertEquals($data['description'], $overlay->getText());

			// Get urls form description.
			$urls = [];
			preg_match_all('/https?:\/\/\S+/', $data['description'], $urls);
			// Verify that each of the urls is clickable.
			foreach ($urls[0] as $url) {
				$this->assertTrue($overlay->query('xpath:./div/a[@href="'.$url.'"]')->one()->isClickable());
			}

			// Verify that the tool-tip can be closed.
			$overlay->query('xpath:./button[@title="Close"]')->one()->click();
			$this->assertFalse($overlay->isDisplayed());
		}
		// If the item has no description the description icon should not be there.
		else {
			$this->assertTrue($row->query('class:icon-description')->count() === 0);
		}
	}
}
