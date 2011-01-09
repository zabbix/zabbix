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
	// Returns all scripts
	public static function allScripts()
	{
		DBconnect($error);

		$scripts=array();

		$result=DBselect('select * from scripts');
		while($script=DBfetch($result))
		{
			$scripts[]=array($script);
		}
		return $scripts;
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageScripts_SimpleTest($script)
	{
		$this->login('scripts.php');
		$this->assertTitle('Scripts');

		$this->ok('Scripts');
		$this->ok('CONFIGURATION OF SCRIPTS');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Name','Command','User group','Host group','Host access'));
		// Data
		$this->ok(array($script['name'],$script['command'],'Read'));
		$this->dropdown_select('go','Delete selected');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageScripts_SimpleUpdate($script)
	{
		$name=$script['name'];

		$sql="select * from scripts where name='$name'";
		$oldHash=$this->DBhash($sql);

		$this->login('scripts.php');
		$this->assertTitle('Scripts');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Scripts');
		$this->ok('Script updated');
		$this->ok($name);
		$this->ok('CONFIGURATION OF SCRIPTS');

		$this->assertEquals($oldHash,$this->DBhash($sql));
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageScripts_MassDelete($script)
	{
		$scriptid=$script['scriptid'];

		$this->DBsave_tables('scripts');
		$this->chooseOkOnNextConfirmation();

		$this->login('scripts.php');
		$this->assertTitle('Scripts');
		$this->checkbox_select("scripts[$scriptid]");
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->assertTitle('Scripts');
		$this->ok('Script deleted');

		$sql="select * from scripts where scriptid='$scriptid'";
		$this->assertEquals(0,$this->DBcount($sql));

		$this->DBrestore_tables('scripts');
	}
}
?>
