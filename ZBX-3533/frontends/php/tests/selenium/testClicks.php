<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testClicks extends CWebTest
{
	public static function provider()
	{
		// List of URLs to test
		// URL, navigation, expected page Title, expected String
		return array(
			// Configuration
			array('hostgroups.php',
				array('link=Discovered hosts','save'),
				'Host groups',
				'Group updated'),
			array('hostgroups.php',
				array('link=Zabbix servers','save'),
				'Host groups',
				'Group updated'),
			array('hostgroups.php',
				array('link=Zabbix server','save'),
				'Hosts',
				'Host updated'),
			array('hostgroups.php',
				array('link=Template_Linux','save'),
				'Templates',
				'Template updated'),
			array('templates.php',
				array('link=Template_Linux','save'),
				'Templates',
				'Template updated'),
			array('templates.php',
				array('link=Zabbix server','save'),
				'Hosts',
				'Host updated'),
			array('sysmaps.php',
				array('link=Local network'),
				'Configuration of network maps',
				'Grid'),
			array('discoveryconf.php',
				array('link=Local network','save'),
				'Configuration of discovery',
				'Discovery rule updated'),
			// Administration
			array('usergrps.php',
				array('link=Guests','save'),
				'User groups',
				'Group updated'),
			array('usergrps.php',
				array('link=Zabbix administrators','save'),
				'User groups',
				'Group updated'),
			array('users.php',
				array('link=Admin','save'),
				'Users',
				'User updated'),
			array('media_types.php',
				array('link=Email','save'),
				'Media types',
				'Media type updated'),
			array('media_types.php',
				array('link=Jabber','save'),
				'Media types',
				'Media type updated'),
			array('media_types.php',
				array('link=SMS','save'),
				'Media types',
				'Media type updated'),
			array('scripts.php',
				array('link=Ping','save'),
				'Scripts',
				'Script updated')
			);
	}

	/**
	* @dataProvider provider
	*/
	public function testClick($url,$clicks,$title,$expected)
	{
		$this->login();
		$this->open($url);
		foreach($clicks as $click)
		{
			$this->click($click);
			$this->waitForPageToLoad();
		}

		foreach($this->failIfNotExists as $str)
		{
			$this->assertTextPresent($str);
		}
		$this->assertTitle($title);
		$this->assertTextPresent($expected);
		$this->logout();
	}
}
?>
