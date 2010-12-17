<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

class testClicks extends PHPUnit_Extensions_SeleniumTestCase
{
	protected $captureScreenshotOnFailure = TRUE;
	protected $screenshotPath = '/home/hudson/public_html/screenshots';
	protected $screenshotUrl = 'http://hudson/~hudson/screenshots';

	// List of strings that should NOT appear on any page
	public $failIfExists = array (
		"ZABBIX_ERROR",
		"pg_query",
		"Error in",
		"expects parameter",
		"Undefined index",
		"Undefined variable",
		"Undefined offset",
		"Fatal error",
		"Call to undefined method",
		"Invalid argument supplied",
		"Warning:",
		"PHP notice",
		"PHP warning",
		"Use of undefined",
		"You must login",
		"DEBUG INFO",
		"Cannot modify header"
	);

	// List of strings that SHOULD appear on every page
	public $failIfNotExists = array (
		"Help",
		"Get support",
		"Print",
		"Profile",
		"Logout",
		"Connected",
		"Admin"
	);

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
			// Administration
			array('usergrps.php',
				array('link=Guests','save'),
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

	protected function setUp()
	{
		$this->setHost('localhost');
		$this->setBrowser('*firefox');
		$this->setBrowserUrl('http://hudson/~hudson/'.PHPUNIT_URL.'/frontends/php/');
	}

	public function login()
	{
		$this->open('index.php');
		$this->waitForPageToLoad();
		// Login if not logged in already
		if($this->isElementPresent('link=Login'))
		{
			$this->click('link=Login');
			$this->waitForPageToLoad();
			$this->type('name','Admin');
			$this->type('password','zabbix');
			$this->click('enter');
			$this->waitForPageToLoad();
		}
	}

	public function logout()
	{
		$this->click('link=Logout');
		$this->waitForPageToLoad();
	}

	/**
	* @dataProvider provider
	*/
	public function atestTitles($a, $b)
	{
		$this->login();
		$this->open($a);
		$this->assertTitle($b);
		$this->logout();
	}

	/**
	* @dataProvider provider
	*/
	public function atestNotExists($a,$b)
	{
		$this->login();
		$this->open($a);
		foreach($this->failIfExists as $str)
		{
			$this->assertTextNotPresent($str,'assertTextNotPresent('.$a.','.$str.')');
		}
		$this->logout();
	}

	/**
	* @dataProvider provider
	*/
	public function atestExists($a,$b)
	{
		$this->login();
		$this->open($a);
		foreach($this->failIfNotExists as $str)
		{
			$this->assertTextPresent($str,'assertTextPresent('.$a.','.$str.')');
		}
		$this->logout();
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
}
?>
