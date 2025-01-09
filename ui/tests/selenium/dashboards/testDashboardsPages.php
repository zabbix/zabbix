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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

use Facebook\WebDriver\WebDriverKeys;

/**
 * @backup dashboard
 *
 * @onBefore prepareDashboardData
 */
class testDashboardsPages extends CWebTest {

	/**
	 * Next page button in dashboard.
	 *
	 * @var string
	 */
	const NEXT_BUTTON = 'xpath://button[contains(@class, "btn-dashboard-next-page")]';

	/**
	 * Previous page button in dashboard.
	 *
	 * @var string
	 */
	const PREVIOUS_BUTTON = 'xpath://button[contains(@class, "btn-dashboard-previous-page")]';

	/**
	 * Attach MessageBehavior to the test.
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Id of dashboard by name.
	 *
	 * @var integer
	 */
	protected static $ids;

	/**
	 * Create new dashboards for autotest.
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
								'name' => 'First page clock',
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
								'name' => 'Second page clock',
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
								'name' => 'First page clock 1',
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0
							],
							[
								'type' => 'graph',
								'name' => 'Graph (classic) widget',
								'x' => 5,
								'y' => 4,
								'width' => 8,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => 4,
										'name' => 'itemid.0',
										'value' => 400410
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'FEDCB'
									]
								]
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
				'pages' => [[],[],[]]
			],
			[
				'name' => 'Dashboard for pages empty name',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for limit check and navigation',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [[], [], [], [], [], [], [], [], [], [],[], [], [], [], [], [], [], [], [], [],[], [], [], [],
					[],[], [], [], [], [],[], [], [], [], [], [], [], [], [], [],[], [], [], [], [], [], [], [], [], []]
			],
			[
				'name' => 'Dashboard for paste',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for page navigation',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => 'long_name_to_check_navigation_1'
					],
					[
						'name' => 'long_name_to_check_navigation_2'
					],
					[
						'name' => 'long_name_to_check_navigation_3'
					],
					[
						'name' => 'long_name_to_check_navigation_4'
					],
					[
						'name' => 'long_name_to_check_navigation_5'
					],
					[
						'name' => 'long_name_to_check_navigation_6'
					],
					[
						'name' => 'long_name_to_check_navigation_7'
					],
					[
						'name' => 'long_name_to_check_navigation_8'
					]
				]
			]
		]);
		$this->assertArrayHasKey('dashboardids', $response);
		self::$ids = CDataHelper::getIds('name');
	}

	/**
	 * Check layout of objects related to dashboard page.
	 */
	public function testDashboardsPages_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&new=1')->waitUntilReady();
		$dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$properties_form = $dialog->query('name:dashboard_properties_form')->asForm()->one();
		$properties_form->fill(['Name' => 'Dashboard creation']);
		$properties_form->submit();
		$this->page->waitUntilReady();

		// Check popup-menu options.
		$this->query('id:dashboard-add')->one()->click();
		$add_menu = CPopupMenuElement::find()->waitUntilVisible()->one();

		// Check add page form.
		$add_menu->select('Add page');
		$this->checkPageProperties();

		// Check page popup-menu options in edit mode.
		$page_menu = $this->getPageMenu('Page 1');
		$page_menu->hasTitles('ACTIONS');
		$page_popup_items = [
			'Copy' => true,
			'Delete' => false,
			'Properties' => true
		];
		foreach ($page_popup_items as $item => $enabled) {
			$this->assertTrue($page_menu->getItem($item)->isEnabled($enabled));
		}

		// Check page properties in edit mode.
		$page_menu->select('Properties');
		$this->checkPageProperties();
		$this->query('id:dashboard-cancel')->one()->click();
		$this->page->waitUntilReady();

		// Check Stop/Start slideshow.
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for layout'])->waitUntilReady();
		foreach (['Stop', 'Start'] as $status) {
			$this->assertTrue($this->query('xpath://button/span[contains(@class, "slideshow-state") and text()="'.
					$status.' slideshow"]')->one()->isDisplayed()
			);
			$this->query('xpath://button[contains(@class, "slideshow-state")]')->one()->click();
		}

		// Check page popup-menu options in created dashboard.
		$this->getPageMenu('First_page_name');
		$page_menu->hasTitles('ACTIONS');
		$this->assertEquals(['Copy', 'Properties'], $page_menu->getItems()->asText());

