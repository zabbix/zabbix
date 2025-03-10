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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @onBefore getTemplatedIds
 *
 * @backup widget, profiles, module
 */
class testDashboardCopyWidgets extends CWebTest {

	// Constants for regular dashboard cases.
	const NEW_PAGE_NAME = 'Test_page';
	const PASTE_DASHBOARD_NAME = 'Dashboard for Paste widgets';

	// Constants for templated dashboard cases.
	const TEMPLATED_DASHBOARD_NAME = 'Templated dashboard with all widgets';
	const TEMPLATED_PAGE_NAME = 'Page for pasting widgets';
	const EMPTY_DASHBOARD_NAME = 'Dashboard without widgets';
	const MODULES_DASHBOARD_NAME = 'Dashboard for Copying widgets _1';
	private static $templated_dashboardid;
	private static $templated_empty_dashboardid;
	private static $modules_dashboardid;

	// Values for replacing widgets.
	private static $replaced_widget_name = "Test widget for replace";
	const REPLACED_WIDGET_SIZE = [ 'width' => '21', 'height' => '3'];

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 *  Get all widgets from dashboards with name starting with "Dashboard for Copying widgets".
	 */
	public static function getDashboardsData() {
		static $data = null;
		if ($data === null) {
			global $DB;
			if (!isset($DB['DB'])) {
				DBconnect($error);
			}
			CDataHelper::load('CopyWidgetsDashboards');

			$data = CDBHelper::getDataProvider('SELECT w.name, dp.dashboardid FROM widget w'.
					' JOIN dashboard_page dp ON w.dashboard_pageid=dp.dashboard_pageid'.
					' WHERE dp.dashboardid IN ('.
						'SELECT dashboardid FROM dashboard '.
						'WHERE name LIKE \'%Dashboard for Copying widgets%\''.
					') ORDER BY w.widgetid DESC'
			);
		}

		return $data;
	}

