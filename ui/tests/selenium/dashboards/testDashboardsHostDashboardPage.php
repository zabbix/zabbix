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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';

define('CURRENT_YEAR', date("Y"));

/**
 * @onBefore prepareHostDashboardsData
 *
 * @backup hosts
 */
class testDashboardsHostDashboardPage extends CWebTest {

	const HOST_NAME = 'Host for Host Dashboards';
	const TEMPLATE_NAME = 'Template for '.self::HOST_NAME;
	const COUNT_MANY = 20;

	public function prepareHostDashboardsData() {
		$data = [
			'host_name' => self::HOST_NAME,
			'dashboards' => [
				[
					'name' => 'Dashboard 1',
					'pages' => [
						[
							'name' => 'Page 1',
							'widgets' => [
								[
									'type' => 'svggraph',
									'name' => 'Graph widget',
									'width' => 6,
									'height' => 4,
									'fields' => [
										[
											'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
											'name' => '*',
											'value' => 0
										]
									]
								]
							]
						],
						[
							'name' => 'Page 2'
						]
					]
				]
			]
		];
		$this->createHostWithDashboards($data);

		// Create a Host with many Dashboards and another Host with many Pages.
		$dashboard_array = [];
		$page_array = [];

		for ($i = 1; $i <= self::COUNT_MANY; $i++) {
			$dashboard_array[] = ['name' => 'Dashboard '.$i];
			$page_array[] = ['name' => 'Page '.$i];
		}

		$data_dashboards = [
			'host_name' => 'Many Dashboards',
			'dashboards' => $dashboard_array
		];
		$this->createHostWithDashboards($data_dashboards);

		$data_pages = [
			'host_name' => 'Many Pages',
			'dashboards' => [
				[
					'name' => 'Dashboard 1',
					'pages' => $page_array
				]
			]
		];
		$this->createHostWithDashboards($data_pages);
	}

	/**
	 * Check layout.
	 */
	public function testDashboardsHostDashboardPage_Layout() {
		$this->openDashboardsForHost(self::HOST_NAME);

		$this->page->assertTitle('Dashboards');
		$this->page->assertHeader('Host dashboards');

		$breadcrumbs = $this->query('class:breadcrumbs')->one();
		$this->assertEquals('zabbix.php?action=host.view', $breadcrumbs->query('link:All hosts')->one()->getAttribute('href'));
		$this->assertEquals(['All hosts', self::HOST_NAME], $breadcrumbs->query('tag:li')->all()->asText());

		$host_dashboard_navigation = $this->query('class:host-dashboard-navigation')->one();

		$prev_button = $host_dashboard_navigation->query('xpath:.//button[@title="Previous dashboard"]')->one();
		$this->assertTrue($prev_button->isDisplayed());
		$this->assertFalse($prev_button->isEnabled());

		$dashboard_tab = $host_dashboard_navigation->query('xpath:.//span[text()="Dashboard 1"]')->one();
		$this->assertEquals('Dashboard 1', $dashboard_tab->getAttribute('title'));

		// Assert the listed dashboard dropdown.
		$list_button = $host_dashboard_navigation->query('xpath:.//button[@title="Dashboard list"]')->one();
		$this->assertTrue($list_button->isClickable());
		$list_button->click();
		$popup_menu = $list_button->asPopupButton()->getMenu();
		$this->assertEquals(['Dashboard 1'], $popup_menu->getItems()->asText());
		$popup_menu->close();

		$next_button = $host_dashboard_navigation->query('xpath:.//button[@title="Next dashboard"]')->one();
		$this->assertTrue($next_button->isDisplayed());
		$this->assertFalse($next_button->isEnabled());

		// Check page tabs.
		$dashboard_navigation = $this->query('class:dashboard-navigation')->one();
		$this->assertEquals(['Page 1', 'Page 2'], $dashboard_navigation->query('xpath:.//li[@class="sortable-item"]')->all()->asText());

		// Check Slideshow button.
		foreach (['Stop', 'Start'] as $status) {
			$this->assertTrue($dashboard_navigation->query('xpath:.//button/span[text()="'.$status.' slideshow"]')->one()->isDisplayed());
			$dashboard_navigation->query('xpath:.//button['.CXPathHelper::fromClass('btn-dashboard-toggle-slideshow').']')->one()->click();
		}
	}

