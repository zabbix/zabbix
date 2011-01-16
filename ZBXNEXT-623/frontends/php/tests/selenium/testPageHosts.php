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

class testPageHosts extends CWebTest
{
	// Returns all hosts
	public static function allHosts()
	{
		return DBdata('select * from hosts where status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')');
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_SimpleTest($host)
	{
		$this->login('hosts.php');
		$this->dropdown_select('groupid','Zabbix servers');
//		$this->wait();
		$this->assertTitle('Hosts');
		$this->ok('CONFIGURATION OF HOSTS');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Name','Applications','Items','Triggers','Graphs','Discovery','Interface','Templates','Status','Availability'));
		// Data
		$this->ok(array($host['host']));
		$this->dropdown_select('go','Export selected');
		$this->dropdown_select('go','Mass update');
		$this->dropdown_select('go','Activate selected');
		$this->dropdown_select('go','Disable selected');
		$this->dropdown_select('go','Delete selected');
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_FilterHost($host)
	{
		$this->login('hosts.php');
		$this->click('flicker_icon_l');
		$this->input_type('filter_host',$host['host']);
		$this->input_type('filter_ip','');
		$this->input_type('filter_port','');
		$this->click('filter');
		$this->wait();
		$this->ok($host['host']);
	}

	// Filter returns nothing
	public function testPageHosts_FilterNone()
	{
		$this->login('hosts.php');

		// Reset filter
		$this->click('css=span.link_menu');

		$this->input_type('filter_host','1928379128ksdhksdjfh');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterNone1()
	{
		$this->login('hosts.php');

		// Reset filter
		$this->click('css=span.link_menu');

		$this->input_type('filter_host','_');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterNone2()
	{
		$this->login('hosts.php');

		// Reset filter
		$this->click('css=span.link_menu');

		$this->input_type('filter_host','%');
		$this->click('filter');
		$this->wait();
		$this->ok('Displaying 0 of 0 found');
	}

	// Filter reset

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_FilterReset($host)
	{
		$this->login('hosts.php');
		$this->click('css=span.link_menu');
		$this->click('filter');
		$this->wait();
		$this->ok($host['host']);
	}

	public function testPageHosts_MassExport()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassUpdate()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassActivate()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassDisable()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageHosts_MassDelete()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
