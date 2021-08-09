<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareDashboardData
 */
class testDashboardPages extends CWebTest {

	private const DEFAULT_TIME = '1 minute';

	/**
	 * Attach MessageBehavior to the test.
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Id of dashboard for layout check.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	/**
	 * Id of dashboard for copy.
	 *
	 * @var integer
	 */
	protected static $dashboardid_copy;

	/**
	 * Id of dashboard for kiosk mode.
	 *
	 * @var integer
	 */
	protected static $dashboardid_kiosk;

	/**
	 * New dashboard with two pages.
	 */
	public function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for page creation',
				'display_period' => 60,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => 'first_page_name',
						'widgets' => [
							[
								'name' => 'First page clocks',
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0
							]
						]
					],
					[
						'name' => 'second_page_name',
						'widgets' => [
							[
								'name' => 'Second page clocks',
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for copy',
				'display_period' => 60,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => 'first_page_copy',
						'widgets' => [
							[
								'name' => 'First page clocks',
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0
							]
						]
					],
					[
						'name' => 'second_page_copy',
						'widgets' => [
							[
								'name' => 'Second page clocks',
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for kiosk',
				'display_period' => 60,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => 'first_page_kiosk',
						'widgets' => [
							[
								'name' => 'First page kiosk',
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0
							]
						]
					],
					[
						'name' => 'second_page_kiosk',
						'widgets' => [
							[
								'name' => 'Second page kiosk',
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0
							]
						]
					],
					[
						'name' => 'third_page_kiosk',
						'widgets' => [
							[
								'name' => 'Third page kiosk',
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0
							]
						]
					]
				]
			]
		]);
		$this->assertArrayHasKey('dashboardids', $response);
		self::$dashboardid = $response['dashboardids'][0];
		self::$dashboardid_copy = $response['dashboardids'][1];
		self::$dashboardid_kiosk = $response['dashboardids'][2];
	}

	public function testDashboardPages_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&new=1')->waitUntilReady();
		$dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$properties_form = $dialog->query('name:dashboard_properties_form')->asForm()->one();
		$this->assertEquals(['Owner', 'Name', 'Default page display period', 'Start slideshow automatically'],
				$properties_form->getLabels()->asText());

		// All available display period time.
		$this->assertEquals(['10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '30 minutes', '1 hour'],
				$properties_form->query('name:display_period')->asZDropdown()->one()->getOptions()->asText());
		$properties_form->fill(['Name' => 'Dashboard creation', 'Default page display period' => self::DEFAULT_TIME]);
		$properties_form->submit();
		$this->page->waitUntilReady();

		// Check popup-menu options.
		$this->query('id:dashboard-add')->one()->click();
		$add_menu = $this->query('xpath://ul[@role="menu"]')->asPopupMenu()->one();
		$this->assertEquals(['Add widget', 'Add page', 'Paste widget', 'Paste page'], $add_menu->getItems()->asText());

		// Check add page form.
		$add_menu->select('Add page');
		$this->checkPageProperties('', self::DEFAULT_TIME);

		// Check page popup-menu options in edit mode.
		$this->openPageMenu('Page 1');
		$page_menu = $this->query('xpath://ul[@role="menu"]')->asPopupMenu()->one();
		$page_menu->hasTitles('ACTIONS');
		$this->assertEquals(['Copy', 'Delete', 'Properties'], $page_menu->getItems()->asText());

		// Check page properties in edit mode.
		$page_menu->select('Properties');
		$this->checkPageProperties('', self::DEFAULT_TIME);
		$this->query('id:dashboard-cancel')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check Stop/Start slideshow.
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		foreach (['Stop', 'Start'] as $status) {
			$this->assertTrue($this->query('xpath://button/span[contains(@class, "slideshow-state") and text()="'.
					$status.' slideshow"]')->one()->isDisplayed());
			$this->query('xpath://button[contains(@class, "slideshow-state")]')->one()->click();
		}

		// Check page popup-menu options in created dashboard.
		$this->openPageMenu('first_page_name');
		$page_menu->hasTitles('ACTIONS');
		$this->assertEquals(['Copy', 'Properties'], $page_menu->getItems()->asText());

		// Check page properties in created dashboard.
		$page_menu->select('Properties');
		$this->checkPageProperties('first_page_name', self::DEFAULT_TIME);
	}

	public function testDashboardPages_CopyPage() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid_copy)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();

		// Save widget name on first page.
		$widget_header = $dashboard->getWidgets()->last()->getHeaderText();

		// Save dashboard page names before copy.
		$pages_before = $this->getTitles();
		$this->selectPageAction('first_page_copy', 'Copy');
		$dashboard->edit();
		$this->page->waitUntilReady();
		$this->query('id:dashboard-add')->one()->click();
		$this->query('xpath://ul[@role="menu"]')->asPopupMenu()->one()->select('Paste page');
		$this->page->waitUntilReady();

		// Copied page added.
		array_push($pages_before, 'first_page_copy');
		sleep(1);

		// Assert that new page added.
		$this->assertEquals($pages_before, $this->getTitles());

		// Check that same widget copied with added page.
		$this->assertEquals($widget_header, $dashboard->getWidgets()->last()->getHeaderText());

		// Change widget name, to be sure that this page is correct after dashboard save.
		$dashboard->getWidgets()->last()->edit()->fill(['Name' => 'First page clocks + changed name'])->submit();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($pages_before, $this->getTitles());
		$this->selectPage('first_page_copy', 2);
		$this->assertEquals('First page clocks + changed name', $dashboard->getWidgets()->last()->getHeaderText());
	}

	public function testDashboardPages_KioskMode() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid_kiosk)->waitUntilReady();
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();

		// Switch pages next/previous.
		$dashboard = CDashboardElement::find()->one();
		foreach (['next', 'previous'] as $direction) {
			$widget_name = ['First', 'Second', 'Third'];
			if ($direction === 'previous') {
				$widget_name = ['First', 'Third', 'Second'];
			}
			foreach ($widget_name as $widget) {
				$this->assertEquals($widget.' page kiosk', $dashboard->getWidgets()->last()->getHeaderText());
				$this->query('xpath://button[contains(@class, "'.$direction.'-page")]')->one()->click()->waitUntilReady();
			}
		}

		// Control panel screenshot - start/stop status.