	/**
	 * Open and close the Kiosk mode.
	 */
	public function testDashboardsHostDashboardPage_CheckKioskMode() {
		$this->openDashboardsForHost(self::HOST_NAME);

		// Test Kiosk mode.
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();

		// Check that Header and Filter disappeared.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilNotVisible();
		$this->assertFalse(CFilterElement::find()->one()->isVisible());
		$this->assertFalse($this->query('class:host-dashboard-navigation')->exists());
		$this->assertTrue(CDashboardElement::find()->one()->getWidgets()->first()->isVisible());

		// Check that Breadcrumbs are still visible.
		$breadcrumbs = $this->query('xpath://ul[@class="breadcrumbs"]//span')->all();
		$this->assertEquals(['All hosts', self::HOST_NAME], $breadcrumbs->asText());

		foreach ($breadcrumbs as $breadcrumb) {
			$this->assertTrue($breadcrumb->isVisible());
		}

		// Check Dashboard page controls.
		foreach (['Previous page', 'Stop slideshow', 'Next page'] as $button) {
			$this->assertTrue($this->query('xpath://button[@title="'.$button.'"]')->exists());
		}

		// Check Slideshow button.
		$dashboard_controls = $this->query('class:dashboard-kioskmode-controls')->one();

		foreach (['Stop', 'Start'] as $status) {
			$this->assertTrue($dashboard_controls->query('xpath:.//button[@title="'.$status.' slideshow"]')->one()->isDisplayed());
			$dashboard_controls->query('xpath:.//button['.
					CXPathHelper::fromClass('btn-dashboard-kioskmode-toggle-slideshow').']')->one()->click();
		}

		$this->query('xpath://button[@title="Normal view"]')->waitUntilPresent()->one()->hoverMouse()->click();
		$this->page->waitUntilReady();

		// Check that Header and Filter are visible again.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilVisible();
		foreach (['xpath://div['.CXPathHelper::fromClass('filter-space').']', 'class:host-dashboard-navigation', 'class:dashboard'] as $selector) {
			$this->assertTrue($this->query($selector)->exists());
		}
	}

