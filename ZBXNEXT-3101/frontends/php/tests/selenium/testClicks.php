<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testClicks extends CWebTest {

	public static function provider() {
		// List of URLs to test
		// URL, navigation, expected page Title, expected String
		return [
			// Configuration
			['hostgroups.php',
				['link=Discovered hosts','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['hostgroups.php',
				['link=Zabbix servers','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['hostgroups.php',
				['link=Hypervisors','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['hostgroups.php',
				['link=Linux servers','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['hostgroups.php',
				['link=Virtual machines','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['hostgroups.php',
				['link=ZBX6648 All Triggers','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['hostgroups.php',
				['link=ZBX6648 Disabled Triggers','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['hostgroups.php',
				['link=ZBX6648 Enabled Triggers','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['hostgroups.php',
				['link=ZBX6648 Group No Hosts','update'],
				'Configuration of host groups',
				'Group updated'
			],
			['templates.php',
				['link=Template OS Linux','update'],
				'Configuration of templates',
				'Template updated'
			],
			['templates.php',
				['link=ЗАББИКС Сервер','update'],
				'Configuration of hosts',
				'Host updated'
			],
			['sysmaps.php',
				['link=Local network'],
				'Configuration of network maps',
				'Grid'
			],
			['discoveryconf.php',
				['link=Local network','update'],
				'Configuration of discovery rules',
				'Discovery rule updated'
			],
			// Administration
			['usergrps.php',
				['link=Guests', 'update'],
				'Configuration of user groups',
				'Group updated'
			],
			['usergrps.php',
				['link=Zabbix administrators', 'update'],
				'Configuration of user groups',
				'Group updated'
			],
			['users.php',
				['link=Admin', 'update'],
				'Configuration of users',
				'User updated'
			],
			['media_types.php',
				['link=Email', 'update'],
				'Configuration of media types',
				'Media type updated'
			],
			['media_types.php',
				['link=Jabber', 'update'],
				'Configuration of media types',
				'Media type updated'
			],
			['media_types.php',
				['link=SMS', 'update'],
				'Configuration of media types',
				'Media type updated'
			],
			['scripts.php',
				['link=Ping', 'update'],
				'Configuration of scripts',
				'Script updated'
			]
		];
	}

	/**
	* @dataProvider provider
	*/
	public function testClick($url, $clicks, $title, $expected) {
		$this->zbxTestLogin();
		$this->zbxTestOpen($url);
		foreach ($clicks as $click) {
			$this->zbxTestClick($click);
			$this->waitForPageToLoad();
		}

		foreach ($this->failIfNotExists as $str) {
			$this->zbxTestTextPresent($str);
		}
		$this->zbxTestCheckTitle($title);
		$this->zbxTestTextPresent($expected);
		$this->zbxTestLogout();
	}
}