	/**
	 * Get ids for template dashboard cases.
	 */
	public static function getTemplatedIds() {
		self::$templated_dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name='.
				zbx_dbstr(self::TEMPLATED_DASHBOARD_NAME)
		);
		self::$templated_empty_dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name='.
				zbx_dbstr(self::EMPTY_DASHBOARD_NAME)
		);
		self::$modules_dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name='.
				zbx_dbstr(self::MODULES_DASHBOARD_NAME)
		);
	}

	/**
	 * @dataProvider getDashboardsData
	 */
	public function testDashboardCopyWidgets_SameDashboard($data) {
		$this->copyWidgets($data['dashboardid'], $data['name']);
	}

	/**
	 * @backupOnce dashboard
	 *
	 * @dataProvider getDashboardsData
	 */
	public function testDashboardCopyWidgets_OtherDashboard($data) {
		$this->copyWidgets($data['dashboardid'], $data['name'], true);
	}

	/**
	 * @dataProvider getDashboardsData
	 */
	public function testDashboardCopyWidgets_ReplaceWidget($data) {
		$this->copyWidgets($data['dashboardid'], $data['name'], true, true);
	}

	/**
	 * @dataProvider getDashboardsData
	 */
	public function testDashboardCopyWidgets_NewPage($data) {
		$this->copyWidgets($data['dashboardid'], $data['name'], false, false, true);
	}

	/**
	 * Common function for copying widgets testing.
	 *
	 * @param int     $start_dashboardid    id of a dashboard with widgets for copying
	 * @param string  $widget_name		    name of a widget to be copied
	 * @param boolean $new_dashboard		true if the widget is copied to new dashboard, false for the same dashboard
	 * @param boolean $replace              true if the widget is being replaced, false if copied to new place
	 * @param boolean $new_page             true if the widget is copied to the new page, false if copied to the same page
	 * @param boolean $templated            true if it is templated dashboard case, false if regular dashboard
	 */
	private function copyWidgets($start_dashboardid, $widget_name, $new_dashboard = false, $replace = false,
			$new_page = false, $templated = false) {

		// Exclude Map navigation tree widget from replacing tests.
		if ($replace && $widget_name === 'Test copy Map navigation tree') {
			return;
		}

		$replaces = self::$replaced_widget_name;

		// Use the appropriate dashboard and page in case of templated dashboard widgets.
		if ($templated) {
			$dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.
					zbx_dbstr(self::TEMPLATED_DASHBOARD_NAME)
			);
			$new_dashboardid = self::$templated_empty_dashboardid;
			$new_page_name = self::TEMPLATED_PAGE_NAME;
			$new_pageid = CDBHelper::getValue('SELECT dashboard_pageid FROM dashboard_page WHERE name='.
					zbx_dbstr(self::TEMPLATED_PAGE_NAME)
			);
			$url = 'zabbix.php?action=template.dashboard.edit&dashboardid=';
		}
		else {
			$dashboardid = $start_dashboardid;
			$new_dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.
					zbx_dbstr('Dashboard for Paste widgets')
			);
			$new_page_name = self::NEW_PAGE_NAME;
			$new_pageid = CDBHelper::getValue('SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid ='.
					$start_dashboardid.' AND name ='.zbx_dbstr(self::NEW_PAGE_NAME)
			);
			$url = 'zabbix.php?action=dashboard.view&dashboardid=';
		}

		$this->page->login()->open($url.$dashboardid);
		$dashboard = CDashboardElement::find()->one();

		// Get fields from widget form to compare them with new widget after copying.
		$widget = $dashboard->getWidget($widget_name)->edit();
		$original_form = $widget->getFields()->filter(CElementFilter::VISIBLE)->asValues();

		// Get tags of original widget.
		if (stristr($widget_name, 'Problem')) {
			$tags = $widget->query('id:tags_table_tags')->asMultifieldTable()->one()->getValue();
		}

		$original_widget_size = $replace
			? self::REPLACED_WIDGET_SIZE
			: CDBHelper::getRow('SELECT w.width, w.height'.
					' FROM widget w WHERE EXISTS ('.
						'SELECT NULL FROM dashboard_page dp'.
						' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
							' AND dp.dashboardid='.$dashboardid.
					')'.
					' AND w.name='.zbx_dbstr($widget_name).' ORDER BY w.widgetid DESC'
			);

		// Close widget configuration overlay.
		COverlayDialogElement::find()->one()->close();
		$dashboard->copyWidget($widget_name);

		// Open other dashboard for paste widgets.
		if ($new_dashboard) {
			$this->page->open($url.$new_dashboardid);
			$dashboard = CDashboardElement::find()->one();
		}

		if ($new_page) {
			$dashboard->selectPage($new_page_name);
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
		$dashboard->waitUntilReady();
		$this->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$copied_widget = $dashboard->getWidgets()->last()->waitUntilReady();

		// For Other dashboard and Map from Navigation tree case - add map source, because it is not being copied by design.
		if (($new_dashboard || $new_page) && stristr($widget_name, 'Map from tree')) {
			$copied_widget_form = $copied_widget->edit();
			$copied_widget_form->fill(['Map' => 'Test copy Map navigation tree']);
			$copied_widget_form->submit();
			COverlayDialogElement::ensureNotPresent();

			$copied_widget = $dashboard->waitUntilReady()->getWidget($widget_name);
		}

		$this->assertEquals($widget_name, $copied_widget->getHeaderText());
		$copied_fields = $copied_widget->edit()->getFields()->filter(CElementFilter::VISIBLE);

		// Check tags of original and copied widget.
		if (stristr($widget_name, 'Problem')) {
			$copied_tags = COverlayDialogElement::find()->waitUntilReady()->one()->query('id:tags_table_tags')
					->asMultifieldTable()->one()->getValue();
			$this->assertEquals($tags, $copied_tags);
		}

		$copied_form = $copied_fields->asValues();
		$this->assertEquals($original_form, $copied_form);

		// Close overlay and save dashboard to get new widget size from DB.
		COverlayDialogElement::find()->one()->close();

		if ($templated) {
			$this->query('button:Save changes')->one()->click();
		}
		else {
			$dashboard->save();
		}

		// Write name for replacing widget next case.
		if ($replace) {
			self::$replaced_widget_name = $widget_name;
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
					' WHERE w.dashboard_pageid='.($new_page ? $new_pageid : 'dp.dashboard_pageid').
						' AND dp.dashboardid='.($new_dashboard ? $new_dashboardid : $dashboardid).
				')'.
				' AND w.name='.zbx_dbstr($widget_name).' ORDER BY w.widgetid DESC'
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
					'name' => 'Discovery status widget',
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
					'name' => 'Item history widget',
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
					'name' => 'Honeycomb widget',
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
					'name' => 'Discovery status widget',
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
					'name' => 'Item history widget',
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
					'name' => 'Gauge widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Host navigator widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Item navigator widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Pie chart widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Top triggers widget',
					'copy to' => 'another page'
				]
			],
			[
				[
					'name' => 'Honeycomb widget',
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
					'name' => 'Discovery status widget',
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
					'name' => 'Item history widget',
					'copy to' => 'another dashboard'
				]
			],
			[
				[
					'name' => 'Honeycomb widget',
					'copy to' => 'another page'
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
			],
			[
				[
					'name' => 'Discovery status widget',
					'copy to' => 'another template'
				]
			],
			[
				[
					'name' => 'Gauge widget',
					'copy to' => 'another template'
				]
			],
			[
				[
					'name' => 'Host navigator widget',
					'copy to' => 'another template'
				]
			],
			[
				[
					'name' => 'Item navigator widget',
					'copy to' => 'another template'
				]
			],
			[
				[
					'name' => 'Top triggers widget',
					'copy to' => 'another template'
				]
			],
			[
				[
					'name' => 'Pie chart widget',
					'copy to' => 'another template'
				]
			],
			[
				[
					'name' => 'Honeycomb widget',
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
				$this->copyWidgets(self::$templated_dashboardid, $data['name'], false, false, false, true);
				break;

			case 'another page':
				$this->copyWidgets(self::$templated_dashboardid, $data['name'], false, false, true, true);
				break;

			case 'another dashboard':
				$this->copyWidgets(self::$templated_dashboardid, $data['name'], true, false, false, true);
				break;

			case 'another template':
				$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$templated_dashboardid);
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
		$this->page->login()->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$templated_dashboardid);
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
				$this->page->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$templated_empty_dashboardid);
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

	public static function getModuleDashboardWidgetData() {
		return [
			[
				[
					'module_name' => 'Action log',
					'widget_name' => 'Test copy Action log',
					'action' => 'copy widget'
				]
			],
			[
				[
					'module_name' => 'Graph (classic)',
					'widget_name' => 'Test copy classic Graph',
					'action' => 'replace',
					'target' => 'Test copy Graph prototype'
				]
			],
			[
				[
					'module_name' => 'Favorite graphs',
					'widget_name' => 'Test copy Favorite graphs',
					'action' => 'copy page'
				]
			],
			[
				[
					'module_name' => 'Item history',
					'widget_name' => 'Item history widget',
					'action' => 'copy widget',
					'template' => true
				]
			],
			[
				[
					'module_name' => 'URL',
					'widget_name' => 'URL widget',
					'action' => 'replace',
					'target' => 'Graph prototype widget',
					'template' => true
				]
			],
			[
				[
					'module_name' => 'Item value',
					'widget_name' => 'Item value widget',
					'action' => 'copy page',
					'template' => true
				]
			]
		];
	}

	/**
	 * Function that checks copy of widgets with disabled modules.
	 *
	 * @dataProvider getModuleDashboardWidgetData
	 */
	public function testDashboardCopyWidgets_CopyDisabledModuleWidgets($data) {
		$url = CTestArrayHelper::get($data, 'template')
			? 'zabbix.php?action=template.dashboard.edit&dashboardid='.self::$templated_dashboardid
			: 'zabbix.php?action=dashboard.view&dashboardid='.self::$modules_dashboardid;
		$this->page->login()->open($url)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->waitUntilVisible();

		// Copy widget or dashboard page.
		if ($data['action'] === 'copy page') {
			$page_name = CTestArrayHelper::get($data, 'template') ? 'Page with widgets' : 'Page 1';
			$this->query('xpath://span[text()='.CXPathHelper::escapeQuotes($page_name).']/../button')
					->waitUntilClickable()->one()->click();
			CPopupMenuElement::find()->one()->waitUntilVisible()->select('Copy');
		}
		else {
			$dashboard->copyWidget($data['widget_name']);
		}

		// Disable widget module that corresponds to the copied widget or to one of the widgets on the copied page.
		$this->page->open('zabbix.php?action=module.list');
		$this->query('class:list-table')->asTable()->one()->findRow('Name', $data['module_name'])
				->query('link', 'Enabled')->one()->click();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Module disabled');

		// Open dashboard and execute the required action with the disabled module widget.
		$this->page->open($url)->waitUntilReady();
		$dashboard->invalidate();

		// Get count of inaccessible widgets.
		$inaccessible_xpath = 'xpath:.//div[contains(@class, "dashboard-widget-inaccessible")]';
		$count = $dashboard->query($inaccessible_xpath)->waitUntilVisible()->count();

		// Template dashbards are always in edit mode, so entering edit mode is only required for regular dashboards.
		if(!array_key_exists('template', $data)) {
			$dashboard->edit();
		}

		switch ($data['action']) {
			case 'copy widget':
				$dashboard->pasteWidget();

				// Check that the number on inaccessible widgets is still the same.
				$this->assertEquals($count, $dashboard->query($inaccessible_xpath)->waitUntilVisible()->count());
				break;

			case 'replace':
				$dashboard->replaceWidget($data['target']);

				// Check that the number on inaccessible widgets is still the same.
				$this->assertEquals($count, $dashboard->query($inaccessible_xpath)->waitUntilVisible()->count());

				// Make sure that the target widget is still present
				$this->assertTrue($dashboard->getWidget($data['target'])->isValid());
				break;

			case 'copy page':
				$this->query('id:dashboard-add')->one()->click();
				CPopupMenuElement::find()->one()->waitUntilVisible()->select('Paste page');
				$this->query("xpath:(//span[@title=".CXPathHelper::escapeQuotes($page_name)."])[2]")
						->waitUntilVisible()->one();

				// Check that no inaccessible widgets are present on the pasted page.
				$dashboard->selectPage($page_name, 2);
				$this->assertFalse($dashboard->query($inaccessible_xpath)->one(false)->isValid());
				break;
		}

		$message = ($data['action'] === 'copy page')
			? 'Inaccessible widgets were not pasted.'
			: 'Cannot paste inaccessible widget.';
		$this->assertMessage('warning', $message);

		// Cancel editing dashboard not to interfere with following cases from data provider.
		$this->query('link:Cancel')->one()->click();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}
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
