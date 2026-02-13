<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__.'/../../include/CWebTest.php';

/**
 * @dataSource MonitoringOverview
 *
 * @onBefore prepareData
 */
class testPageTriggerDescription extends CWebTest {

	protected static $triggerids;
	protected static $eventids;

	public static function prepareData() {
		self::$triggerids = CDataHelper::get('MonitoringOverview.triggerids');
		self::$eventids = CDataHelper::get('MonitoringOverview.eventids');
	}

	public static function getTriggerDescription() {
		return [
			// Trigger without description.
			[
				[
					'Event name' => '1_trigger_Disaster'
				]
			],
			// Trigger with plain text in the description.
			[
				[
					'Event name' => '1_trigger_High',
					'description' => 'Non-clickable description'
				]
			],
			// Trigger with only 1 url in description.
			[
				[
					'Event name' => '1_trigger_Average',
					'description' => 'https://zabbix.com'
				]
			],
			// Trigger with text and url in description.
			[
				[
					'Event name' => '1_trigger_Warning',
					'description' => 'The following url should be clickable: https://zabbix.com'
				]
			],
			// Trigger with multiple urls in description.
			[
				[
					'Event name' => '2_trigger_Information',
					'description' => 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact'
				]
			],
			// Trigger with macro in description.
			[
				[
					'Event name' => '1_trigger_Not_classified',
					'description' => 'Macro should be resolved, host IP should be visible here: 127.0.0.1'
				]
			],
			// Trigger with url and macro in description.
			[
				[
					'Event name' => '3_trigger_Average',
					'description' => 'Macro - resolved, URL - clickable: 3_Host_to_check_Monitoring_Overview, https://zabbix.com'
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
		$row = $table->findRow('Problem', $data['Event name'], true);

		if (CTestArrayHelper::get($data, 'description', false)) {
			$row->query('xpath:.//button['.CXPathHelper::fromClass('zi-alert-with-content').']')->one()->click();
			$overlay = $this->query('xpath://div[contains(@class, "hintbox-static")]')->asOverlayDialog()->one()->waitUntilReady();
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

		// Check trigger description in event details of the corresponding problem.
		$row->getColumn('Time')->query('xpath:./a')->one()->click();
		$this->page->waitUntilReady();

		// Check the URL of the opened page to make sure that correct event is opened.
		$this->assertStringContainsString('tr_events.php?triggerid='.self::$triggerids[$data['Event name']].
				'&eventid='.self::$eventids[$data['Event name']], $this->page->getCurrentURL()
		);
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
		// Take the urls out of description text to process them separately.
		$urls = [];
		preg_match_all('/https?:\/\/\S+/', $data['description'], $urls);

		// Check that urls are clickable.
		foreach ($urls[0] as $url) {
			$this->assertTrue($element->query('xpath:./div/a[@href="'.$url.'"]')->one()->isClickable());
		}
	}
}
