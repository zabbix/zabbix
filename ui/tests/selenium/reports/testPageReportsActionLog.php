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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup alerts
 *
 *
 */
class testPageReportsActionLog extends CLegacyWebTest {

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
						'Recipients' => 'test-timezone'
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
				'filter_messages=&filter_set=1');

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		$table = $this->query('class:list-table')->asTable()->one();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill($data['fields'])->submit();
		$this->page->waitUntilReady();

		if (array_key_exists('result_amount', $data)) {
			$this->assertEquals($data['result_amount'], $table->getRows()->count());
			$this->assertTableStats($data['result_amount']);

			if (array_key_exists('fields', $data)) {
				foreach ($data['fields'] as $column => $values) {
					$column_values = $this->getTableColumnData(substr($column, 0, -1));
					foreach ($column_values as $column_value) {
						foreach ($values as $value) {
							if (str_contains($column_value, $value)) {
								$column_values = array_values(array_diff($column_values, [$column_value]));
							}
						}
					}
				}
			}
		}
	}
}
