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
require_once(dirname(__FILE__).'/class.ctest.php');

class testPageHosts extends CTest
{
	public function testPageHosts_SimpleTest()
	{
		$this->login('hosts.php');
		$this->dropdown_select('groupid','Zabbix servers');
//		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('Zabbix server');
		$this->ok('CONFIGURATION OF HOSTS');
		$this->ok('Displaying');
		$this->ok('Name');
		$this->ok('Applications');
		$this->ok('Items');
		$this->ok('Triggers');
		$this->ok('Graphs');
		$this->ok('Discovery');
		$this->ok('Interface');
		$this->ok('Templates');
		$this->ok('Status');
		$this->ok('Availability');
		$this->dropdown_select('go','Export selected');
		$this->dropdown_select('go','Mass update');
		$this->dropdown_select('go','Activate selected');
		$this->dropdown_select('go','Disable selected');
		$this->dropdown_select('go','Delete selected');
	}

	public function testPageHosts_FilterZabbixServer()
	{
		$this->login('hosts.php');
		$this->click('filter_icon');
		$this->input_type('filter_host','Zabbix ser');
		$this->input_type('filter_ip','127.0.0.1');
		$this->input_type('filter_port','10050');
		$this->click('filter');
		$this->wait();
		$this->ok('Zabbix server');
	}

	// Filter returns nothing
	public function testPageHosts_FilterNone()
	{
		$this->login('hosts.php');

		$this->input_type('filter_host','1928379128ksdhksdjfh');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	// Filter reset
	public function testPageHosts_FilterReset()
	{
		$this->login('hosts.php');
		$this->click('css=span.link_menu');
		$this->ok('Zabbix server');
	}
}
?>
