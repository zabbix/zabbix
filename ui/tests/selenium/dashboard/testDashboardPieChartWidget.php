<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup hosts, widget, profiles
 *
 * @onBefore prepareData
 */
class testDashboardPieChartWidget extends CWebTest
{
	protected static $dashboardid;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Create the needed initial data in database and set static variables.
	 */
	public function prepareData() {
		// Set Pie chart as the default widget type.
		DBexecute('DELETE FROM profiles WHERE idx=\'web.dashboard.last_widget_type\' AND userid=\'1\'');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, value_str, type)'.
			' VALUES (99999,1,\'web.dashboard.last_widget_type\',\'piechart\',3)');

		// Create a dashboard for creating widgets.
		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Pie chart dashboard',
			'auto_start' => 0,
			'pages' => [['name' => 'Pie chart test page']]
		]);
		self::$dashboardid = $dashboards['dashboardids'][0];
	}

	public function getCreateData() {
		return [
			// Missing host pattern.
			[
				[
					'fields' => [
						'Data set' => ['item' => '*']
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			// Missing item pattern.
			[
				[
					'fields' => [
						'Data set' => ['host' => '*']
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			],
			// Minimum required fields.
			[
				[
					'fields' => [
						'Data set' => [
							'host' => 'test host',
							'item' => 'test item'
						]
					]
				]
			],
			// Minimum required.
			[
				[
					'fields' => [
						'Data set' => [
							'host' => 'test host',
							'item' => 'test item'
						]
					]
				]
			],
		];
	}

	/**
	 * Test creation of Pie chart.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardPieChartWidget_Create($data){
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->addWidget()->asForm();

		// Fill data and submit.
		$this->fillForm($data['fields'], $form);
		$form->submit();

		$test_good_expected = (CTestArrayHelper::get($data, 'result', TEST_GOOD) === TEST_GOOD);

		if ($test_good_expected) {
			COverlayDialogElement::ensureNotPresent();

			// Save Dashboard.
			$widget = $dashboard->getWidget($this->calculateWidgetName($data['fields']));
			$this->waitForWidgetToLoad($widget);
			$dashboard->save();

			// Assert successful save.
			$message = CMessageElement::find()->waitUntilPresent()->one();
			$this->assertTrue($message->isGood());
			$this->assertEquals('Dashboard updated', $message->getTitle());

			// Assert data in edit form.
			$form = $widget->edit();
			$this->checkForm($data['fields'], $form);
		}
		else {
			// Assert error message.
			$message = CMessageElement::find()->waitUntilPresent()->one();
			$this->assertTrue($message->isBad());
			$this->assertEquals($data['error'], $message->getLines()->get(0)->getText());
		}

		// Check total Widget count.
		$this->assertEquals($old_widget_count + ($test_good_expected ? 1 : 0), $dashboard->getWidgets()->count());
	}

	/**
	 * Waits for a widget to stop loading and to show the pie chart.
	 *
	 * @param CWidgetElement $widget    widget element to wait
	 */
	protected function waitForWidgetToLoad($widget) {
		$widget->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$widget->getContent()->query('class:svg-pie-chart')->waitUntilVisible();
	}

	/**
	 * Calculates widget name from field data in data provider.
	 * If no name is provided, then use an MD5 as the name so that it is unique.
	 *
	 * @param array $fields    field data for calculating the widget name
	 */
	protected function calculateWidgetName($fields) {
		return (array_key_exists('main_fields', $fields) && array_key_exists('Name', $fields['main_fields']))
				? $fields['main_fields']['Name']
				: md5(serialize($fields));
	}

	/**
	 * Check Pie chart widget form contains the provided data.
	 *
	 * @param array $fields         field data to check
	 * @param CFormElement $form    form to be checked
	 */
	protected function checkForm($fields, $form) {
		// ToDo: write the check function.
		$form->checkValue(['Name' => 'abd9ac388db7de8a01a1f38d8db257f8']);
	}

	/**
	 * Fill Pie chart widget form with provided data.
	 *
	 * @param array $fields         field data to fill
	 * @param CFormElement $form    form to be filled
	 */
	protected function fillForm($fields, $form) {
		// Fill main fields.
		$main_fields['Name'] = $this->calculateWidgetName($fields);
		$form->fill($main_fields);

		// Fill datasets.
		$this->fillDatasets(CTestArrayHelper::get($fields, 'Data set', []), $form);

		// Fill the other tabs.
		$tabs = ['Displaying options', 'Time period', 'Legend'];

		foreach ($tabs as $tab) {
			if (!array_key_exists($tab, $fields)) {
				continue;
			}

			$form->selectTab($tab);
			$form->fill($fields[$tab]);
		}
	}

	/**
	 * Fill "Data sets" tab with field data.
	 *
	 * @param array $data_sets      array of data sets to be filled
	 * @param CFormElement $form    CFormElement to be filled
	 */
	protected function fillDatasets($data_sets, $form) {
		// When there is only one data set defined make it an array.
		if (CTestArrayHelper::isAssociative($data_sets)) {
			$data_sets = [$data_sets];
		}

		$last = count($data_sets) - 1;
		// Count of data sets that already exist.
		$count_sets = $form->query('xpath://li[contains(@class, "list-accordion-item")]')->all()->count();

		foreach ($data_sets as $i => $data_set) {
			$selectors = [
				'host' => 'xpath://div[@id="ds_'.$i.'_hosts_"]/..',
				'item' => 'xpath://div[@id="ds_'.$i.'_items_"]/..'
			];

			// Exchange 'host' and 'item' keys for the actual selector the fields have.
			foreach ($selectors as $field => $selector) {
				if (array_key_exists($field, $data_set)) {
					$data_set = [$selector => $data_set[$field]] + $data_set;
					unset($data_set[$field]);
				}
			}

			$form->fill($data_set);

			// Open next dataset if needed. Either create a new one or open an existing one.
			if ($i !== $last) {
				if ($i + 1 < $count_sets) {
					$i += 2;
					$form->query('xpath:(//li[contains(@class, "list-accordion-item")])['.$i.']//button')->one()->click();
				}
				else {
					$form->query('button:Add new data set')->one()->click();
				}

				$form->invalidate();
			}
		}
	}
}
