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

require_once __DIR__.'/../include/CWebTest.php';

/**
 * @backup sessions
 *
 * Test side menu.
 */
class testSidebarMenu extends CWebTest {

	public static function getMainData() {
		return [
			[
				[
					'section' => 'Dashboards',
					'page' => 'Dashboards',
					'header' => 'Global view'
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Problems'
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Hosts'
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Latest data'
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Maps'
				]
			],
			[
				[
					'section' => 'Monitoring',
					'page' => 'Discovery',
					'header' => 'Status of discovery'
				]
			],
			[
				[
					'section' => 'Services',
					'page' => 'Services'
				]
			],
			[
				[
					'section' => 'Services',
					'page' => 'SLA'
				]
			],
			[
				[
					'section' => 'Services',
					'page' => 'SLA report'
				]
			],
			[
				[
					'section' => 'Inventory',
					'page' => 'Overview',
					'header' => 'Host inventory overview'
				]
			],
			[
				[
					'section' => 'Inventory',
					'page' => 'Hosts',
					'header' => 'Host inventory'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'System information'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Scheduled reports'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Availability report'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Top 100 triggers',
					'header' => 'Top 100 triggers'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Audit log'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Action log'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Notifications'
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Template groups'
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Host groups'
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Templates'
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Hosts'
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Maintenance',
					'header' => 'Maintenance periods'
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Event correlation'
				]
			],
			[
				[
					'section' => 'Data collection',
					'page' => 'Discovery',
					'header' => 'Discovery rules'
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Actions',
					'third_level' =>
					[
						'Trigger actions',
						'Service actions',
						'Discovery actions',
						'Autoregistration actions',
						'Internal actions'
					]
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Media types'
				]
			],
			[
				[
					'section' => 'Alerts',
					'page' => 'Scripts'
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'User groups'
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'User roles'
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'Users'
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'API tokens'
				]
			],
			[
				[
					'section' => 'Users',
					'page' => 'Authentication'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'General',
					'third_level' =>
					[
						'GUI',
						'Autoregistration',
						'Images',
						'Icon mapping',
						'Regular expressions',
						'Trigger displaying options',
						'Geographical maps',
						'Modules',
						'Other'
					]
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Audit log'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Housekeeping'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Proxies'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Macros'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Queue',
					'third_level' =>
					[
						'Queue overview',
						'Queue overview by proxy',
						'Queue details'
					]
				]
			],
			[
				[
					'section' => 'User settings',
					'page' => 'Profile',
					'header' => 'User profile: Zabbix Administrator'
				]
			],
			[
				[
					'section' => 'User settings',
					'page' => 'API tokens'
				]
			]
		];
	}

	/**
	 * Check menu pages that allows to navigate.
	 *
	 * @dataProvider getMainData
	 */
	public function testSidebarMenu_Main($data) {
		$this->page->login()->open('')->waitUntilReady();
		$this->query('xpath://li[@class="is-selected"]/a[text()="Dashboards"]')->waitUntilReady();

		$menu__type = ($data['section'] === 'User settings') ? 'user' : 'main';
		// First level menu.
		$main_section = $this->query('xpath://ul[@class="menu-'.$menu__type.'"]')->query('link', $data['section']);

		// Click on the first level menu and wait for it to fully open.
		if ($data['section'] !== 'Dashboards') {
			$main_section->waitUntilReady()->one()->click();
			$element = $this->query('xpath://a[text()="'.$data['section'].'"]/../ul[@class="submenu"]')->one();
			CElementQuery::wait()->until(function () use ($element) {
				return CElementQuery::getDriver()->executeScript('return arguments[0].clientHeight ==='.
						' parseInt(arguments[0].style.maxHeight, 10)', [$element]);
			});

			$submenu = $element->query('link', $data['page'])->one();

			// Open second level menu.
			$submenu->waitUntilClickable()->click();
		}

		// Checking 3rd level menu.
		if (array_key_exists('third_level', $data)) {
			foreach ($data['third_level'] as $third_level) {
				$submenu->parents('tag:li')->query('xpath://ul/li/a[text()="'.$third_level.'"]')
						->waitUntilClickable()->one()->click();
				$this->assertTrue($this->query('xpath://li[contains(@class, "is-selected")]/a[text()="'.
						$data['page'].'"]')->exists());
				$this->page->assertHeader(($third_level === 'Other') ? 'Other configuration parameters' : $third_level);
				$submenu->click();
			}
		}
		else {
			$this->page->assertHeader((array_key_exists('header', $data)) ? $data['header'] : $data['page']);
		}
	}

	/**
	 * Check side menu hide and collapse availability.
	 */
	public function testSidebarMenu_CollapseHide() {
		$this->page->login()->open('')->waitUntilReady();
		foreach (['compact', 'hidden'] as $view) {
			if ($view === 'compact') {
				$hide = 'Collapse sidebar';
				$unhide = 'Expand sidebar';
				$id = 'view';
			}
			else {
				$hide = 'Hide sidebar';
				$unhide = 'Show sidebar';
				$id = 'sidebar-button-toggle';
			}

			// Clicking hide/collapse button.
			$this->query('xpath://button[@title='.CXPathHelper::escapeQuotes($hide).']')->one()->click();
			$this->assertTrue($this->query('xpath://aside[@class="sidebar is-'.$view.'"]')->waitUntilReady()->exists());

			// Waiting sidemenu to hide/collapse.
			if ($view === 'compact') {
				$this->query('class:zabbix-logo-sidebar')->one(false)->waitUntilNotVisible();
			}
			elseif ($view === 'hidden') {
				$this->query('xpath://aside[@class="sidebar is-hidden"]')->one(false)->waitUntilNotVisible();
			}

			$this->query('id', $id)->one()->click();
			$this->assertTrue($this->query('xpath://aside[@class="sidebar is-'.$view.' is-opened"]')->waitUntilReady()->exists());

			// Checking that collapsed, hidden sidemenu appears on clicking.
			$this->query('xpath://aside[@class="sidebar is-'.$view.' is-opened"]')->one()->waitUntilVisible();

			// Returning standard sidemenu view clicking on unhide, expand button.
			$this->query('xpath://button[@title='.CXPathHelper::escapeQuotes($unhide).']')->one()->waitUntilClickable()->click();
			$this->assertTrue($this->query('class:sidebar')->one()->isVisible());
		}
	}

	public static function getUserSectionData() {
		return [
			[
				[
					'section' => 'Support',
					'link' => 'https://www.zabbix.com/support'
				]
			],
			[
				[
					'section' => 'Integrations',
					'link' => 'https://www.zabbix.com/integrations'
				]
			],
			[
				[
					'section' => 'Help',
					'link' => 'https://www.zabbix.com/documentation/'.ZABBIX_EXPORT_VERSION.'/'
				]
			],
			[
				[
					'section' => 'Sign out',
					'title' => 'Zabbix'
				]
			]
		];
	}

	/**
	 * Pages that transfer you to another webpage and logout button.
	 *
	 * @dataProvider getUserSectionData
	 */
	public function testSidebarMenu_UserSection($data) {
		$this->page->login()->open('')->waitUntilReady();

		if (array_key_exists('link', $data)) {
			$this->assertTrue($this->query('xpath://ul[@class="menu-user"]//a[text()="'.$data['section'].
					'" and @href="'.$data['link'].'"]')->one()->isVisible());
		}
		else {
			$this->query('xpath://ul[@class="menu-user"]//a[text()="'.$data['section'].'"]')->one()->click();
			$this->page->assertTitle('Zabbix');
		}
	}
}
