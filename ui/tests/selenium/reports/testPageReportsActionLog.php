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
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup alerts
 *
 * @onBefore prepareInsertActionsData
 */
class testPageReportsActionLog extends CWebTest {

	use TableTrait;

	public static function prepareInsertActionsData() {
		DBexecute("INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, ".
				"message, status, retries, error, esc_step, alerttype, parameters) VALUES (8, 13, 1, 1, ".
				"1329724870, 10, 'test.test@zabbix.com', 'subject here', 'message here', 1, 0, '', 1, 0, '');"
		);

		DBexecute("INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, ".
				"message, status, retries, error, esc_step, alerttype, parameters) VALUES (9, 13, 1, 9, ".
				"1329724880, 3, '77777777', 'subject here', 'message here', 1, 0, '', 1, 0, '');"
		);
	}

	public function testPageReportsActionLog_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=actionlog.list&from=now-2y&to=now');

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();

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
		$this->assertEquals(['Time', 'Action', 'Media type', 'Recipient', 'Message', 'Status', 'Info'], $table->getHeadersText());

		// Check status available values.
		$this->assertEquals(['In progress', 'Sent/Executed', 'Failed'], $this->query('id:filter_status')
				->asCheckboxList()->one()->getLabels()->asText()
		);
	}

	public static function getCheckFilterData() {
		return [
			// #0
			[
				[
					'fields' => [
						'Recipients' => ['test-timezone']
					],
					'result_amount' => 1
				]
			],
			// #1
			[
				[
					'fields' => [
						'Recipients' => ['Tag-user', 'test-timezone']
					],
					'result_amount' => 1
				]
			],
			// #2
			[
				[
					'fields' => [
						'Recipients' => ['Administrator']
					],
					'result_amount' => 6
				]
			],
			// #3
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 2']
					],
					'result_amount' => 7
				]
			],
			// #4
			[
				[
					'fields' => [
						'Actions' => ['Simple action']
					]
				]
			],
			// #5
			[
				[
					'fields' => [
						'Recipients' => ['filter-update']
					]
				]
			],
			// #6
			[
				[
					'fields' => [
						'Media types' => ['Discord']
					],
					'result_amount' => 1
				]
			],
			// #7
			[
				[
					'fields' => [
						'Media types' => ['Discord', 'SMS']
					],
					'result_amount' => 2
				]
			],
			// #8
			[
				[
					'fields' => [
						'Media types' => ['Github']
					]
				]
			],
			// #9
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 2'],
						'Media types' => ['Discord']
					]
				]
			],
			// #10
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 3'],
						'Media types' => ['Discord']
					],
					'result_amount' => 1
				]
			],
			// #11
			[
				[
					'fields' => [
						'Recipients' => ['Administrator'],
						'Actions' => ['Trigger action 3']
					],
					'result_amount' => 1
				]
			],
			// #12
			[
				[
					'fields' => [
						'Recipients' => ['Administrator'],
						'Media types' => ['Email']
					],
					'result_amount' => 5
				]
			],
			// #13
			[
				[
					'fields' => [
						'Recipients' => ['Administrator', 'test-timezone'],
						'Media types' => ['Email', 'Discord', 'SMS'],
						'Actions' => ['Trigger action 2', 'Trigger action 3']
					],
					'result_amount' => 7
				]
			],
			// #14
			[
				[
					'status' => ['Failed'],
					'result_amount' => 1,
					'result_status' => ['Failed']
				]
			],
			// #15
			[
				[
					'status' => ['Sent/Executed'],
					'result_amount' => 7,
					'result_status' => [
						'Sent',
						'Sent',
						'Executed',
						'Executed',
						'Sent',
						'Sent',
						'Sent'
					]
				]
			],
			// #16
			[
				[
					'status' => ['In progress'],
					'result_amount' => 1,
					'result_status' => ["In progress:\n3 retries left"]
				]
			],
			// #17
			[
				[
					'status' => ['Failed', 'In progress'],
					'result_amount' => 2,
					'result_status' => [
						"In progress:\n3 retries left",
						'Failed'
					]
				]
			],
			// #18
			[
				[
					'status' => ['Failed', 'Sent/Executed', 'In progress'],
					'result_amount' => 9,
					'result_status' => [
						'Sent',
						'Sent',
						'Executed',
						'Executed',
						"In progress:\n3 retries left",
						'Failed',
						'Sent',
						'Sent',
						'Sent'
					]
				]
			],
			// #19
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 3']
					],
					'status' => ['Sent/Executed'],
					'result_amount' => 2,
					'result_status' => [
						'Sent',
						'Sent'
					]
				]
			],
			// #20
			[
				[
					'fields' => [
						'Actions' => ['Trigger action 3']
					],
					'status' => ['In progress']
				]
			],
			// #21
			[
				[
					'fields' => [
						'Recipients' => ['Administrator', 'test-timezone']
					],
					'status' => ['In progress'],
					'result_amount' => 1,
					'result_status' => [
						"In progress:\n3 retries left"
					]
				]
			],
			// #22
			[
				[
					'fields' => [
						'Recipients' => ['test-timezone']
					],
					'status' => ['In progress']
				]
			],
			// #23
			[
				[
					'fields' => [
						'Media types' => ['Email']
					],
					'status' => ['In progress'],
					'result_amount' => 1,
					'result_status' => [
						"In progress:\n3 retries left"
					]
				]
			],
			// #24
			[
				[
					'fields' => [
						'Media types' => ['Email']
					],
					'status' => ['Sent/Executed'],
					'result_amount' => 3,
					'result_status' => [
						'Sent',
						'Sent',
						'Sent'
					]
				]
			],
			// #25
			[
				[
					'fields' => [
						'Media types' => ['Email']
					],
					'status' => ['Failed'],
					'result_amount' => 1,
					'result_status' => [
						'Failed'
					]
				]
			],
			// #26
			[
				[
					'fields' => [
						'Media types' => ['Email'],
						'Actions' => ['Trigger action 2'],
						'Recipients' => ['Administrator']
					],
					'status' => ['Sent/Executed'],
					'result_amount' => 3,
					'result_status' => [
						'Sent',
						'Sent',
						'Sent'
					]
				]
			],
			// #27
			[
				[
					'fields' => [
						'Search string' => 'test'
					]
				]
			],
			// #28
			[
				[
					'fields' => [
						'Search string' => '10:00:40'
					],
					'result_amount' => 1,
					'result_status' => ["In progress:\n3 retries left"]
				]
			],
			// #29
			[
				[
					'fields' => [
						'Search string' => '.'
					],
					'result_amount' => 5
				]
			],
			// #30
			[
				[
					'fields' => [
						'Search string' => '5'
					],
					'result_amount' => 1
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFilterData
	 */
	public function testPageReportsActionLog_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=actionlog.list&from=2012-02-20+09:01:00&to=2012-02-20+11:01:00&'.
				'filter_messages=&filter_set=1')->waitUntilReady();

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		$form = $this->query('name:zbx_filter')->asForm()->one();

		if (array_key_exists('fields', $data)) {
			$form->fill($data['fields']);
		}

		if (array_key_exists('status', $data)) {
			$this->query('id:filter_status')->asCheckboxList()->one()->check($data['status']);
		}

		$form->submit();
		$this->page->waitUntilReady();

		if (array_key_exists('result_amount', $data)) {
			$this->assertEquals($data['result_amount'], $this->query('class:list-table')->asTable()->one()->getRows()->count());
			$this->assertTableStats($data['result_amount']);

			if (array_key_exists('fields', $data)) {
				foreach ($data['fields'] as $column => $values) {
					if ($column === 'Search string') {
						$column = 'Message';
						$values = [$values];
					} else {
						$column = substr($column, 0, -1);
					}

					$column_values = $this->getTableColumnData($column);
					foreach ($values as $value) {
						foreach ($column_values as $column_value) {
							if (str_contains($column_value, $value)) {
								$column_values = array_values(array_diff($column_values, [$column_value]));
							}
						}
					}
				}

				$this->assertEquals(0, count($column_values));
			}

			if (array_key_exists('result_status', $data)) {
				$this->assertEquals($data['result_status'], $this->getTableColumnData('Status'));
			}
		}
		else {
			$this->assertTableStats(0);
			$this->assertTableData();
		}
	}
}
