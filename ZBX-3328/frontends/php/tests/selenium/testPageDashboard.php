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
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testPageDashboard extends CWebTest
{
	public $host = "Text host";

	public function testPageDashboard_SimpleTest()
	{
		$this->login('dashboard.php');
		$this->assertTitle('Dashboard');
		$this->ok('PERSONAL DASHBOARD');
		$this->ok('Favourite graphs');
		$this->ok('Favourite screens');
		$this->ok('Favourite maps');
		$this->ok('Status of Zabbix');
		$this->ok('System status');
		$this->ok('Host status');
		$this->ok('Last 20 issues');
		$this->ok('Web monitoring');
		$this->ok('Updated:');
	}
}
?>
