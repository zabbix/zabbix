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
	 * Id of dashboard for page creation.
	 *
	 * @var integer
	 */
	protected static $dashboardid_creation;

	/**
	 * Id of dashboard for page delete.
	 *
	 * @var integer
	 */
	protected static $dashboardid_delete;
	
	/**
	 * Id of dashboard for empty pages name.
	 *
	 * @var integer
	 */
	protected static $dashboardid_empty;

	/**
	 * New dashboards.
	 */
	public function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for layout',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => 'First_page_name',
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
				'display_period' => 30,
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
				'display_period' => 30,
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
			],
			[
				'name' => 'Dashboard for page creation',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => 'first_page_creation'
					]
				]
			],
			[
				'name' => 'Dashboard for page delete',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
					],
					[
					],
					[
					]
				]
			],
			[
				'name' => 'Dashboard for pages empty name',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
					]
				]
			],
			
		]);
		$this->assertArrayHasKey('dashboardids', $response);
		self::$dashboardid = $response['dashboardids'][0];
		self::$dashboardid_copy = $response['dashboardids'][1];
		self::$dashboardid_kiosk = $response['dashboardids'][2];
		self::$dashboardid_creation = $response['dashboardids'][3];
		self::$dashboardid_delete = $response['dashboardids'][4];
		self::$dashboardid_empty = $response['dashboardids'][5];
	}

	/**
	 * Page layout.
	 */
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
		$properties_form->fill(['Name' => 'Dashboard creation', 'Default page display period' => '30 seconds']);
		$properties_form->submit();
		$this->page->waitUntilReady();

		// Check popup-menu options.
		$this->query('id:dashboard-add')->one()->click();
		$add_menu = $this->query('xpath://ul[@role="menu"]')->asPopupMenu()->one();
		$this->assertEquals(['Add widget', 'Add page', 'Paste widget', 'Paste page'], $add_menu->getItems()->asText());

		// Check add page form.
		$add_menu->select('Add page');
		$this->checkPageProperties();

		// Check page popup-menu options in edit mode.
		$this->openPageMenu('Page 1');
		$page_menu = $this->query('xpath://ul[@role="menu"]')->asPopupMenu()->one();
		$page_menu->hasTitles('ACTIONS');
		$this->assertEquals(['Copy', 'Delete', 'Properties'], $page_menu->getItems()->asText());

		// Check page properties in edit mode.
		$page_menu->select('Properties');
		$this->checkPageProperties();
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
		
		// Check Stop/Start slideshow changing automatic slideshow option.
		

		// Check page popup-menu options in created dashboard.
		$this->openPageMenu('First_page_name');
		$page_menu->hasTitles('ACTIONS');
		$this->assertEquals(['Copy', 'Properties'], $page_menu->getItems()->asText());

		// Check page properties in created dashboard.
		$page_menu->select('Properties');
		$this->checkPageProperties();
	}

	/**
	 * Copy dashboard page.
	 */
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
		$dashboard->waitUntilReady();

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

	public static function getCreateData() {
		return [
			// #0 Simple name.
			[
				[
					'fields' => [
						'Name' => 'Simple name',
						'Page display period' => '10 seconds'
					]
				]
			],
			// #1 Symbols.
			[
				[
					'fields' => [
						'Name' => '#!@#$%^&*()_+',
						'Page display period' => '30 seconds'
					]
				]
			],
			// #2 Before and after spacing.
			[
				[
					'fields' => [
						'Name' => '      trimmed name      ',
						'Page display period' => '1 minute'
					],
					'trim' => true
				]
			],
			// #3 Long name.
			[
				[
					'fields' => [
						'Name' => 'long_name_here_long_name_here_long_name_here_long_name_here_long_name_here',
						'Page display period' => '2 minutes'
					]
				]
			],
			// #4 Duplicate name.
			[
				[
					'fields' => [
						'Name' => 'first_page_creation',
						'Page display period' => '10 minutes'
					]
				]
			],
			// #5 cyrillic.
			[
				[
					'fields' => [
						'Name' => 'кириллица',
						'Page display period' => '30 minutes'
					]
				]
			],
			// #6 ASCII symbols.
			[
				[
					'fields' => [
						'Name' => '♥♥♥♥♥♥♥',
						'Page display period' => '1 hour'
					]
				]
			]
		];
	}

	/**
	 * Add new pages with different names to dashboard.
	 * 
	 * @dataProvider getCreateData
	 */
	public function testDashboardPages_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid_creation)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();
		$dashboard->addPage();
		$page_dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$page_dialog->query('name:dashboard_page_properties_form')->asForm()->one()->fill($data['fields'])->submit();
		$dashboard->waitUntilReady();

		$title = $data['fields']['Name'];
		if (CTestArrayHelper::get($data, 'trim', false)) {
			$title = trim($data['fields']['Name']);
		}
		
		$this->assertTrue(in_array($title, $this->getTitles(), true));
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$index = ($title === 'first_page_creation') ? 2 : 1;
		$this->checkPageValues($title, $data['fields']['Page display period'], $index);
	}

	/**
	 * Check displayed error message trying to add more than 50 pages.
	 */
	public function testDashboardPages_MaximumPageError() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&new=1')->waitUntilReady();
		$dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$properties_form = $dialog->query('name:dashboard_properties_form')->asForm()->one();
		$properties_form->fill(['Name' => 'Dashboard for limit check and navigation'])->submit();
		$this->page->waitUntilReady();

		$dashboard = CDashboardElement::find()->one();
		for ($i = 0; $i <= 49; $i++) {
			$dashboard->addPage();

			if ($i !== 49) {
				$page_dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
				$page_dialog->query('name:dashboard_page_properties_form')->asForm()->one()->submit();
				$dashboard->waitUntilReady();
			}
		}

		$this->assertMessage(TEST_BAD, 'Cannot add dashboard page: maximum number of 50 dashboard pages has been added.');
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard created');
	}

	/**
	 * Swich pages using next/previous arrow buttons.
	 * 
	 * @depends testDashboardPages_MaximumPageError
	 */
	public function testDashboardPages_Navigation() {
		$this->page->login()->open('zabbix.php?action=dashboard.list')->waitUntilReady();
		$this->query('link:Dashboard for limit check and navigation')->one()->click();
		$this->page->waitUntilReady();
		$next_page = $this->query('xpath://button[@class="dashboard-next-page btn-iterator-page-next"]')->one();
		$previous_page = $this->query('xpath://button[@class="dashboard-previous-page btn-iterator-page-previous"]')->one();
		$next_page->isEnabled();
		$previous_page->isEnabled(false);

		for ($i = 1; $i <= 3; $i++) {
			$this->assertEquals('Page '.$i, $this->query('xpath://div[@class="selected-tab"]/span')->one()->waitUntilPresent()->getText());
			if ($i !== 3) {
				$next_page->waitUntilReady()->click();
			}
		}
		$previous_page->isEnabled();

		for ($i = 3; $i >= 1; $i--) {
			$this->assertEquals('Page '.$i, $this->query('xpath://div[@class="selected-tab"]/span')->one()->waitUntilPresent()->getText());
			if ($i !== 1) {
				$previous_page->waitUntilReady()->click();
			}
		}
		$previous_page->isEnabled(false);
	}

	public function testDashboardPages_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid_delete)->waitUntilReady();
		$this->assertEquals(['Page 1', 'Page 2', 'Page 3'], $this->getTitles());
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();

		// Remove second page. All three pages are without names. Their name, should be changed according page amount.
		$this->selectPageAction('Page 2', 'Delete');
		$this->assertEquals(['Page 1', 'Page 3'], $this->getTitles());
		$dashboard->save();
		$this->assertEquals(['Page 1', 'Page 2'], $this->getTitles());
		$dashboard->edit();
		$this->selectPageAction('Page 2', 'Delete');
		$this->assertEquals(['Page 1'], $this->getTitles());

		// Check that Delete option is disabled when one page left.
		$this->openPageMenu('Page 1');
		$page_menu = $this->query('xpath://ul[@role="menu"]')->asPopupMenu()->one();
		$this->assertTrue($page_menu->query('xpath://a[@aria-label="Actions, Delete"]')->one()->isEnabled(false));
		$dashboard->save();
		$this->assertEquals(['Page 1'], $this->getTitles());
	}
	
	public function testDashboardPages_EmptyPagesName() {
		// Check that first page do not has any name.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid_empty)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();
		$this->assertEquals(['Page 1'], $this->getTitles());
		$this->selectPageAction('Page 1', 'Properties');
		$page_dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$page_dialog->query('name:dashboard_page_properties_form')->asForm()->one()->checkValue(['Name' => '']);
		$page_dialog->query('button:Cancel')->one()->click();
		
		// Check popup-menu options and add page with name.
		$dashboard->addPage();
		COverlayDialogElement::find()->waitUntilVisible()->one();
		$page_dialog->query('name:dashboard_page_properties_form')->asForm()->one()->fill(['Name' => 'not_page_number']);
		$page_dialog->query('button:Apply')->one()->click();
		$dashboard->waitUntilReady();
		$this->assertEquals(['Page 1', 'not_page_number'], $this->getTitles());
		
		// Add page without name and check that it has correct page order number in name.
		$dashboard->addPage();
		COverlayDialogElement::find()->waitUntilVisible()->one();
		$page_dialog->query('name:dashboard_page_properties_form')->asForm()->one()->checkValue(['Name' => '']);
		$page_dialog->query('button:Apply')->one()->click();
		$dashboard->waitUntilReady();
		$this->assertEquals(['Page 1', 'not_page_number', 'Page 3'], $this->getTitles());
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertEquals(['Page 1', 'not_page_number', 'Page 3'], $this->getTitles());
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

//		// Control panel screenshot - start/stop/next/previous.
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

	private function checkPageValues($page_name, $default_time, $index = 1) {
		$this->selectPageAction($page_name, 'Properties', $index);
		$page_dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$page_form = $page_dialog->query('name:dashboard_page_properties_form')->asForm()->one();
		$page_form->checkValue(['Name' => $page_name, 'Page display period' => $default_time]);
	}

	private function openPageMenu($page_name, $index = 1) {
		$selector = '//ul[@class="sortable-list"]//span[@title='.CXPathHelper::escapeQuotes($page_name);

		$value = $this->query('xpath:('.$selector.']/../../div)['.$index.']')->one()->getAttribute('class');
		if ($value !== 'selected-tab') {
			$this->selectPage($page_name, $index);
		}
		$this->query('xpath:('.$selector.']/following-sibling::button)['.$index.']')->waitUntilPresent()->one()->click()->waitUntilVisible();
	}

	private function selectPage($page_name, $index = 1) {
		$selection = '//ul[@class="sortable-list"]//span[@title=';
		$this->query('xpath:('.$selection.CXPathHelper::escapeQuotes($page_name).'])['.$index.']')
				->one()->click()->waitUntilReady();
		$this->query('xpath:'.$selection.CXPathHelper::escapeQuotes($page_name).']/../../div[@class="selected-tab"]')
				->one()->waitUntilPresent();
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

	private function checkPageProperties() {
		$page_dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$page_form = $page_dialog->query('name:dashboard_page_properties_form')->asForm()->one();
		$this->assertEquals('Dashboard page properties', $page_dialog->getTitle());
		$this->assertEquals(['Name', 'Page display period'], $page_form->getLabels()->asText());
		$this->assertEquals(['Default (30 seconds)', '10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '30 minutes', '1 hour'],
				$page_form->query('name:display_period')->asZDropdown()->one()->getOptions()->asText());
		$page_dialog->query('button:Cancel')->one()->click();
	}
}
