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

class testPageMediaTypes extends CWebTest
{
	// Returns all media types
	public static function allMediaTypes()
	{
		return DBdata('select * from media_type');
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testPageMediaTypes_SimpleTest($mediatype)
	{
		$this->login('media_types.php');
		$this->assertTitle('Media types');

		$this->ok('Media types');
		$this->ok('CONFIGURATION OF MEDIA TYPES');
		$this->ok('Displaying');
		$this->ok(array('Description','Type','Details'));
		$this->ok($mediatype['description']);
		if($mediatype['type'] == MEDIA_TYPE_EMAIL)	$this->ok('Email');
		if($mediatype['type'] == MEDIA_TYPE_JABBER)	$this->ok('Jabber');
		if($mediatype['type'] == MEDIA_TYPE_SMS)	$this->ok('SMS');
		if($mediatype['type'] == MEDIA_TYPE_EZ_TEXTING)	$this->ok('Ez Texting');
		$this->dropdown_select('go','Delete selected');
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testPageMediaTypes_SimpleUpdate($mediatype)
	{
		$name=$mediatype['description'];

		$sql="select * from media_type where description='$name' order by mediatypeid";
		$oldHash=DBhash($sql);

		$this->login('media_types.php');
		$this->assertTitle('Media types');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Media types');
		$this->ok('Media type updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF MEDIA TYPES');

		$this->assertEquals($oldHash,DBhash($sql));
	}

	public function testPageMediaTypes_MassDeleteAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testPageMediaTypes_MassDelete($mediatype)
	{
		$id=$mediatype['mediatypeid'];

		$row=DBfetch(DBselect("select count(*) as cnt from media_type where mediatypeid=$id"));
		$row=DBfetch(DBselect("select count(*) as cnt from operations where mediatypeid=$id"));
		$used_by_operations = ($row['cnt'] > 0);

		DBsave_tables(array('media_type','media','operations'));

		$this->chooseOkOnNextConfirmation();

		$this->login('media_types.php');
		$this->assertTitle('Media types');
		$this->checkbox_select("media_types[$id]");
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->assertTitle('Media types');
		switch($used_by_operations){
			case true:
				$this->nok('Media type deleted');
				$this->ok('Media type was not deleted');
				$this->ok('Mediatypes used by action');
			break;
			case false:
				$this->ok('Media type deleted');
				$sql="select * from media_type where mediatypeid=$id";
				$this->assertEquals(0,DBcount($sql));
			break;
		}

		DBrestore_tables(array('media_type','media','operations'));
	}

	public function testPageMediaTypes_Sorting()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