//		$this->page->removeFocus();
//		foreach (['Stop', 'Start'] as $status) {
//			$screenshot_area = $this->query('xpath://ul[@class="header-kioskmode-controls"]')->waitUntilVisible()->one();
//			$this->assertScreenshot($screenshot_area, $status);
//			$this->query('xpath://button[@title="'.$status.' slideshow"]')->one()->click();
//		}

		// Check that returned from kiosk view.
		$this->query('xpath://button[@title="Normal view"]')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Dashboard for kiosk');
	}

	private function checkPageProperties($page_name, $default_time) {
		$page_dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$this->assertEquals('Dashboard page properties', $page_dialog->getTitle());
		$page_form = $page_dialog->query('name:dashboard_page_properties_form')->asForm()->one();
		$page_form->checkValue(['Name' => $page_name]);
		$this->assertEquals(['Name', 'Page display period'], $page_form->getLabels()->asText());
		$this->assertEquals(['Default ('.$default_time.')', '10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '30 minutes', '1 hour'],
				$page_form->query('name:display_period')->asZDropdown()->one()->getOptions()->asText());
		$page_dialog->query('button:Cancel')->one()->click();
	}

	private function openPageMenu($page_name, $index = 1) {
		$selector = '//div[@class="dashboard-navigation-tabs"]//ul[@class="sortable-list"]//span[@title='.CXPathHelper::escapeQuotes($page_name);

		$value = $this->query('xpath:('.$selector.']/../../div)['.$index.']')->one()->getAttribute('class');
		if ($value !== 'selected-tab') {
			$this->selectPage($page_name, $index);
		}
		$this->query('xpath:('.$selector.']/following::button)['.$index.']')->waitUntilPresent()->one()->click()->waitUntilReady();
	}

	private function selectPageAction($page_name, $menu_item, $index = 1) {
		$this->openPageMenu($page_name, $index);
		$this->query('xpath://ul[@role="menu"]')->waitUntilVisible()->asPopupMenu()->one()->select($menu_item);
	}

	private function getTitles() {
		$pages = $this->query('xpath:.//li[@class="sortable-item"]')->all();
		if ($pages->count() > 0) {
			return $pages->asText();
		}
	}
}
