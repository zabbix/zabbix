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

class testFormMediaType extends CWebTest
{
	// Returns all media types
	public static function allMediaTypes()
	{
		return DBdata('select * from media_type');
	}

	public function testFormMediaType_SimpleTest()
	{
		$this->login('media_types.php');
		$this->assertTitle('Media types');

		$this->click('form');
		$this->wait();

		$this->ok('Media types');
		$this->ok('CONFIGURATION OF MEDIA TYPES');
		$this->nok('Displaying');
		$this->ok(array('Description','Type','SMTP server','SMTP helo','SMTP email'));

		$this->click('cancel');
		$this->wait();

		$this->assertTitle('Media types');
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormMediaType_SimpleCancel($mediatype)
	{
		$name=$mediatype['description'];

		$sql="select * from media_type order by mediatypeid";
		$oldHash=DBhash($sql);

		$this->login('media_types.php');
		$this->assertTitle('Media types');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('cancel');
		$this->wait();
		$this->assertTitle('Media types');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF MEDIA TYPES');

		$this->assertEquals($oldHash,DBhash($sql));
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormMediaType_SimpleDelete($mediatype)
	{
		$name=$mediatype['description'];
		$id=$mediatype['mediatypeid'];

		$row=DBfetch(DBselect("select count(*) as cnt from media_type where mediatypeid=$id"));
//echo "Media_type:".$row['cnt']."\n";
		$row=DBfetch(DBselect("select count(*) as cnt from operations where mediatypeid=$id"));
		$used_by_operations = ($row['cnt'] > 0);
//echo "Media:".$row['cnt']."\n";

		DBsave_tables(array('media_type','media','operations'));

		$this->chooseOkOnNextConfirmation();

		$this->login('media_types.php');
		$this->assertTitle('Media types');
		$this->click("link=$name");
		$this->wait();

		$this->button_click('delete');

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
}
?>
