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
		return array(
			// Configuration
			array('hostgroups.php',
				array('link=Discovered hosts','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('hostgroups.php',
				array('link=Zabbix servers','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('hostgroups.php',
				array('link=Hypervisors','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('hostgroups.php',
				array('link=Linux servers','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('hostgroups.php',
				array('link=Virtual machines','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('hostgroups.php',
				array('link=ZBX6648 All Triggers','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('hostgroups.php',
				array('link=ZBX6648 Disabled Triggers','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('hostgroups.php',
				array('link=ZBX6648 Enabled Triggers','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('hostgroups.php',
				array('link=ZBX6648 Group No Hosts','save'),
				'Configuration of host groups',
				'Group updated'
			),
			array('templates.php',
				array('link=Template OS Linux','save'),
				'Configuration of templates',
				'Template updated'
			),
			array('templates.php',
				array('link=ЗАББИКС Сервер','save'),
				'Configuration of hosts',
				'Host updated'
			),
			array('sysmaps.php',
				array('link=Local network'),
				'Configuration of network maps',
				'Grid'
			),
			array('discoveryconf.php',
				array('link=Local network','save'),
				'Configuration of discovery',
				'Discovery rule updated'
			),
			// Administration
			array('usergrps.php',
				array('link=Guests', 'save'),
				'Configuration of user groups',
				'Group updated'
			),
			array('usergrps.php',
				array('link=Zabbix administrators', 'save'),
				'Configuration of user groups',
				'Group updated'
			),
			array('users.php',
				array('link=Admin', 'save'),
				'Configuration of users',
				'User updated'
			),
			array('media_types.php',
				array('link=Email', 'save'),
				'Configuration of media types',
				'Media type updated'
			),
			array('media_types.php',
				array('link=Jabber', 'save'),
				'Configuration of media types',
				'Media type updated'
			),
			array('media_types.php',
				array('link=SMS', 'save'),
				'Configuration of media types',
				'Media type updated'
			),
			array('scripts.php',
				array('link=Ping', 'save'),
				'Configuration of scripts',
				'Script updated'
			)
		);
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
