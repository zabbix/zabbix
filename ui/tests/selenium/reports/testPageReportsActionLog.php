<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup alerts
 *
 * @dataSource LoginUsers, Actions
 */
class testPageReportsActionLog extends CWebTest {

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	public function testPageReportsActionLog_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=actionlog.list')->waitUntilReady();

		// Check header and title.
		$this->page->assertHeader('Action log');
		$this->page->assertTitle('Action log');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// If the time selector is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-1" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-1')->one()->click();
		}

		// Check that filter set to display Last hour data.
		$this->assertEquals('selected', $this->query('xpath://a[@data-label="Last 1 hour"]')->one()
			->getAttribute('class')
		);

		// Check data set values in input field.
		$this->assertEquals('now-1h', $this->query('id:from')->one()->getValue());
		$this->assertEquals('now', $this->query('id:to')->one()->getValue());

		// Press to display filter.
		$this->query('id:ui-id-2')->one()->click();

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('xpath:.//div[@class="filter-forms"]/button[text()="'.$button.'"]')
					->one()->isClickable()
			);
		}

		// Check that Export to CSV button is clickable.
		$this->assertTrue($this->query('button:Export to CSV')->one()->isClickable());

		// Check form labels.
		$this->assertEquals(['Recipients', 'Actions', 'Media types', 'Status', 'Search string'], $form->getLabels()->asText());

		// Check Search string field max length.
		$this->assertEquals(255, $form->getField('Search string')->waitUntilVisible()->getAttribute('maxlength'));

		// Check table headers.
		$this->assertEquals(['Time', 'Action', 'Media type', 'Recipient', 'Message', 'Status', 'Info'],
				$this->query('class:list-table')->asTable()->one()->getHeadersText()
		);

		// Check status available values.
		$this->assertEquals(['In progress', 'Sent/Executed', 'Failed'], $this->query('id:filter_status')
				->asCheckboxList()->one()->getLabels()->asText()
		);
	}

	public static function getCheckFilterData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Recipients' => ['test-timezone']
					],
					'result' => [
						['Recipient' => "test-timezone\n77777777"],
						['Recipient' => "test-timezone\n77777777"]
					]
				]
			],
			// #1.
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 2']
					],
					'result' => [
						['Action' => 'Trigger action 2'],
						['Action' => 'Trigger action 2'],
						['Action' => 'Trigger action 2'],
						['Action' => 'Trigger action 2'],
						['Action' => 'Trigger action 2'],
						['Action' => 'Trigger action 2'],
						['Action' => 'Trigger action 2']
					]
				]
			],
			// #2.
			[
				[
					'fields' => [
						'Actions' => ['Simple action']
					],
					'result' => []
				]
			],
			// #3.
			[
				[
					'fields' => [
						'Recipients' => ['filter-update']
					],
					'result' => []
				]
			],
			// #4.
			[
				[
					'fields' => [
						'Media types' => ['Discord']
					],
					'result' => [
						['Media type' => 'Discord']
					]
				]
			],
			// #5.
			[
				[
					'fields' => [
						'Media types' => ['Discord', 'SMS']
					],
					'result' => [
						['Media type' => 'SMS'],
						['Media type' => 'SMS'],
						['Media type' => 'Discord']
					]
				]
			],
			// #6.
			[
				[
					'fields' => [
						'Media types' => ['Github']
					],
					'result' => []
				]
			],
			// #7.
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 2'],
						'Media types' => ['Discord']
					],
					'result' => []
				]
			],
			// #8.
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 3'],
						'Media types' => ['Discord']
					],
					'result' => [
						['Action' => 'Trigger action 3', 'Media type' => 'Discord']
					]
				]
			],
			// #9.
			[
				[
					'fields' => [
						'Recipients' => ['Administrator'],
						'Actions' => ['Trigger action 3']
					],
					'result' => [
						['Action' => 'Trigger action 3', 'Recipient' => "Admin (Zabbix Administrator)\ntest.test@zabbix.com"]
					]
				]
			],
			// #10.
			[
				[
					'fields' => [
						'Recipients' => ['Administrator'],
						'Media types' => ['Email']
					],
					'result' => [
						['Media type' => 'Email', 'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"],
						['Media type' => 'Email', 'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"],
						['Media type' => 'Email', 'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"],
						['Media type' => 'Email', 'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"],
						['Media type' => 'Email', 'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"]
					]
				]
			],
			// #11.
			[
				[
					'fields' => [
						'Recipients' => ['Administrator', 'test-timezone'],
						'Media types' => ['Email', 'Discord', 'SMS'],
						'Actions' => ['Trigger action 2', 'Trigger action 3']
					],
					'result' => [
						[
							'Action' => 'Trigger action 3',
							'Media type' => 'SMS',
							'Recipient' => "test-timezone\n77777777"
						],
						[
							'Action' => 'Trigger action 3',
							'Media type' => 'SMS',
							'Recipient' => "test-timezone\n77777777"
						],
						[
							'Action' => 'Trigger action 3',
							'Media type' => 'Discord',
							'Recipient' => "Admin (Zabbix Administrator)\ntest.test@zabbix.com"
						],
						[
							'Action' => 'Trigger action 2',
							'Media type' => 'Email',
							'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"
						],
						[
							'Action' => 'Trigger action 2',
							'Media type' => 'Email',
							'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"
						],
						[
							'Action' => 'Trigger action 2',
							'Media type' => 'Email',
							'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"
						],
						[
							'Action' => 'Trigger action 2',
							'Media type' => 'Email',
							'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"
						],
						[
							'Action' => 'Trigger action 2',
							'Media type' => 'Email',
							'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com"
						]
					]
				]
			],
			// #12.
			[
				[
					'fields' => ['Failed' => true],
					'result' => [
						['Status' => 'Failed']
					]
				]
			],
			// #13.
			[
				[
					'fields' => ['Sent/Executed' => true],
					'result' => [
						['Status' => 'Sent'],
						['Status' => 'Sent'],
						['Status' => 'Sent'],
						['Status' => 'Executed'],
						['Status' => 'Executed'],
						['Status' => 'Sent'],
						['Status' => 'Sent'],
						['Status' => 'Sent']
					]
				]
			],
			// #14.
			[
				[
					'fields' => ['In progress' => true],
					'result' => [
						['Status' => "In progress:\n3 retries left"]
					]
				]
			],
			// #15.
			[
				[
					'fields' => [
						'Failed' => true,
						'In progress' => true
					],
					'result' => [
						['Status' => "In progress:\n3 retries left"],
						['Status' => 'Failed']
					]
				]
			],
			// #16.
			[
				[
					'fields' => [
						'In progress' => true,
						'Sent/Executed' => true,
						'Failed' => true
					],
					'result' => [
						['Status' => 'Sent'],
						['Status' => 'Sent'],
						['Status' => 'Sent'],
						['Status' => 'Executed'],
						['Status' => 'Executed'],
						['Status' => "In progress:\n3 retries left"],
						['Status' => 'Failed'],
						['Status' => 'Sent'],
						['Status' => 'Sent'],
						['Status' => 'Sent']
					]
				]
			],
			// #17.
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 3'],
						'Sent/Executed' => true
					],
					'result' => [
						['Action' => 'Trigger action 3', 'Status' => 'Sent'],
						['Action' => 'Trigger action 3', 'Status' => 'Sent'],
						['Action' => 'Trigger action 3', 'Status' => 'Sent']
					]
				]
			],
			// #18.
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 3'],
						'In progress' => true
					],
					'result' => []
				]
			],
			// #19.
			[
				[
					'fields' => [
						'Recipients' => ['Administrator', 'test-timezone'],
						'In progress' => true
					],
					'result' => [
						[
							'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com",
							'Status' => "In progress:\n3 retries left"
						]
					]
				]
			],
			// #20.
			[
				[
					'fields' => [
						'Recipients' => ['test-timezone'],
						'In progress' => true
					],
					'result' => []
				]
			],
			// #21.
			[
				[
					'fields' => [
						'Media types' => ['Email'],
						'In progress' => true
					],
					'result' => [
						['Media type' => 'Email', 'Status' => "In progress:\n3 retries left"]
					]
				]
			],
			// #22.
			[
				[
					'fields' => [
						'Media types' => ['Email'],
						'Sent/Executed' => true
					],
					'result' => [
						['Media type' => 'Email', 'Status' => 'Sent'],
						['Media type' => 'Email', 'Status' => 'Sent'],
						['Media type' => 'Email', 'Status' => 'Sent']
					]
				]
			],
			// #23.
			[
				[
					'fields' => [
						'Media types' => ['Email'],
						'Failed' => true
					],
					'result' => [
						['Media type' => 'Email', 'Status' => 'Failed']
					]
				]
			],
			// #24.
			[
				[
					'fields' => [
						'Media types' => ['Email'],
						'Actions' => ['Trigger action 2'],
						'Recipients' => ['Administrator'],
						'Sent/Executed' => true
					],
					'result' => [
						[
							'Action' => 'Trigger action 2', 'Media type' => 'Email',
								'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com", 'Status' => 'Sent'
						],
						[
							'Action' => 'Trigger action 2', 'Media type' => 'Email',
								'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com", 'Status' => 'Sent'
						],
						[
							'Action' => 'Trigger action 2', 'Media type' => 'Email',
								'Recipient' => "Admin (Zabbix Administrator)\nigor.danoshaites@zabbix.com", 'Status' => 'Sent'
						]
					]
				]
			],
			// #25.
			[
				[
					'fields' => [
						'Search string' => 'test'
					],
					'result' => []
				]
			],
			// #26.
			[
				[
					'fields' => [
						'Search string' => '10:00:40'
					],
					'result' => [
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 20\n\nMessage:\nEvent ".
									"at 2012.02.20 10:00:40 Hostname: H1 Value of item key1 > 20: PROBLEM"
						]
					]
				]
			],
			// #27.
			[
				[
					'fields' => [
						'Search string' => '.'
					],
					'result' => [
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 20\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:40 Hostname: H1 Value of item key1 > 20: PROBLEM"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 10\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:30 Hostname: H1 Value of item key1 > 10: PROBLEM"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 7\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:20 Hostname: H1 Value of item key1 > 7: PROBLEM"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 6\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:10 Hostname: H1 Value of item key1 > 6: PROBLEM"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 5\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:00 Hostname: H1 Value of item key1 > 5: PROBLEM Last value: 6"
						]
					]
				]
			],
			// #28.
			[
				[
					'fields' => [
						'Search string' => '5'
					],
					'result' => [
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 5\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:00 Hostname: H1 Value of item key1 > 5: PROBLEM Last value: 6"
						]
					]
				]
			],
			// #29.
			[
				[
					'result' => [
						['Time' => '2012-02-20 10:01:30'],
						['Time' => '2012-02-20 10:01:20'],
						['Time' => '2012-02-20 10:01:10'],
						['Time' => '2012-02-20 10:01:00'],
						['Time' => '2012-02-20 10:00:50'],
						['Time' => '2012-02-20 10:00:40'],
						['Time' => '2012-02-20 10:00:30'],
						['Time' => '2012-02-20 10:00:20'],
						['Time' => '2012-02-20 10:00:10'],
						['Time' => '2012-02-20 10:00:00']
					]
				]
			],
			// #30.
			[
				[
					'fields' => [
						'Search string' => ' '
					],
					'result' => [
						[
							'Message' => "Subject:\nsubject here\n\nMessage:\nmessage here"
						],
						[
							'Message' => "Subject:\nsubject here\n\nMessage:\nmessage here"
						],
						[
							'Message' => "Command:\nCommand: H1:ls -la"
						],
						[
							'Message' => "Command:\nCommand: H1:ls -la"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 20\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:40 Hostname: H1 Value of item key1 > 20: PROBLEM"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 10\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:30 Hostname: H1 Value of item key1 > 10: PROBLEM"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 7\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:20 Hostname: H1 Value of item key1 > 7: PROBLEM"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 6\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:10 Hostname: H1 Value of item key1 > 6: PROBLEM"
						],
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 5\n\nMessage:\nEvent at 2012.02.20 ".
									"10:00:00 Hostname: H1 Value of item key1 > 5: PROBLEM Last value: 6"
						]
					]
				]
			],
			// #31.
			[
				[
					'fields' => [
						'Search string' => '     '
					],
					'result' => []
				]
			],
			// #32.
			[
				[
					'fields' => [
						'Search string' => 'Event at 2012.02.20 10:00:30 Hostname: H1 Value of item key1 > 10: PROBLEM'
					],
					'result' => [
						[
							'Message' => "Subject:\nPROBLEM: Value of item key1 > 10\n\nMessage:\nEvent at 2012.02.20 ".
								"10:00:30 Hostname: H1 Value of item key1 > 10: PROBLEM"
						]
					],
					'result_count' => 1
				]
			],
			// #33.
			[
				[
					'fields' => [
						'Search string' => '2012-02-20 10:01:00'
					],
					'result' => []
				]
			],
			// 34.
			[
				[
					'time' => [
						'id:from' => '2020-08-15 00:00:00',
						'id:to' => '2020-08-15 01:0:00'
					],
					'result' => [
						['Time' => '2020-08-15 00:20:00'],
						['Time' => '2020-08-15 00:10:00']
					]
				]
			],
			// 35.
			[
				[
					'fields' => [
						'Actions' => 'Trigger action 2'
					],
					'time' => [
						'id:from' => '2020-08-15 00:00:00',
						'id:to' => '2020-08-15 01:0:00'
					],
					'result' => [
						['Time' => '2020-08-15 00:20:00', 'Action' => 'Trigger action 2']
					]
				]
			]
		];
	}

	/**
	 * Filter action log and check results.
	 *
	 * @dataProvider getCheckFilterData
	 */
	public function testPageReportsActionLog_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=actionlog.list&from=2012-02-20+09:01:00&to=2012-02-20+11:01:00&'.
				'filter_messages=&filter_set=1')->waitUntilReady();

		// Filter by time.
		$form = $this->query('name:zbx_filter')->asForm()->one();

		if (array_key_exists('time', $data)) {
			// Enable time tab.
			$time_tab = $this->query('xpath:.//li[@aria-labelledby="ui-id-1" and @aria-selected="false"]');
			if ($time_tab->exists()) {
				$time_tab->one()->click();
			}

			$this->query('id:from')->one()->fill($data['time']['id:from']);
			$this->query('id:to')->one()->fill($data['time']['id:to']);
			$this->query('id:apply')->one()->click();
			$this->page->waitUntilReady();
		}

		// Fill filter.
		$filter_tab = $this->query('xpath:.//li[@aria-labelledby="ui-id-2" and @aria-selected="false"]');

		if (array_key_exists('fields', $data)) {
			// If the filter is not visible - enable it.
			if ($filter_tab->exists()) {
				$filter_tab->one()->click();
			}

			$form->fill($data['fields'])->submit();
		}

		$this->page->waitUntilReady();
		$this->assertTableHasData($data['result']);
		$this->assertTableStats(count($data['result']));

		// Reset filter for the next checks.
		if ($filter_tab->exists()) {
			$filter_tab->one()->click();
		}

		$form->query('button:Reset')->one()->click();
	}

	/**
	 * Check Status column colors and Info column hintbox.
	 */
	public function testPageReportsActionLog_CheckStatusInfo() {
		$this->page->login()->open('zabbix.php?action=actionlog.list&from=2012-02-20+09:01:00&to=2012-02-20+11:01:00&'.
				'filter_messages=&filter_set=1')->waitUntilReady();

		// Check status color correctness.
		$table = $this->query('class:list-table')->asTable()->one();
		$statuses = ['Executed' => 'green', 'In progress:' => 'yellow', 'Failed' => 'red', 'Sent' => 'green'];

		foreach ($statuses as $status => $color) {
			$this->assertEquals($color, $table->query("xpath:(//td/span[text()=".CXPathHelper::escapeQuotes($status)."])[1]")
					->one()->getAttribute('class')
			);
		}

		// Check hintbox.
		$table->findRow('Status', 'Failed')->getColumn('Info')->query("xpath:.//button[@data-hintbox]")
				->waitUntilClickable()->one()->click();
		$hintbox = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent();
		$this->assertEquals('Get value from agent failed: cannot connect to [[127.0.0.1]:10050]: [111] Connection refused',
				$hintbox->one()->getText()
		);

		// Close hintbox.
		$hintbox->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click()->waitUntilNotPresent();
	}

	/**
	 * Check Reset button.
	 */
	public function testPageReportsActionLog_CheckResetButton() {
		$this->page->login()->open('zabbix.php?action=actionlog.list&from=2012-02-20+09:01:00&to=2012-02-20+11:01:00&'.
				'filter_messages=&filter_set=1')->waitUntilReady();

		// If the filter is not visible - enable it.
		$filter_tab = $this->query('xpath:.//li[@aria-labelledby="ui-id-2" and @aria-selected="false"]');
		if ($filter_tab->exists()) {
			$filter_tab->one()->click();
		}

		$form = $this->query('name:zbx_filter')->asForm()->one();
		$empty_form = [
			'Recipients' => '',
			'Actions' => '',
			'Media types' => '',
			'Search string' => '',
			'In progress' => false,
			'Sent/Executed' => false,
			'Failed' => false
		];
		$filled_form = [
			'Recipients' => 'test-timezone',
			'Actions' => 'Trigger action 3',
			'Media types' => 'SMS',
			'Search string' => 'test',
			'In progress' => true,
			'Sent/Executed' => true,
			'Failed' => true
		];

		// Check reset button with/without filter submit.
		foreach ([true, false] as $submit) {
			$this->assertTableStats(10);
			$form->checkValue($empty_form);
			$form->fill($filled_form);

			if ($submit) {
				$form->submit();
				$this->page->waitUntilReady();
				$this->assertTableStats(0);
			}

			$form->invalidate()->checkValue($filled_form);
			$form->query('button:Reset')->one()->click();
			$this->page->waitUntilReady();
			$form->invalidate()->checkValue($empty_form);
			$this->assertTableStats(10);
		}
	}
}
