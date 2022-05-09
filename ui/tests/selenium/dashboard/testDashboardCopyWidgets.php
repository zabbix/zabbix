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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @onBefore prepareIds
 *
 * @backup widget, profiles
 */
class testDashboardCopyWidgets extends CWebTest {

	const NEW_PAGE_NAME = 'Test_page';
	const TEMPLATED_PAGE_NAME = 'Page for pasting widgets';
	const DASHBOARD_NAME1 = 'Dashboard_1 for Copying widgets';
	const DASHBOARD_NAME2 = 'Dashboard_2 for Copying widgets';

	private static $replaced_widget_name = "Test widget for replace";
	private static $replaced_widget_size = [ 'width' => '13', 'height' => '8'];

	private static $dashboardid_with_widgets;
	private static $empty_dashboardid;
	private static $templated_page_id;

	protected static $dashboard_id1;
	protected static $dashboard_id2;
	protected static $new_page_ids;
	protected static $paste_dashboard_id;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public static function prepareIds() {
		self::$new_page_ids	= CDBHelper::getColumn('SELECT * FROM dashboard_page WHERE name ='.zbx_dbstr('Test_page').
				' ORDER BY dashboard_pageid', 'dashboard_pageid');
		self::$paste_dashboard_id = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.
				zbx_dbstr('Dashboard for Paste widgets'));
		self::$dashboardid_with_widgets = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.
				zbx_dbstr('Templated dashboard with all widgets'));
		self::$empty_dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.
				zbx_dbstr('Dashboard without widgets'));
		self::$templated_page_id = CDBHelper::getValue('SELECT dashboard_pageid FROM dashboard_page WHERE name='.
				zbx_dbstr(self::TEMPLATED_PAGE_NAME));
		self::$dashboard_id1 = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.zbx_dbstr(self::DASHBOARD_NAME1));
		self::$dashboard_id2 = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.zbx_dbstr(self::DASHBOARD_NAME2));
	}

	/**
	 * Data provider for copying widgets from the first dashboard.
	 */
	public function getCopyWidgetsFirstData() {
		return $this->getCopyWidgetsData('Dashboard_1 for Copying widgets');
	}

	/**
	 * Data provider for copying widgets from the second dashboard.
	 */
	public function getCopyWidgetsSecondData() {
		return $this->getCopyWidgetsData('Dashboard_2 for Copying widgets');
	}

	private function getCopyWidgetsData($dashboard_name) {
		static $data = [];
		if (!array_key_exists($dashboard_name, $data)) {
			global $DB;
			if (!isset($DB['DB'])) {
				DBconnect($error);
			}
			CDataHelper::load('CopyWidgetsDashboards');

			$dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.zbx_dbstr($dashboard_name));
			$data[$dashboard_name] = CDBHelper::getDataProvider('SELECT * FROM widget w'.
				' WHERE EXISTS ('.
					'SELECT NULL'.
					' FROM dashboard_page dp'.
					' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
						' AND dp.dashboardid='.$dashboardid.
				') ORDER BY w.widgetid DESC'
			);
		}

		return $data[$dashboard_name];
	}

	/**
	 * @dataProvider getCopyWidgetsFirstData
	 */
	public function testDashboardCopyWidgets_SameDashboard_1($data) {
		$this->copyWidgets($data, self::$dashboard_id1);
	}

	/**
	 * @dataProvider getCopyWidgetsSecondData
	 */
	public function testDashboardCopyWidgets_SameDashboard_2($data) {
		$this->copyWidgets($data, self::$dashboard_id2);
	}

	/**
	 * @dataProvider getCopyWidgetsFirstData
	 */
	public function testDashboardCopyWidgets_OtherDashboard_1($data) {
		$this->copyWidgets($data, self::$dashboard_id1, null, true);
	}

	/**
	 * @dataProvider getCopyWidgetsSecondData
	 */
	public function testDashboardCopyWidgets_OtherDashboard_2($data) {
		$this->copyWidgets($data, self::$dashboard_id2, null, true);
	}

	/**
	 * @dataProvider getCopyWidgetsFirstData
	 */
	public function testDashboardCopyWidgets_ReplaceWidget_1($data) {
		$this->copyWidgets($data, self::$dashboard_id1, null, true, true);
	}

	/**
	 * @dataProvider getCopyWidgetsSecondData
	 */
	public function testDashboardCopyWidgets_ReplaceWidget_2($data) {
		$this->copyWidgets($data, self::$dashboard_id2, null, true, true);
	}

	/**
	 * @dataProvider getCopyWidgetsFirstData
	 */
	public function testDashboardCopyWidgets_NewPage_1($data) {
		$this->copyWidgets($data, self::$dashboard_id1, self::$new_page_ids[0], false, false, true);
	}

	/**
	 * @dataProvider getCopyWidgetsSecondData
	 */
	public function testDashboardCopyWidgets_NewPage_2($data) {
		$this->copyWidgets($data, self::$dashboard_id2, self::$new_page_ids[1], false, false, true);
	}

	private function copyWidgets($data, $start_dashboard, $paste_page_id = null, $new_dashboard = false, $replace = false,
			$new_page = false, $templated = false) {
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

		// Use the appropriate dashboard and page in case of templated dashboard widgets.
		if ($templated) {
			$dashboard_id = self::$dashboardid_with_widgets;
			$new_dashboard_id = self::$empty_dashboardid;
			$new_page_name = self::TEMPLATED_PAGE_NAME;
			$new_page_id = self::$templated_page_id;
			$url = 'zabbix.php?action=template.dashboard.edit&dashboardid=';
		}
		else {
			$dashboard_id = $start_dashboard;
			$new_dashboard_id = self::$paste_dashboard_id;
			$new_page_name = self::NEW_PAGE_NAME;
			$new_page_id = $paste_page_id;
			$url = 'zabbix.php?action=dashboard.view&dashboardid=';
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
		$this->page->login()->open($url.$dashboard_id);
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
			: CDBHelper::getRow('SELECT w.width, w.height'.
					' FROM widget w WHERE EXISTS ('.
						'SELECT NULL FROM dashboard_page dp'.
						' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
							' AND dp.dashboardid='.$dashboard_id.
					')'.
					' AND w.name='.zbx_dbstr($name).' ORDER BY w.widgetid DESC'
			);

		// Close widget configuration overlay.
		COverlayDialogElement::find()->one()->close();
		$dashboard->copyWidget($name);

		// Open other dashboard for paste widgets.
		if ($new_dashboard) {
			$this->page->open($url.$new_dashboard_id);
			$dashboard = CDashboardElement::find()->one();
		}

		if ($new_page) {
			$this->query('xpath://div[@class="dashboard-navigation-tabs"]//span[text()="'.$new_page_name.'"]')
					->waitUntilClickable()->one()->click();
			$this->query('xpath://div[@class="selected-tab"]//span[text()="'.$new_page_name.'"]')
					->waitUntilVisible()->one();
		}

		$dashboard->edit();

		if ($replace) {
			$dashboard->replaceWidget($replaces);
		}
		else {
			$dashboard->pasteWidget();
		}

		// Wait until widget is pasted and loading spinner disappeared.
		sleep(1);
		$this->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$copied_widget = $dashboard->getWidgets()->last();

		// For Other dashboard and Map from Navigation tree case - add map source, because it is not being copied by design.
		if (($new_dashboard || $new_page) && stristr($name, 'Map from tree')) {
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

		if ($templated) {
			$this->query('button:Save changes')->one()->click();
		}
		else {
			$dashboard->save();
		}
		$this->page->waitUntilReady();

		// For templated dashboards the below SQL is executed faster than the corresponding record is added to DB.
		if ($templated) {
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		$copied_widget_size = CDBHelper::getRow('SELECT w.width, w.height'.
				' FROM widget w WHERE EXISTS ('.
					'SELECT NULL'.
					' FROM dashboard_page dp'.
					' WHERE w.dashboard_pageid='.($new_page ? $new_page_id : 'dp.dashboard_pageid').
						' AND dp.dashboardid='.($new_dashboard ? $new_dashboard_id : $dashboard_id).
				')'.
				' AND w.name='.zbx_dbstr($name).' ORDER BY w.widgetid DESC'
		);
		$this->assertEquals($original_widget_size, $copied_widget_size);
	}

	public static function getTemplateDashboardWidgetData() {
		return [
			[
				[
					'name' => 'Clock widget',
					'copy to' => 'same page'
				]
			],
			[
				[
					'name' => 'Graph (classic) widget',
					'copy to' => 'same page'
				]
			],
			[
				[
					'name' => 'URL widget',
					'copy to' => 'same page'
				]
			],
			[
				[
					'name' => 'Plain text widget',
					'copy to' => 'same page'
				]
			],
			[
				[
					'name' => 'URL widget',
					'copy to' => 'same page'
				]
			],
			[
				[
					'name' => 'Item value widget',
					'copy to' => 'same page'
				]
			],
			[
				[
					'name' => 'Clock widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Graph (classic) widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'URL widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Plain text widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'URL widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Item value widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Clock widget',
					'copy to' => 'another dashboard'
				]
			],
			[
				[
					'name' => 'Graph (classic) widget',
					'copy to' => 'another dashboard'
				]
			],
			[
				[
					'name' => 'URL widget',
					'copy to' => 'another dashboard'
				]
			],
			[
				[
					'name' => 'Plain text widget',
					'copy to' => 'another dashboard'
				]
			],
			[
				[
					'name' => 'URL widget',
					'copy to' => 'another dashboard'
				]
			],
			[
				[
					'name' => 'Item value widget',
					'copy to' => 'another dashboard'
				]
			],
			[
				[
					'name' => 'Clock widget',
					'copy to' => 'another template'
				]
			]
		];
	}

	/**
	 * Function that checks copy operation for template dashboard widgets to different locations.
	 *
	 * @dataProvider getTemplateDashboardWidgetData
	 *
	 * @backupOnce dashboard
	 */
	public function testDashboardCopyWidgets_CopyTemplateWidgets($data) {
		switch ($data['copy to']) {
			case 'same page':
				$this->copyWidgets($data,  self::$dashboardid_with_widgets, null, false, false, false, true);
				break;

			case 'another page':
				$this->copyWidgets($data, self::$dashboardid_with_widgets, null, false, false, true, true);
				break;

			case 'another dashboard':
				$this->copyWidgets($data, self::$dashboardid_with_widgets, null, true, false, false, true);
				break;

			case 'another template':
				$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_with_widgets);
				$dashboard = CDashboardElement::find()->one()->waitUntilVisible();
				$dashboard->copyWidget($data['name']);

				$this->page->open('zabbix.php?action=template.dashboard.edit&templateid=50002');
				$this->page->waitUntilReady();
				COverlayDialogElement::find()->one()->close();
				$this->query('id:dashboard-add')->one()->click();
				$this->assertFalse(CPopupMenuElement::find()->one()->getItem('Paste widget')->isEnabled());

				$this->closeDialogues();
				break;
		}
	}

	public static function getTemplateDashboardPageData() {
		return [
			[
				[
					'copy to' => 'same dashboard'
				]
			],
			[
				[
					'copy to' => 'another dashboard'
				]
			],
			[
				[
					'copy to' => 'another template'
				]
			]
		];
	}

	/**
	 * Function that checks copy operation for template dashboard pages to different locations.
	 *
	 * @dataProvider getTemplateDashboardPageData
	 */
	public function testDashboardCopyWidgets_CopyTemplateDashboardPage($data) {
		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$dashboardid_with_widgets);
		$dashboard = CDashboardElement::find()->one()->waitUntilVisible();

		$dashboard->query('xpath://span[text()= "Page with widgets"]/../button')->one()->click();
		CPopupMenuElement::find()->one()->waitUntilVisible()->select('Copy');

		switch ($data['copy to']) {
			case 'same dashboard':
				$this->query('id:dashboard-add')->one()->click();
				CPopupMenuElement::find()->one()->waitUntilVisible()->select('Paste page');
				$dashboard->query('xpath:(//span[@title="Page with widgets"])[2]')->waitUntilVisible()->one();
				$this->assertEquals(2, $dashboard->query('xpath://span[@title="Page with widgets"]')->all()->count());
				break;

			case 'another dashboard':
				$this->page->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$empty_dashboardid);
				$this->page->waitUntilReady();

				$this->query('id:dashboard-add')->one()->click();
				CPopupMenuElement::find()->one()->waitUntilVisible()->select('Paste page');
				$this->assertEquals(1, $dashboard->query('xpath://span[@title="Page with widgets"]')
						->waitUntilVisible()->all()->count()
				);
				break;

			case 'another template':
				$this->page->open('zabbix.php?action=template.dashboard.edit&templateid=50002');
				$this->page->waitUntilReady();
				COverlayDialogElement::find()->one()->close();
				$this->query('id:dashboard-add')->one()->click();
				$this->assertFalse(CPopupMenuElement::find()->one()->getItem('Paste page')->isEnabled());
				break;
		}

		$this->closeDialogues();
	}

	/**
	 * Function that closes all dialogs and alerts on a template dashboard before proceeding to the next test.
	 */
	private function closeDialogues() {
		$overlay = COverlayDialogElement::find()->one(false);
		if ($overlay->isValid()) {
			$overlay->close();
		}
		$this->query('link:Cancel')->one()->forceClick();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}
	}
}
