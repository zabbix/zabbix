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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';

/**
 * @backup widget
 * @backup profiles
 */
class testDashboardCopyWidgets extends CWebTest {

	const DASHBOARD_ID = 130;
	const PASTE_DASHBOARD_ID = 131;

	private static $replaced_widget_name = "Test widget for replace";
	private static $replaced_widget_size = [ 'width' => '13', 'height' => '8'];

	/**
	 * Data provider for copying widgets.
	 */
	public static function getCopyWidgetsData() {
		return CDBHelper::getDataProvider('SELECT * FROM widget WHERE dashboardid ='.self::DASHBOARD_ID);
	}

	/**
	 * @dataProvider getCopyWidgetsData
	 */
	public function testDashboardCopyWidgets_SameDashboard($data) {
		$this->copyWidgets($data);
	}

	/**
	 * @dataProvider getCopyWidgetsData
	 */
	public function testDashboardCopyWidgets_OtherDashboard($data) {
		$this->copyWidgets($data, true);
	}

	/**
	 * @dataProvider getCopyWidgetsData
	 */
	public function testDashboardCopyWidgets_ReplaceWidget($data) {
		$this->copyWidgets($data, true, true);
	}

	private function copyWidgets($data, $new_dashboard = false, $replace = false) {
		$name = $data['name'];

		// Exclude Map navigation tree widget from replacing tests.
		if ($replace && $name === 'Test copy Map navigation tree') {
			return;
		}

		$replaces = self::$replaced_widget_name;

		// Write name for replacing widget next case.
		if ($replace) {
			self::$replaced_widget_name = $name;
		}

		// Mapping for tags in problem widgets.
		$mapping = [
			'tag',
			[
				'name' => 'match',
				'class' => CSegmentedRadioElement::class
			],
			'value'
		];
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::DASHBOARD_ID);
		$dashboard = CDashboardElement::find()->one();
		// Get fields from widget form to compare them with new widget after copying.
		$fields = $dashboard->getWidget($name)->edit()->getFields();
		// Add tag fields mapping to form for problem widgets.
		if (stristr($name, 'Problem')) {
			$fields->set('', $fields->get('')->asMultifieldTable(['mapping' => $mapping]));
		}
		$original_form = $fields->asValues();
		$original_widget_size = $replace
			? self::$replaced_widget_size
			: CDBHelper::getRow('SELECT width, height FROM widget WHERE dashboardid='.zbx_dbstr(self::DASHBOARD_ID).
					' AND name='.zbx_dbstr($name).' ORDER BY widgetid DESC');

		// Close widget configuration overlay.
		COverlayDialogElement::find()->one()->close();

		$dashboard->copyWidget($name);
		// Open other dashboard for paste widgets.
		if ($new_dashboard) {
			$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::PASTE_DASHBOARD_ID);
			$dashboard = CDashboardElement::find()->one();
		}
		$dashboard->edit();

		if ($replace) {
			$dashboard->replaceWidget($replaces);
		}
		else {
			$dashboard->pasteWidget();
		}
		sleep(1);
		// Wait until widget is pasted and loading spinner disappeared.
		$this->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$copied_widget = $dashboard->getWidgets()->last();
		// For Other dashboard and Map from Navigation tree case - add map source, because it is not being copied by design.
		if ($new_dashboard && stristr($name, 'Map from tree')) {
			$copied_widget_form = $copied_widget->edit();
			$copied_widget_form->fill(['Filter' => 'Test copy Map navigation tree']);
			$copied_widget_form->submit();
		}
		$this->assertEquals($name, $copied_widget->getHeaderText());
		$copied_fields = $copied_widget->edit()->getFields();
		// Add tag fields mapping to form for newly copied problem widgets.
		if (stristr($name, 'Problem')) {
			$copied_fields->set('', $copied_fields->get('')->asMultifieldTable(['mapping' => $mapping]));
		}
		$copied_form = $copied_fields->asValues();
		$this->assertEquals($original_form, $copied_form);
		// Close overlay and save dashboard to get new widget size from DB.
		$copied_overlay = COverlayDialogElement::find()->one();
		$copied_overlay->close();
		$dashboard->save();
		$this->page->waitUntilReady();

		$copied_widget_size = CDBHelper::getRow('SELECT width, height FROM widget'.
				' WHERE dashboardid='.zbx_dbstr($new_dashboard ? self::PASTE_DASHBOARD_ID : self::DASHBOARD_ID).
				' AND name='.zbx_dbstr($name).' ORDER BY widgetid DESC'
		);
		$this->assertEquals($original_widget_size, $copied_widget_size);
	}
}
