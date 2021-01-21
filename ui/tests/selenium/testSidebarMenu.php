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
					'third_level' =>
					[
						'Trigger overview',
						'Data overview'
					]
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
					'page' => 'Screens',
					'third_level' =>
					[
						'Screens',
						'Slide shows'
					]
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
					'third_level' =>
					[
						'Trigger actions',
						'Discovery actions',
						'Autoregistration actions',
						'Internal actions'
					]
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
					'third_level' =>
					[
						'GUI',
						'Autoregistration',
						'Housekeeping',
						'Images',
						'Icon mapping',
						'Regular expressions',
						'Macros',
						'Value mapping',
						'Trigger displaying options',
						'Modules',
						'Other'
					]
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
					'page' => 'User roles'
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
	 * Check menu pages that allows to navigate in zabbix.
	 *
	 * @dataProvider getMainData
	 */
	public function testSidebarMenu_Main($data) {
		$this->page->login()->open('')->waitUntilReady();
		$xpath = '//nav/ul/li[contains(@class, "has-submenu")]';

		if ($data['section'] !== 'Monitoring') {
			$menu = ($data['section'] === 'User settings') ? 'user' : 'main';
			$this->query('xpath://ul[@class="menu-'.$menu.'"]/li/a[text()="'.$data['section'].'"]')->waitUntilReady()->one()->click();
			sleep (1);
		}

		$this->query('xpath:'.$xpath.'/a[text()="'.$data['section'].'"]/following::ul/li/a[text()="'.
				$data['page'].'"]')->waitUntilVisible()->one()->click();
		if (array_key_exists('third_level', $data)) {
			foreach ($data['third_level'] as $submenu) {
				$this->query('xpath://li[contains(@class, "has-submenu")]/a[text()="'.$data['page'].
						'"]/following::ul/li/a[text()="'.$submenu.'"]')->waitUntilPresent()->one()->click();
				$this->assertTrue($this->query('xpath://li[contains(@class, "is-selected")]/a[text()="'.
						$data['page'].'"]')->exists());
				$header = ($submenu === 'Other') ? 'Other configuration parameters' : $submenu;
				$this->assertPageHeader($header);
				$this->query('xpath:'.$xpath.'/ul/li/a[text()="'.$data['page'].'"]')->one()->click();
			}
		}
		else {
			$header = (array_key_exists('header', $data)) ? $data['header'] : $data['page'];
			$this->assertPageHeader($header);
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

			$this->query('button:'.$hide)->one()->click();
			$this->assertTrue($this->query('xpath://aside[@class="sidebar is-'.$view.'"]')->waitUntilReady()->exists());
			sleep(1);
			$this->query('id:'.$id)->one()->click();
			$this->assertTrue($this->query('xpath://aside[@class="sidebar is-'.$view.' is-opened"]')->waitUntilReady()->exists());
			sleep(1);
			$this->query('button:'.$unhide)->one()->click();
			$this->assertTrue($this->query('class:sidebar')->exists());
		}
	}

	public static function getLinksData() {
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
					'link' => 'https://www.zabbix.com/documentation/5.4/'
				]
			],
			[
				[
					'section' => 'Sign out'
				]
			]
		];
	}

	/**
	 * Pages that transfer you to another webpage and logout button.
	 *
	 * @dataProvider getLinksData
	 */
	public function testSidebarMenu_Links($data) {
		$this->page->login()->open('')->waitUntilReady();
		if (array_key_exists('link', $data)) {
			$this->assertTrue($this->query('xpath://ul[@class="menu-user"]//a[text()="'.$data['section'].
					'" and @href="'.$data['link'].'"]')->exists());
		}
		else {
			$this->query('xpath://ul[@class="menu-user"]//a[text()="'.$data['section'].'"]')->one()->click();
			$this->assertPageTitle('Zabbix');
		}
	}
}