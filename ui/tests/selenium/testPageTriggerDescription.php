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

require_once dirname(__FILE__).'/../include/CWebTest.php';

class testPageTriggerDescription extends CWebTest {

	public static function getTriggerDescription() {
		return [
			// Trigger without description.
			[
				[
					'Trigger name' => '1_trigger_Disaster',
					'event_url' => 'tr_events.php?triggerid=100036&eventid=9004'
				]
			],
			// Trigger with plain text in the description.
			[
				[
					'Trigger name' => '1_trigger_High',
					'description' => 'Non-clickable description',
					'event_url' => 'tr_events.php?triggerid=100035&eventid=9003'
				]
			],
			// Trigger with only 1 url in description.
			[
				[
					'Trigger name' => '1_trigger_Average',
					'description' => 'https://zabbix.com',
					'event_url' => 'tr_events.php?triggerid=100034&eventid=9002'
				]
			],
			// Trigger with text and url in description.
			[
				[
					'Trigger name' => '1_trigger_Warning',
					'description' => 'The following url should be clickable: https://zabbix.com',
					'event_url' => 'tr_events.php?triggerid=100033&eventid=9001'
				]
			],
			// Trigger with multiple urls in description.
			[
				[
					'Trigger name' => '2_trigger_Information',
					'description' => 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact',
					'event_url' => 'tr_events.php?triggerid=100037&eventid=9005'
				]
			],
			// Trigger with macro in description.
			[
				[
					'Trigger name' => '1_trigger_Not_classified',
					'description' => 'Macro should be resolved, host IP should be visible here: 127.0.0.1',
					'event_url' => 'tr_events.php?triggerid=100032&eventid=9000'
				]
			],
			// Trigger with url and macro in description.
			[
				[
					'Trigger name' => '3_trigger_Average',
					'description' => 'Macro - resolved, URL - clickable: 3_Host_to_check_Monitoring_Overview, https://zabbix.com',
					'event_url' => 'tr_events.php?triggerid=100038&eventid=9006'
				]
			]
		];
	}

	/**
	 * @dataProvider getTriggerDescription
	 */
	public function testPageTriggerDescription_ProblemDescription($data) {
		$this->page->login()->open('zabbix.php?action=problem.view');

		// Find rows from the data provider and check the description if such should exist.
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Problem', $data['Trigger name'], true);

		if (CTestArrayHelper::get($data, 'description', false)) {
			$row->query('xpath:.//a[contains(@class, "icon-description")]')->one()->click();
			$overlay = $this->query('xpath://div[@class="overlay-dialogue"]')->asOverlayDialog()->one()->waitUntilReady();
			$this->assertEquals($data['description'], $overlay->getText());

			// Check urls in description.
			$this->checkDescriptionUrls($data, $overlay);

			// Close the tool-tip.
			$overlay->close();
		}
		// Check that there is no description icon if such is not specified if trigger config.
		else {
			$this->assertTrue($row->query('class:icon-description')->count() === 0);
		}

		// Check trigger description in event details of the correspondign problem.
		$row->getColumn('Time')->query('xpath:./a')->one()->click();
		$this->page->waitUntilReady();

		// Check the URL of the opened page to make sure that correct event is opened.
		$this->assertStringContainsString($data['event_url'], $this->page->getCurrentURL());
		// Find the row that contains trigger description and select the column that holds the value of description field.
		$description = $this->query('xpath://td[text()="Description"]/..')->one()->asTableRow()->getColumn(1);

		// Check description value.
		if (CTestArrayHelper::get($data, 'description', false)) {
			$this->assertEquals($data['description'], $description->getText());
			// Check URLs in description.
			$this->checkDescriptionUrls($data, $description);
		}
		// Check that description field is empty if the trigger doesn't have a description.
		else {
			$this->assertEquals('', $description->getText());
		}
	}

	private function checkDescriptionUrls($data, $element) {
		// Take the urls out of description text to process them separatelly.
		$urls = [];
		preg_match_all('/https?:\/\/\S+/', $data['description'], $urls);

		// Check that urls are clickable.
		foreach ($urls[0] as $url) {
			$this->assertTrue($element->query('xpath:./div/a[@href="'.$url.'"]')->one()->isClickable());
		}
	}
}
