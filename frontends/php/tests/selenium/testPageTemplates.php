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

class testPageTemplates extends CWebTest
{
	// Returns all templates
	public static function allTemplates()
	{
		return DBdata("select * from hosts where status in (".HOST_STATUS_TEMPLATE.')');
	}

	/**
	* @dataProvider allTemplates
	*/
	public function testPageTemplates_SimpleTest($template)
	{
		$this->login('templates.php');
		$this->dropdown_select('groupid','Templates');
//		$this->wait();
		$this->assertTitle('Templates');
		$this->ok('CONFIGURATION OF TEMPLATES');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Templates','Applications','Items','Triggers','Graphs','Screens','Discovery','Linked templates','Linked to'));
		// Data
		$this->ok(array($template['host']));
		$this->dropdown_select('go','Export selected');
		$this->dropdown_select('go','Delete selected');
		$this->dropdown_select('go','Delete selected with linked elements');
	}

	/**
	* @dataProvider allTemplates
	*/
	public function testPageTemplates_SimpleUpdate($template)
	{
		$name=$template['host'];

		$sql1="select * from hosts where host='$name'";
		$oldHashTemplate=DBhash($sql1);
		$sql2="select * from hosts order by hostid";
		$oldHashHosts=DBhash($sql2);
		$sql3="select * from items order by itemid";
		$oldHashItems=DBhash($sql3);
		$sql4="select * from triggers order by triggerid";
		$oldHashTriggers=DBhash($sql4);

		$this->login('templates.php');
		$this->dropdown_select('groupid','all');

		$this->assertTitle('Templates');

		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Templates');
		$this->ok('Template updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF TEMPLATES');

		$this->assertEquals($oldHashTemplate,DBhash($sql1));
		$this->assertEquals($oldHashHosts,DBhash($sql2));
		$this->assertEquals($oldHashItems,DBhash($sql3));
		$this->assertEquals($oldHashTriggers,DBhash($sql4));
	}

	public function testPageTemplates_Create()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_Import()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassExport()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassDelete()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassDeleteWithLinkedElements()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