		// Check page properties in created dashboard.
		$page_menu->select('Properties');
		$this->checkPageProperties();
	}

	/**
	 * Copy dashboard page to same dashboard and another one.
	 */
	public function testDashboardsPages_CopyPastePage() {
		$query_pageid = 'SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid=';
		$query_widgets = 'SELECT type, name, x, y, width, height, view_mode FROM widget WHERE dashboard_pageid=';
		$query_widgetid = 'SELECT widgetid FROM widget WHERE dashboard_pageid=';
		$query_widgetfields = 'SELECT type, name, value_int, value_str, value_groupid FROM widget_field WHERE'.
				' name!=\'reference\' AND widgetid=';

		foreach ([self::$ids['Dashboard for copy'], self::$ids['Dashboard for paste']] as $dashboardid) {
			$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for copy'])
					->waitUntilReady();
			$dashboard = CDashboardElement::find()->one();

			// Save hash and copy first page.
			$dashboard->edit();
			$this->page->waitUntilReady();
			$this->selectPageAction('first_page_copy', 'Copy');
			$first_pageid = CDBHelper::getValue($query_pageid.zbx_dbstr(self::$ids['Dashboard for copy']).' ORDER BY dashboard_pageid DESC');
			$first_page_widgets = CDBHelper::getHash($query_widgets.zbx_dbstr($first_pageid));
			$graph_widgetid = CDBHelper::getValue($query_widgetid.zbx_dbstr($first_pageid).' ORDER BY widgetid DESC');
			$widgetfield_hash = CDBHelper::getHash($query_widgetfields.zbx_dbstr($graph_widgetid));

			// Open another dashboard to paste copied page.
			if ($dashboardid === self::$ids['Dashboard for paste']) {
				$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid)->waitUntilReady();
				$dashboard->edit();
				$this->page->waitUntilReady();
			}

			// Save dashboard page names before copy and paste page.
			$titles_before = $this->getPagesTitles();
			$this->query('id:dashboard-add')->one()->click();
			CPopupMenuElement::find()->waitUntilVisible()->one()->select('Paste page');
			$dashboard->waitUntilReady();

			// Wait until the second page appears.
			$this->query('xpath://li[@class="sortable-item"][2]')->waitUntilVisible()->one();

			// Copied page added.
			$titles_before[] = 'first_page_copy';

			// Assert that new page added.
			$this->assertEquals($titles_before, $this->getPagesTitles());
			$dashboard->save();
			$this->page->waitUntilReady();

			// Check and compare widgets of copied page.
			$pasted_pageid = CDBHelper::getValue($query_pageid.zbx_dbstr($dashboardid).' ORDER BY dashboard_pageid DESC');
			$this->assertEquals($first_page_widgets, CDBHelper::getHash($query_widgets.zbx_dbstr($pasted_pageid)));
			$pasted_graph_widgetid = CDBHelper::getValue($query_widgetid.zbx_dbstr($pasted_pageid).' ORDER BY widgetid DESC');
			$this->assertEquals($widgetfield_hash, CDBHelper::getHash($query_widgetfields.zbx_dbstr($pasted_graph_widgetid)));
		}
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
			// #2 Trimming leading and trailing spaces.
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
					],
					'duplicate' => true
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
	public function testDashboardsPages_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for page creation'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();
		$dashboard->addPage();
		$page_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$page_dialog->query('name:dashboard_page_properties_form')->asForm()->one()->fill($data['fields'])->submit();
		$page_dialog->ensureNotPresent();
		$dashboard->waitUntilReady();

		$title = $data['fields']['Name'];
		if (CTestArrayHelper::get($data, 'trim', false)) {
			$title = trim($data['fields']['Name']);
		}

		$this->assertTrue(in_array($title, $this->getPagesTitles(), true));
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		$next_page = $this->query(self::NEXT_BUTTON)->one();
		$tab = $this->query('class:selected-tab')->one();

		// If next page button exists and enabled press next tab buttun until the required tab is selected.
		if ($next_page->isClickable()) {
			while ($tab->getText() !== $title && $next_page->isClickable()) {
				$next_page->click();
				$tab->waitUntilAttributesNotPresent(['class' => 'selected-tab']);
				$tab->reload();
			}
		}

		$index = CTestArrayHelper::get($data, 'duplicate', false) ? 2 : 1;
		$this->selectPageAction($title, 'Properties', $index);
		COverlayDialogElement::find()->waitUntilReady()->one();
		$page_form = $page_dialog->query('name:dashboard_page_properties_form')->asForm()->one();
		$page_form->checkValue(['Name' => $title, 'Page display period' => $data['fields']['Page display period']]);
	}

	/**
	 * Check displayed error message adding more than 50 pages.
	 */
	public function testDashboardsPages_MaximumPageError() {
		$sql = 'SELECT * FROM dashboard_page WHERE dashboardid ='.zbx_dbstr(self::$ids['Dashboard for limit check and navigation']);
		$hash = CDBHelper::getHash($sql);
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for limit check and navigation'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->addPage();
		$this->assertMessage('warning', 'Cannot add dashboard page: maximum number of 50 dashboard pages has been added.');
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals(CDBHelper::getHash($sql), $hash);
	}

	/**
	 * Switch pages using next/previous arrow buttons.
	 */
	public function testDashboardsPages_Navigation() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for page navigation'])
				->waitUntilReady();
		$next_page = $this->query(self::NEXT_BUTTON)->one();
		$previous_page = $this->query(self::PREVIOUS_BUTTON)->one();

		// Check selected page.
		$this->assertEquals('long_name_to_check_navigation_1', $this->query('xpath://li/div[@class="selected-tab"]')->one()->getText());

		// Navigate on dashboard.
		foreach ([$next_page, $previous_page] as $navigation) {
			while ($navigation->isClickable()) {
				$navigation->click();
			}

			if ($navigation === $next_page) {
				$this->assertTrue($next_page->isEnabled(false));
				$this->assertTrue($previous_page->isEnabled());
				$this->assertEquals('long_name_to_check_navigation_8',
						$this->query('xpath://li/div[@class="selected-tab"]')->one()->getText()
				);
			}
		}

		$this->assertTrue($next_page->isEnabled());
		$this->assertTrue($previous_page->isEnabled(false));
	}

	/**
	 * Delete pages.
	 */
	public function testDashboardsPages_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for page delete'])
				->waitUntilReady();
		$this->assertEquals(['Page 1', 'Page 2', 'Page 3'], $this->getPagesTitles());
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();

		// Remove second page. All three pages are without names. Their name, should be changed according page amount.
		$this->selectPageAction('Page 2', 'Delete');
		$this->assertEquals(['Page 1', 'Page 3'], $this->getPagesTitles());
		$dashboard->save();
		$this->assertEquals(['Page 1', 'Page 2'], $this->getPagesTitles());
		$dashboard->edit();
		$this->selectPageAction('Page 2', 'Delete');
		$this->assertEquals(['Page 1'], $this->getPagesTitles());

		// Check that Delete option is disabled when one page left.
		$page_menu = $this->getPageMenu('Page 1');
		$this->assertTrue($page_menu->query('xpath:.//a[@aria-label="Actions, Delete"]')->one()->isEnabled(false));

		// Press Escape key to close page menu before saving the dashboard.
		$this->page->pressKey(WebDriverKeys::ESCAPE);
		$page_menu->waitUntilNotVisible();

		$dashboard->save();
		$this->assertEquals(['Page 1'], $this->getPagesTitles());
	}

	/**
	 * Check default page names adding new pages.
	 */
	public function testDashboardsPages_EmptyPagesName() {
		// Check that first page does not have any names.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for pages empty name'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();
		$this->assertEquals(['Page 1'], $this->getPagesTitles());
		$this->selectPageAction('Page 1', 'Properties');
		$page_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$page_dialog->query('name:dashboard_page_properties_form')->asForm()->one()->checkValue(['Name' => '']);
		$page_dialog->query('button:Cancel')->one()->click();

		// Check popup-menu options and add page with name.
		foreach(['not_page_number', ''] as $page_name) {
			$dashboard->addPage();
			COverlayDialogElement::find()->waitUntilReady()->one();

			$form = $page_dialog->query('name:dashboard_page_properties_form')->asForm()->one();
			if ($page_name === 'not_page_number') {
				$form->fill(['Name' => 'not_page_number']);
			}
			else {
				$form->checkValue(['Name' => '']);
			}

			$page_dialog->query('button:Apply')->one()->click();
			COverlayDialogElement::ensureNotPresent();
			$dashboard->waitUntilReady();
			$allpage_name = ($page_name === 'not_page_number') ? ['Page 1', 'not_page_number'] : ['Page 1', 'not_page_number', 'Page 3'];
			$this->assertEquals($allpage_name, $this->getPagesTitles());
		}

		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertEquals(['Page 1', 'not_page_number', 'Page 3'], $this->getPagesTitles());
	}

	/**
	 * Check navigation in kiosk mode.
	 *
	 * @backup profiles
	 */
	public function testDashboardsPages_KioskMode() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for kiosk'])
				->waitUntilReady();
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();

		// Switch pages next/previous.
		$dashboard = CDashboardElement::find()->one();
		foreach (['btn-dashboard-kioskmode-next-page', 'btn-dashboard-kioskmode-previous-page'] as $direction) {
			$widget_name = ($direction === 'btn-dashboard-kioskmode-next-page')
				? ['First', 'Second', 'Third']
				: ['First', 'Third', 'Second'];

			foreach ($widget_name as $widget) {
				$this->assertEquals($widget.' page kiosk', $dashboard->getWidgets()->last()->getHeaderText());
				$this->query('xpath://button[contains(@class, '.CXPathHelper::escapeQuotes($direction).')]')
						->one()->hoverMouse()->click();
			}
		}

		// Control panel screenshot - start/stop/next/previous.
		// TODO: uncomment after fix DEV-2700
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

	/**
	 * Check default period change for page.
	 */
	public function testDashboardsPages_DefaultPeriod() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for page delete'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();
		foreach (['10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '30 minutes', '1 hour'] as $default) {
			$this->query('id:dashboard-config')->one()->click();
			$properties = COverlayDialogElement::find()->waitUntilReady()->one();
			$properties->query('name:dashboard_properties_form')->asForm()->one()->fill(['Default page display period' => $default]);
			$properties->query('button:Apply')->one()->click();
			$dashboard->waitUntilReady();

			// Check that default time for page changed in edit mode and after dashboard save.
			foreach([true, false] as $save_dashboard) {
				$this->selectPageAction('Page 1', 'Properties');
				$page_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
				$page_dialog->query('name:dashboard_page_properties_form')->asForm()->one()
						->checkValue(['Page display period' => 'Default ('.$default.')']);
				$page_dialog->query('button:Cancel')->one()->click();

				if ($save_dashboard) {
					$dashboard->save();
					$this->page->waitUntilReady();
					$dashboard->edit();
				}
			}
		}
	}

	/**
	 * Open page popup menu.
	 *
	 * @param string $page_name		page name where to open menu
	 * @param integer $index		number of page that has duplicated name
	 */
	private function getPageMenu($page_name, $index = 1) {
		$selector = '//div[@class="dashboard-navigation-tabs"]//span[@title='.CXPathHelper::escapeQuotes($page_name);

		$value = $this->query('xpath:('.$selector.']/../../div)['.$index.']')->waitUntilVisible()->one()->getAttribute('class');
		if ($value !== 'selected-tab') {
			CDashboardElement::find()->one()->selectPage($page_name, $index);
		}
		$this->query('xpath:('.$selector.']/following-sibling::button)['.$index.']')->waitUntilClickable()->one()->click();

		return CPopupMenuElement::find()->waitUntilVisible()->one();
	}

	/**
	 * Select action from pages popup menu.
	 *
	 * @param string $page_name		page name where to open menu
	 * @param string $menu_item		action name
	 * @param integer $index		number of page that has duplicated name
	 */
	private function selectPageAction($page_name, $menu_item, $index = 1) {
		$this->getPageMenu($page_name, $index)->select($menu_item);
	}

	/**
	 * Get pages names.
	 *
	 * @return string
	 */
	private function getPagesTitles() {
		$pages = $this->query('xpath://li[@class="sortable-item"]')->all();

		return $pages->asText();
	}

	/**
	 * Checks dashboard page properties.
	 */
	private function checkPageProperties() {
		$page_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$page_form = $page_dialog->query('name:dashboard_page_properties_form')->asForm()->one();
		$this->assertEquals('255', $page_form->query('id:name')->one()->getAttribute('maxlength'));
		$this->assertEquals('Dashboard page properties', $page_dialog->getTitle());
		$this->assertEquals(['Name', 'Page display period'], $page_form->getLabels()->asText());
		$this->assertEquals(['Default (30 seconds)', '10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes',
				'30 minutes', '1 hour'], $page_form->query('name:display_period')->asDropdown()->one()->getOptions()->asText()
		);
		$page_dialog->query('button:Cancel')->one()->click();
	}
}
