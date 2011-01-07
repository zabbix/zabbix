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

class testPageScripts extends CTest
{
	public function testPageScripts_SimpleTest()
	{
		$this->login('scripts.php');
		$this->assertTitle('Scripts');

		$this->ok('Scripts');
		$this->ok('CONFIGURATION OF SCRIPTS');
		$this->ok('Displaying');
		$this->ok('Name');
		$this->ok('Command');
		$this->ok('User group');
		$this->ok('Host group');
		$this->ok('Host access');
		$this->ok('Ping');
		$this->ok('Traceroute');
		$this->ok('All');
		$this->ok('Read');
		$this->dropdown_select('go','Delete selected');
	}

	public function testPageScripts_SimpleUpdate()
	{
		$sql="select * from scripts where name='Traceroute'";
		$oldHash=$this->DBhash($sql);

		$this->login('scripts.php');
		$this->assertTitle('Scripts');
		$this->click('link=Traceroute');
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Scripts');
		$this->ok('Script updated');
		$this->ok('Traceroute');
		$this->ok('CONFIGURATION OF SCRIPTS');

		$this->assertEquals($oldHash,$this->DBhash($sql));
	}

	public function testPageScripts_MassDelete()
	{
		$this->chooseOkOnNextConfirmation();

		$this->login('scripts.php');
		$this->assertTitle('Scripts');
		$this->checkbox_select('scripts[2]');
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->assertTitle('Scripts');
		$this->ok('Script deleted');

		$sql="select * from scripts where name='Traceroute'";
		$this->assertEquals(0,$this->DBcount($sql));
	}
}
?>