	public function getCheckFiltersData() {
		return [
			[
				[
					'fields' => ['id:from' => 'now-2h', 'id:to' => 'now-1h'],
					'expected_tab' => 'now-2h – now-1h',
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => true,
						'js-btn-time-right' => true
					]
				]
			],
			[
				[
					'fields' => ['id:from' => 'now-2y', 'id:to' => 'now-1y'],
					'expected_tab' => 'now-2y – now-1y',
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => true,
						'js-btn-time-right' => true
					]
				]
			],
			[
				[
					'link' => 'Last 30 days',
					'expected_fields' => ['id:from' => 'now-30d', 'id:to' => 'now'],
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => true,
						'js-btn-time-right' => false
					]
				]
			],
			[
				[
					'link' => 'Last 2 years',
					'expected_fields' => ['id:from' => 'now-2y', 'id:to' => 'now'],
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => false,
						'js-btn-time-right' => false
					]
				]
			],
			[
				[
					'fields' => ['id:from' => CURRENT_YEAR.'-01-01 00:00:00', 'id:to' => CURRENT_YEAR.'-01-01 01:00:00'],
					'expected_tab' => CURRENT_YEAR.'-01-01 00:00:00 – '.CURRENT_YEAR.'-01-01 01:00:00',
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => true,
						'js-btn-time-right' => true
					]
				]
			],
			[
				[
					'fields' => ['id:from' => '2023-01', 'id:to' => '2023-01'],
					'expected_fields' => ['id:from' => '2023-01-01 00:00:00', 'id:to' => '2023-01-31 23:59:59'],
					'expected_tab' => '2023-01-01 00:00:00 – 2023-01-31 23:59:59',
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => true,
						'js-btn-time-right' => true
					]
				]
			],
			[
				[
					'fields' => ['id:from' => '$#^$@', 'id:to' => '&nbsp;'],
					'error' => [
						'from' => 'Invalid date.',
						'to' => 'Invalid date.'
					]
				]
			],
			[
				[
					'fields' => ['id:from' => 'now-3y', 'id:to' => 'now'],
					'error' => [
						'from' => 'Maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			]
		];
	}

	/**
	 * Change values in the filter section and check the resulting changes.
	 *
	 * @dataProvider getCheckFiltersData
	 */
	public function testDashboardsHostDashboardPage_CheckFilters($data) {
		$this->openDashboardsForHost(self::HOST_NAME);
		$filter = CFilterElement::find()->one();
		$form = $filter->asForm(['normalized' => true]);

		// Set custom time filter.
		if (CTestArrayHelper::get($data, 'fields')) {
			$form->fill($data['fields']);
			$form->query('id:apply')->one()->click();
		}
		else {
			$form->query('link', $data['link'])->waitUntilClickable()->one()->click();
		}

		$this->page->waitUntilReady();

		// Check error message if such is expected.
		if (CTestArrayHelper::get($data, 'error')) {
			foreach ($data['error'] as $field => $text) {
				// Count of days mentioned in error depends ot presence of leap year february in selected period.
				if (CTestArrayHelper::get($data, 'days_count')) {
					$text = str_replace('{days}', CDateTimeHelper::countDays('now', 'P2Y'), $text);
				}
				$message = $this->query('xpath://ul[@data-error-for='.CXPathHelper::escapeQuotes($field).']//li')->one();
				$this->assertEquals($text, $message->getText());
			}
		}
		else {
			// If error not expected.

			// Check Zoom buttons.
			foreach ($data['zoom_buttons'] as $button => $state) {
				$this->assertTrue($this->query('xpath://button[contains(@class, '.CXPathHelper::escapeQuotes($button).
					')]')->one()->isEnabled($state)
				);
			}

			// Check field values.
			$form->checkValue(CTestArrayHelper::get($data, 'expected_fields', CTestArrayHelper::get($data, 'fields')));

			// Check tab title.
			$this->assertEquals(
				CTestArrayHelper::get($data, 'expected_tab', CTestArrayHelper::get($data, 'link')),
				$filter->getSelectedTabName()
			);
		}
	}

	public function getCheckNavigationTabsData() {
		return [
			[
				[
					'host_name' => 'One Dashboard - one Page',
					'dashboards' => [['name' => 'Dashboard 1']]
				]
			],
			[
				[
					'host_name' => 'One Dashboard - three Pages',
					'dashboards' => [
						[
							'name' => 'Dashboard 1',
							'pages' => [['name' => 'Page 1'], ['name' => 'Page 2'], ['name' => 'Page 3']]
						]
					]
				]
			],
			[
				[
					'host_name' => 'Three Dashboards - three Pages each',
					'dashboards' => [
						[
							'name' => 'Dashboard 1',
							'pages' => [['name' => 'Page 11'], ['name' => 'Page 12'], ['name' => 'Page 13']]
						],
						[
							'name' => 'Dashboard 2',
							'pages' => [['name' => 'Page 21'], ['name' => 'Page 22'], ['name' => 'Page 23']]
						],
						[
							'name' => 'Dashboard 3',
							'pages' => [['name' => 'Page 31'], ['name' => 'Page 32'], ['name' => 'Page 33']]
						]
					]
				]
			],
			[
				[
					'host_name' => 'Unicode Dashboards',
					'dashboards' => [
						['name' => '🙂🙃'],
						['name' => 'test тест 测试 テスト ทดสอบ'],
						['name' => '<script>alert("hi!");</script>'],
						['name' => '&nbsp; &amp;'],
						['name' => '☺♥²©™"\'']
					]
				]
			],
			[
				[
					'host_name' => 'Unicode Pages',
					'dashboards' => [
						[
							'name' => 'Dashboard 1',
							'pages' => [
								['name' => '🙂🙃'],
								['name' => 'test тест 测试 テスト ทดสอบ'],
								['name' => '<script>alert("hi!");</script>'],
								['name' => '&nbsp; &amp;'],
								['name' => '☺♥²©™"\'']
							]
						]
					]
				]
			],
			[
				[
					'host_name' => 'Long names',
					'dashboards' => [
						[
							'name' => STRING_255,
							'pages' => [['name' => STRING_255], ['name' => STRING_128]]
						]
					]
				]
			]
		];
	}

	/**
	 * Check Dashboard and Page navigation using tabs.
	 *
	 * @dataProvider getCheckNavigationTabsData
	 */
	public function testDashboardsHostDashboardPage_CheckNavigationTabs($data) {
		// Create the required entities in database.
		$api_dashboards = $this->createHostWithDashboards($data);

		$this->openDashboardsForHost($data['host_name']);

		// Parent to all Dashboard navigation elements.
		$navigation = $this->query('class:host-dashboard-navigation')->one();

		// Assert buttons.
		$prev_button = $navigation->query('xpath:.//button[@title="Previous dashboard"]')->one();
		$this->assertFalse($prev_button->isEnabled());
		$next_button = $navigation->query('xpath:.//button[@title="Next dashboard"]')->one();
		$this->assertEquals(count($api_dashboards) > 1, $next_button->isEnabled());

		// Assert dashboard Tabs and Pages.
		foreach ($api_dashboards as $dashboard) {
			$dashboard_tab = $navigation->query('xpath:.//span[text()='.CXPathHelper::escapeQuotes($dashboard['name']).']')->one();
			$this->assertEquals($dashboard['name'], $dashboard_tab->getAttribute('title'));

			// If not already on the correct Dashboard, then switch.
			if ($dashboard['name'] !== $navigation->query('xpath:.//div[@class="selected-tab"]')->one()->getText()) {
				$dashboard_tab->click();
				$this->page->waitUntilReady();
			}

			/*
			 * Check Page switching.
			 * It is expected that in every page there will be a Widget named like so: 'Dashboard 1 - Page 2 widget'.
			 */
			if (count($dashboard['pages']) === 1) {
				// Case when there is only one Page. The Page button is not even visible.
				$this->checkDashboardOpen($dashboard);
			}
			else {
				// When a Dashboard contains several Pages.

				// Check that Slideshow button exists.
				$this->assertTrue($this->query('class:btn-dashboard-toggle-slideshow')->exists());

				// Parent to all Page tabs.
				$page_tabs = $this->query('class:dashboard-navigation-tabs')->one();

				foreach ($dashboard['pages'] as $page) {
					$page_tab = $page_tabs->query('xpath:.//span[text()='.CXPathHelper::escapeQuotes($page['name']).']')->one();
					$this->assertEquals($page['name'], $page_tab->getAttribute('title'));

					// Only switch the Page if it is not the first one.
					if ($page['name'] !== $page_tabs->query('xpath:.//div[@class="selected-tab"]')->one()->getText()) {
						$page_tab->click();
						$this->page->waitUntilReady();
					}

					// Assert that the Dashboard has opened.
					$this->checkDashboardOpen($dashboard, $page);
				}
			}
		}

		// Assert the Dashboard dropdown.
		$list_button = $navigation->query('xpath:.//button[@title="Dashboard list"]')->one();
		$list_button->click();
		$popup_menu = $list_button->asPopupButton()->getMenu();
		$this->assertEquals(array_column($api_dashboards, 'name'), $popup_menu->getItems()->asText());
		$popup_menu->close();
	}

	public function getCheckNavigationButtonsData() {
		return [
			[
				[
					'host_name' => 'Many Dashboards',
					'previous_button_selector' => 'class:btn-host-dashboard-previous-dashboard',
					'next_button_selector' => 'class:btn-host-dashboard-next-dashboard'
				]
			],
			[
				[
					'host_name' => 'Many Pages',
					'previous_button_selector' => 'class:btn-dashboard-previous-page',
					'next_button_selector' => 'class:btn-dashboard-next-page'
				]
			]
		];
	}

	/**
	 * Check Dashboard and Page navigation using the buttons.
	 *
	 * @dataProvider getCheckNavigationButtonsData
	 */
	public function testDashboardsHostDashboardPage_CheckNavigationButtons($data) {
		$this->openDashboardsForHost($data['host_name']);

		$previous = $this->query($data['previous_button_selector'])->one();
		$next = $this->query($data['next_button_selector'])->one();

		// If these are set then use them instead of the counter for determining the correct widget name.
		$dashboard_count = ($data['host_name'] === 'Many Dashboards') ? null : 1;
		$page_count = ($data['host_name'] === 'Many Pages') ? null : 1;

		// Cycle tabs in forward direction (by using the > button).
		for ($i = 1; $i <= self::COUNT_MANY; $i++) {
			$this->checkDashboardOpen(
					['name' => 'Dashboard '.($dashboard_count === null ? $i : $dashboard_count)],
					['name' => 'Page '.($page_count === null ? $i : $page_count)]
			);

			//Assert if enabled/disabled correctly.
			$this->assertEquals($i > 1, $previous->isEnabled());
			$this->assertEquals($i < self::COUNT_MANY, $next->isEnabled());

			// Only switch the Dashboard if it is not the last one.
			if ($i !== self::COUNT_MANY) {
				$next->click();
				$this->page->waitUntilReady();
			}
		}

		// Cycle tabs in backward direction (by using the < button).
		for ($i = self::COUNT_MANY; $i >= 1; $i--) {
			$this->checkDashboardOpen(
				['name' => 'Dashboard '.($dashboard_count === null ? $i : $dashboard_count)],
				['name' => 'Page '.($page_count === null ? $i : $page_count)]
			);

			//Assert if enabled/disabled correctly.
			$this->assertEquals($i > 1, $previous->isEnabled());
			$this->assertEquals($i < self::COUNT_MANY, $next->isEnabled());

			// Only switch the Dashboard if it is not the first one.
			if ($i !== 1) {
				$previous->click();
				$this->page->waitUntilReady();
			}
		}
	}

	/**
	 * Check Dashboard navigation using the dropdown.
	 */
	public function testDashboardsHostDashboardPage_CheckNavigationDropdown() {
		// Create a Host with some Dashboards.
		$data = [
			'host_name' => 'Dashboards for dropdown',
			'dashboards' => [
				['name' => 'Dashboard 1'],
				['name' => '🙂🙃'],
				['name' => '<script>alert("hi!");</script>'],
				['name' => 'test тест 测试 テスト ทดสอบ'],
				['name' => '&nbsp; &amp;☺♥²©™"\'']
			]
		];
		$api_dashboards = $this->createHostWithDashboards($data);

		// Open the newly created Host dashboard.
		$this->openDashboardsForHost('Dashboards for dropdown');

		// Click each Dashboard in the menu and assert that it opened.
		foreach ($api_dashboards as $dashboard) {
			$navigation = $this->query('class:host-dashboard-navigation')->one();

			// Only switch if not already on the correct Dashboard.
			if ($dashboard['name'] !== $navigation->query('xpath:.//div[@class="selected-tab"]')->one()->getText()) {
				$list_button = $this->query('xpath:.//button[@title="Dashboard list"]')->one();
				$list_button->click();
				$list_button->asPopupButton()->getMenu()->select($dashboard['name']);
			}

			$this->checkDashboardOpen($dashboard);
		}
	}

	/**
	 * Opens the 'Host dashboards' page for a specific host.
	 *
	 * @param $host_name    name of the Host to open Dashboards for
	 */
	protected function openDashboardsForHost($host_name) {
		// Instead of searching the Host in the UI it is faster to just get the ID from the database.
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host_name));
		$this->page->login()->open('zabbix.php?action=host.dashboard.view&hostid='.$id)->waitUntilReady();
	}

	/**
	 * Creates a Template with required Dashboards using API and assigns it to a new Host.
	 *
	 * @param $data      data from data provider
	 *
	 * @returns array    dashboard data, that was actually sent to the API (with the defaults set)
	 */
	protected function createHostWithDashboards($data) {
		$response = CDataHelper::createTemplates([
			[
				'host' => 'Template for '.$data['host_name'],
				'groups' => [
					['groupid' => '1'] // template group 'Templates'
				]
			]
		]);
		$template_id = $response['templateids']['Template for '.$data['host_name']];

		CDataHelper::createHosts([
			[
				'host' => $data['host_name'],
				'groups' => [
					['groupid' => '6'] // host group 'Virtual machines'
				],
				'templates' => [
					'templateid' => $template_id
				]
			]
		]);

		// Add all resulting dashboard data and then return.
		$api_dashboards = [];

		foreach ($data['dashboards'] as $dashboard) {
			// Set Template ID.
			$dashboard['templateid'] = $template_id;

			// Add the default Dashboard Page if none set.
			if (!array_key_exists('pages', $dashboard)) {
				$dashboard['pages'] = [
					[
						'name' => 'Page 1',
						'widgets' => [
							[
								'type' => 'clock',
								'name' => $this->widgetName($dashboard['name'], 'Page 1'),
								'width' => 6,
								'height' => 4
							]
						]
					]
				];
			}

			// Add default widgets if missing, the name is important.
			foreach ($dashboard['pages'] as $i => $page) {
				if (!array_key_exists('widgets', $dashboard['pages'][$i])) {
					$dashboard['pages'][$i]['widgets'] = [
						[
							'type' => 'clock',
							'name' => $this->widgetName($dashboard['name'], $page['name']),
							'width' => 6,
							'height' => 4
						]
					];
				}
			}

			// Create the Dashboard with API.
			CDataHelper::call('templatedashboard.create', [
				$dashboard
			]);

			$api_dashboards[] = $dashboard;
		}

		// The dashboard tabs are sorted alphabetically.
		CTestArrayHelper::usort($api_dashboards, ['name']);
		return $api_dashboards;
	}

	/**
	 * Create a widget name from the dashboard and page name.
	 * The name is used for making sure the correct Dashboard is displayed.
	 *
	 * @param $dashboard_name    name of the dashboard this widget is on
	 * @param $page_name         name of the page this widget is on
	 *
	 * @return string            calculated widget name
	 */
	protected function widgetName($dashboard_name, $page_name) {
		// Widget name max length 255.
		return substr($dashboard_name.' - '.$page_name.' widget', 0, 255);
	}

	/**
	 * Check that the correct Dashboard and Pages is displayed.
	 * This is done by testing for the unique Widget name.
	 *
	 * @param $dashboard    Dashboard data array
	 * @param $page         Page data array
	 */
	protected function checkDashboardOpen ($dashboard, $page = null) {
		if ($page === null) {
			$page = $dashboard['pages'][0];
		}

		// Check that the correct Dashboard tab is selected.
		$navigation = $this->query('class:host-dashboard-navigation')->one();
		$this->assertEquals($dashboard['name'], $navigation->query('xpath:.//div[@class="selected-tab"]')->one()->getText());

		// Check that the correct Page tab is selected.
		if (count(CTestArrayHelper::get($dashboard, 'pages', [])) > 1) {
			$page_tabs = $this->query('class:dashboard-navigation-tabs')->one();
			$this->assertEquals($page['name'], $page_tabs->query('xpath:.//div[@class="selected-tab"]')->one()->getText());
		}

		// Assert correct Dashboard is displayed by asserting the Widget name.
		CDashboardElement::find()->one()->getWidget($this->widgetName($dashboard['name'], $page['name']));
	}
}
