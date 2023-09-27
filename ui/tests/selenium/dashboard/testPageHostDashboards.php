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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';

/**
 * @onBefore prepareHostDashboardsData
 *
 * @backup hosts
 */
class testPageHostDashboards extends CWebTest {

	protected const TEMPLATE_NAME = 'Template for Host dashboards';
	protected const HOST_NAME = 'Host for Host dashboards';

	public function prepareHostDashboardsData() {
		$response = CDataHelper::createTemplates([
			[
				'host' => self::TEMPLATE_NAME,
				'groups' => [
					['groupid' => '1']
				]
			]
		]);
		$template_id = $response['templateids'][self::TEMPLATE_NAME];

		CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME,
				'groups' => [
					['groupid' => '6']
				],
				'templates' => [
					'templateid' => $template_id
				]
			]
		]);

		CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => $template_id,
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
										'type' => 0,
										'name' => '*',
										'value' => 0
									]
								]
							]
						]
					]
				]
			]
		]);
	}

	/**
	 * Check layout.
	 */
	public function testPageHostDashboards_Layout() {
		$this->openDashboardsForHost(self::HOST_NAME);

		$this->page->assertTitle('Dashboards');
		$this->page->assertHeader('Host dashboards');

		$breadcrumbs = $this->query('class:breadcrumbs')->one();
		$this->assertEquals('zabbix.php?action=host.view', $breadcrumbs->query('link:All hosts')->one()->getAttribute('href'));
		$this->assertEquals(self::HOST_NAME, $breadcrumbs->query('xpath:./li[2]/span')->one()->getText());

		$dashboard_nav = $this->query('class:host-dashboard-navigation')->one();

		$prev_button = $dashboard_nav->query('xpath:.//button[@title="Previous dashboard"]')->one();
		$this->assertTrue($prev_button->isDisplayed());
		$this->assertFalse($prev_button->isEnabled());

		$dasboard_tab = $dashboard_nav->query('xpath:.//span[text()="Dashboard 1"]')->one();
		$this->assertEquals('Dashboard 1', $dasboard_tab->getAttribute('title'));

		// Assert the listed dashboard dropdown.
		$list_button = $dashboard_nav->query('xpath:.//button[@title="Dashboard list"]')->one();
		$this->assertTrue($list_button->isClickable());
		$list_button->click();
		$popup_menu = $list_button->asPopupButton()->getMenu();
		$this->assertEquals(['Dashboard 1'], $popup_menu->getItems()->asText());
		$popup_menu->close();

		$next_button = $dashboard_nav->query('xpath:.//button[@title="Next dashboard"]')->one();
		$this->assertTrue($next_button->isDisplayed());
		$this->assertFalse($next_button->isEnabled());
	}

	/**
	 * Open and close the Kiosk mode.
	 */
	public function testPageHostDashboards_CheckKioskMode() {
		$this->openDashboardsForHost(self::HOST_NAME);

		// Test Kiosk mode.
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();

		// Check that Header and Filter disappeared.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilNotVisible();
		$this->assertFalse($this->query('xpath://div[@aria-label="Filter"]')->exists());
		$this->assertFalse($this->query('class:host-dashboard-navigation')->exists());
		$this->assertTrue($this->query('class:dashboard')->exists());

		$this->query('xpath://button[@title="Normal view"]')->waitUntilPresent()->one()->click(true);
		$this->page->waitUntilReady();

		// Check that Header and Filter are visible again.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilVisible();
		$this->assertTrue($this->query('xpath://div[@aria-label="Filter"]')->exists());
		$this->assertTrue($this->query('class:host-dashboard-navigation')->exists());
		$this->assertTrue($this->query('class:dashboard')->exists());
	}

	public function getCheckFiltersData() {
		return [
			[
				[
					'fields' => ['id:from' => 'now-2h', 'id:to' => 'now-1h'],
					'expected' => 'now-2h – now-1h',
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
					'expected' => 'now-2y – now-1y',
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
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => false,
						'js-btn-time-right' => false
					]
				]
			]
		];
	}

	/**
	 * Change values in the filter section and check the resulting changes.
	 *
	 * @dataProvider getCheckFiltersData
	 */
	public function testPageHostDashboards_CheckFilters($data) {
		$this->openDashboardsForHost(self::HOST_NAME);
		$form = $this->query('class:filter-container')->asForm(['normalized' => true])->one();

		// Set custom time filter.
		if (CTestArrayHelper::get($data, 'fields')) {
			$form->fill($data['fields']);
			$form->query('id:apply')->one()->click();
		}
		else {
			$form->query('link', $data['link'])->waitUntilClickable()->one()->click();
		}

		$this->page->waitUntilReady();

		// Check Zoom buttons.
		foreach ($data['zoom_buttons'] as $button => $state) {
			$this->assertTrue($this->query('xpath://button[contains(@class, '.CXPathHelper::escapeQuotes($button).
				')]')->one()->isEnabled($state)
			);
		}

		// Check tab title.
		$this->assertTrue($this->query('link',
				CTestArrayHelper::get($data, 'expected', CTestArrayHelper::get($data, 'link')))->exists()
		);
	}

	public function getCheckNavigationData() {
		return [
			[
				[

				]
			],

		];
	}

	/**
	 * Check dashboard tab navigation.
	 *
	 * @dataProvider getCheckNavigationData
	 */
	public function testPageHostDashboards_CheckNavigation($data) {
		// Create the required entities in database.
		$this->createHostWithDashboards($data);

		$this->openDashboardsForHost(self::HOST_NAME);

		// Parent to all dashboard navigation elements.
		$nav = $this->query('class:host-dashboard-navigation')->one();

		// Assert buttons.
		$prev_button = $nav->query('xpath:.//button[@title="Previous dashboard"]')->one();
		$this->assertFalse($prev_button->isEnabled());
		$next_button = $nav->query('xpath:.//button[@title="Next dashboard"]')->one();
		$this->assertFalse($next_button->isEnabled());

		// Assert dashboard tabs.
		$dasboard_tab = $nav->query('xpath:.//span[text()="Dashboard 1"]')->one();
		$this->assertEquals('Dashboard 1', $dasboard_tab->getAttribute('title'));

		// Assert the listed dashboard dropdown.
		$list_button = $nav->query('xpath:.//button[@title="Dashboard list"]')->one();
		$list_button->click();
		$popup_menu = $list_button->asPopupButton()->getMenu();
		$this->assertEquals(['Dashboard 1'], $popup_menu->getItems()->asText());
		$popup_menu->close();
	}

	/**
	 * Opens the Host dashboards page for a specific host.
	 *
	 * @param $host_name    name of the Host to open Dashboards for
	 */
	protected function openDashboardsForHost($host_name) {
		// Instead of searching the Host in the UI it is faster to just get the ID from the database.
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host_name));
		$this->page->login()->open('zabbix.php?action=host.dashboard.view&hostid='.$id);
		$this->page->waitUntilReady();
	}

	/**
	 * Creates a template with required dashboards using API and assigns it to a new host.
	 *
	 * @param $data    data from data provider
	 */
	protected function createHostWithDashboards($data) {
		$response = CDataHelper::createTemplates([
			[
				'host' => self::TEMPLATE_NAME,
				'groups' => [
					['groupid' => '1']
				]
			]
		]);
		$template_id = $response['templateids'][self::TEMPLATE_NAME];

		CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME,
				'groups' => [
					['groupid' => '6']
				],
				'templates' => [
					'templateid' => $template_id
				]
			]
		]);

		CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => $template_id,
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
										'type' => 0,
										'name' => '*',
										'value' => 0
									]
								]
							]
						]
					]
				]
			]
		]);
	}

}
