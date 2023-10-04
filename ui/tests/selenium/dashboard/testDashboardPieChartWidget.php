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

/**
 * @backup widget, profiles
 *
 * @onBefore prepareData
 */
class testDashboardPieChartWidget extends CWebTest
{
	protected static $dashboardid;

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
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Data set' => [
							'host' => '*',
							'item' => '*'
						]
					],
					'error' => 'Invalid parameter "Item": cannot be empty.'
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

		$this->fillForm($data['fields'], $form);
		$form->submit();

	}

	/**
	 * Fill Pie chart widget form with provided data.
	 *
	 * @param array $fields         field data to fill
	 * @param CFormElement $form    CFormElement to be filled
	 */
	protected function fillForm($fields, $form) {
		$form->fill(CTestArrayHelper::get($fields, 'main_fields', []));
		$this->fillDatasets(CTestArrayHelper::get($fields, 'Data set', []), $form);

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
			$mapping = [
				'host' => 'xpath://div[@id="ds_'.$i.'_hosts_"]/..',
				'item' => 'xpath://div[@id="ds_'.$i.'_items_"]/..'
			];

			// If host or item of data set exist in data provider, add the xpath selector and value from data provider to them.
			foreach ($mapping as $field => $selector) {
				if (array_key_exists($field, $data_set)) {
					$data_set = [$selector => $data_set[$field]] + $data_set;
					unset($data_set[$field]);
				}
			}

			$form->fill($data_set);

			// Open next dataset if needed. Either create new or open existing.
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
