<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once(dirname(__FILE__) . '/../include/class.cwebtest.php');

class testPageMediaTypes extends CWebTest {
	// Returns all media types
	public static function allMediaTypes() {
		return DBdata('select * from media_type');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageMediaTypes_CheckLayout($mediatype) {
		$this->login('media_types.php');
		$this->assertTitle('Media types');

		$this->ok('Media types');
		$this->ok('CONFIGURATION OF MEDIA TYPES');
		$this->ok('Displaying');
		$this->ok(array('Description', 'Type', 'Details'));
		$this->ok($mediatype['description']);
		if ($mediatype['type'] == MEDIA_TYPE_EMAIL) $this->ok('Email');
		if ($mediatype['type'] == MEDIA_TYPE_JABBER) $this->ok('Jabber');
		if ($mediatype['type'] == MEDIA_TYPE_SMS) $this->ok('SMS');
		if ($mediatype['type'] == MEDIA_TYPE_EZ_TEXTING) $this->ok('Ez Texting');
		$this->dropdown_select('go', 'Delete selected');
//		$this->button_click('delete');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageMediaTypes_SimpleUpdate($mediatype) {
		$name = $mediatype['description'];

		$sql = "select * from media_type where description='$name' order by mediatypeid";
		$oldHash = DBhash($sql);

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

		$this->assertEquals($oldHash, DBhash($sql));
	}

	public function testPageMediaTypes_MassDeleteAll() {
		// TODO
		$this->markTestIncomplete();
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageMediaTypes_MassDelete($mediatype) {
		$id = $mediatype['mediatypeid'];

		$row = DBfetch(DBselect("select count(*) as cnt from opmessage where mediatypeid=$id"));
		$used_by_operations = ($row['cnt'] > 0);

		DBsave_tables('media_type');

		$this->chooseOkOnNextConfirmation();

		$this->login('media_types.php');
		$this->assertTitle('Media types');
		$this->checkbox_select("mediatypeids[$id]");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->assertTitle('Media types');
		if ($used_by_operations) {
			$this->nok('Media type deleted');
			$this->ok('Cannot delete media type');
			$this->ok('Media types used by action');
		}
		else {
			$this->ok('Media type deleted');
			$sql = "select * from media_type where mediatypeid=$id";
			$this->assertEquals(0, DBcount($sql));
		}

		DBrestore_tables('media_type');
	}

	public function testPageMediaTypes_Sorting() {
		// TODO
		$this->markTestIncomplete();
	}
}
?>
