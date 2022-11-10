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
 * @onBefore getTemplatedIds
 *
 * @backup widget, profiles
 */
class testDashboardCopyWidgets extends CWebTest {

	// Constants for regular dashboard cases.
	const NEW_PAGE_NAME = 'Test_page';
	const PASTE_DASHBOARD_NAME = 'Dashboard for Paste widgets';

	// Constants for templated dashboard cases.
	const TEMPLATED_DASHBOARD_NAME = 'Templated dashboard with all widgets';
	const TEMPLATED_PAGE_NAME = 'Page for pasting widgets';
	const EMPTY_DASHBOARD_NAME = 'Dashboard without widgets';
	private static $templated_dashboardid;
	private static $templated_empty_dashboardid;

	// Values for replacing widgets.
	private static $replaced_widget_name = "Test widget for replace";
	const REPLACED_WIDGET_SIZE = [ 'width' => '13', 'height' => '8'];

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/*
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

	/*
	 * Get ids for templated dashboard cases.
	 */
	public static function getTemplatedIds() {
		self::$templated_dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.
				zbx_dbstr(self::TEMPLATED_DASHBOARD_NAME)
		);
		self::$templated_empty_dashboardid = CDBHelper::getValue('SELECT dashboardid FROM dashboard WHERE name ='.
				zbx_dbstr(self::EMPTY_DASHBOARD_NAME)
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

		// Write name for replacing widget next case.
		if ($replace) {
			self::$replaced_widget_name = $widget_name;
		}

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
		$original_form = $widget->getFields()->asValues();

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
		$this->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$copied_widget = $dashboard->getWidgets()->last();

		// For Other dashboard and Map from Navigation tree case - add map source, because it is not being copied by design.
		if (($new_dashboard || $new_page) && stristr($widget_name, 'Map from tree')) {
			$copied_widget_form = $copied_widget->edit();
			$copied_widget_form->fill(['Filter' => 'Test copy Map navigation tree']);
			$copied_widget_form->submit();
		}

		$this->assertEquals($widget_name, $copied_widget->getHeaderText());
		$copied_fields = $copied_widget->edit()->getFields();

		// Check tags of original and copied widget.
		if (stristr($widget_name, 'Problem')) {
			$copied_tags = COverlayDialogElement::find()->waitUntilReady()->one()->query('id:tags_table_tags')
					->asMultifieldTable()->one()->getValue();
			$this->assertEquals($tags, $copied_tags);
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
