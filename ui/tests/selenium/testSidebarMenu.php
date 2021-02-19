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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup sessions
 *
 * Test Zabbix side menu.
 */
class testSidebarMenu extends CWebTest {

	public static function getMainData() {
		return [
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
					'page' => 'Overview',
					'header' => 'Trigger overview'
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
					'page' => 'Screens'
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
					'section' => 'Monitoring',
					'page' => 'Services'
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
					'page' => 'Availability report'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Triggers top 100',
					'header' => '100 busiest triggers'
				]
			],
			[
				[
					'section' => 'Reports',
					'page' => 'Audit',
					'header' => 'Audit log'
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
					'section' => 'Configuration',
					'page' => 'Host groups'
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Templates'
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Hosts'
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Maintenance',
					'header' => 'Maintenance periods'
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Actions',
					'header' => 'Autoregistration actions'
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Event correlation'
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Discovery',
					'header' => 'Discovery rules'
				]
			],
			[
				[
					'section' => 'Configuration',
					'page' => 'Services'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'General',
					'header' => 'GUI'
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
					'page' => 'Authentication'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'User groups'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Users'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Media types'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Scripts'
				]
			],
			[
				[
					'section' => 'Administration',
					'page' => 'Queue',
					'header' => 'Queue overview'
				]
			]
		];
	}

	/**
	 * Check menu pages that allows to navigate in Zabbix.
	 *
	 * @dataProvider getMainData
	 */
	public function testSidebarMenu_Main($data) {
		$this->page->login()->open('')->waitUntilReady();
		$this->query('xpath://li[@class="is-selected"]/a[text()="Dashboard"]')->waitUntilReady();

		$menu__type = ($data['section'] === 'User settings') ? 'user' : 'main';
		// First level menu.
		$main_section = $this->query('xpath://ul[@class="menu-'.$menu__type.'"]')->query('link', $data['section']);

		// Click on the first level menu and wait for it to fully open.
		if ($data['section'] !== 'Monitoring') {
			$main_section->waitUntilReady()->one()->click();
			$element = $this->query('xpath://a[text()="'.$data['section'].'"]/../ul[@class="submenu"]')->one();
			CElementQuery::wait()->until(function () use ($element) {
				return CElementQuery::getDriver()->executeScript('return arguments[0].clientHeight ==='.
						' parseInt(arguments[0].style.maxHeight, 10)', [$element]);
			});
		}

		// Second level menu.
		$submenu = $main_section->one()->parents('tag:li')->query('link', $data['page'])->one();
		$submenu->waitUntilClickable()->click();
		$this->page->assertHeader((array_key_exists('header', $data)) ? $data['header'] : $data['page']);
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
			$this->query('button', $hide)->one()->click();
			$this->assertTrue($this->query('xpath://aside[@class="sidebar is-'.$view.'"]')->waitUntilReady()->exists());

			// Waiting sidemenu to hide/collapse.
			if ($view === 'compact') {
				$this->query('class:zabbix-sidebar-logo')->one(false)->waitUntilNotVisible();
			}
			elseif ($view === 'hidden') {
				$this->query('xpath://aside[@class="sidebar is-hidden"]')->one(false)->waitUntilNotVisible();
			}

			$this->query('id', $id)->one()->click();
			$this->assertTrue($this->query('xpath://aside[@class="sidebar is-'.$view.' is-opened"]')->waitUntilReady()->exists());

			// Checking that collapsed, hidden sidemenu appears on clicking.
			$this->query('xpath://aside[@class="sidebar is-'.$view.' is-opened"]')->one()->waitUntilVisible();

			// Returning standart sidemenu view clicking on unhide, expand button.
			$this->query('button', $unhide)->one()->waitUntilClickable()->click();
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
					'section' => 'Share',
					'link' => 'https://share.zabbix.com/'
				]
			],
			[
				[
					'section' => 'Help',
					'link' => 'https://www.zabbix.com/documentation/5.0/'
				]
			],
			[
				[
					'section' => 'User settings',
					'title' => 'User profile'
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
	 * Side menu user section.
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
			$this->page->assertTitle($data['title']);
		}
	}
}
