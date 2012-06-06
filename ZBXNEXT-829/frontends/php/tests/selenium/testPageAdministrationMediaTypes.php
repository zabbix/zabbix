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
require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testPageAdministrationMediaTypes extends CWebTest {
	// Returns all media types
	public static function allMediaTypes() {
		return DBdata('SELECT * FROM media_type');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_CheckLayout($mediatype) {

		$this->login('media_types.php');
		$this->checkTitle('Configuration of media types');

		$this->ok(array('Media types', 'CONFIGURATION OF MEDIA TYPES', 'Displaying'));
		$this->ok(array('Description', 'Type', 'Details'));
		$this->ok($mediatype['description']);
		if ($mediatype['type'] == MEDIA_TYPE_EMAIL) $this->ok('Email');
		if ($mediatype['type'] == MEDIA_TYPE_JABBER) $this->ok('Jabber');
		if ($mediatype['type'] == MEDIA_TYPE_SMS) $this->ok('SMS');
		if ($mediatype['type'] == MEDIA_TYPE_EZ_TEXTING) $this->ok('Ez Texting');
		$this->assertElementPresent("//select[@id='go']/option[text()='Enable selected']");
		$this->assertElementPresent("//select[@id='go']/option[text()='Disable selected']");
		$this->assertElementPresent("//select[@id='go']/option[text()='Delete selected']");

	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_SimpleUpdate($mediatype) {

		$name = $mediatype['description'];
		$sql = 'SELECT * FROM media_type WHERE description = '.zbx_dbstr($name).' ORDER BY mediatypeid';
		$oldHashMediaTypes = DBhash($sql);

		$sql2 = 'SELECT * FROM media_type WHERE description <> '.zbx_dbstr($name).' ORDER BY mediatypeid';
		$oldHashMediaTypes2 = DBhash($sql2);

		$this->login('media_types.php');
		$this->checkTitle('Configuration of media types');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of media types');
		$this->ok(array('Media type updated', "$name", 'CONFIGURATION OF MEDIA TYPES'));

		$this->assertEquals($oldHashMediaTypes, DBhash($sql));
		$this->assertEquals($oldHashMediaTypes2, DBhash($sql2), 'Chuck Norris: Values for other Media types in the DB should not be changed');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Disable($mediatype) {

		$this->login('media_types.php');

		$mediatypeid = $mediatype['mediatypeid'];
		$this->checkTitle('Configuration of media types');

		$sql = 'SELECT mediatypeid FROM media_type WHERE mediatypeid = '.$mediatypeid;
		$result = DBfetch(DBselect($sql));
		$this->checkbox_select("mediatypeids_$mediatypeid");
		$this->dropdown_select('go', 'Disable selected');
		$this->chooseOkOnNextConfirmation();
		$this->click('goButton');
		$this->wait();
		$this->getConfirmation();
		$this->ok('Media type disabled');

		// checking that media type is disabled in the DB
		$sql = 'SELECT * FROM media_type WHERE status=1 AND mediatypeid = '.$mediatypeid.' ORDER BY mediatypeid';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Media type has not been disabled');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Enable($mediatype) {

		$this->login('media_types.php');

		$name = $mediatype['description'];
		$this->checkTitle('Configuration of media types');

		$sql = 'SELECT mediatypeid FROM media_type WHERE description = '.zbx_dbstr($name);
		$result = DBfetch(DBselect($sql));
		$mediatypeid = $result['mediatypeid'];
		$this->checkbox_select("mediatypeids_$mediatypeid");
		$this->dropdown_select('go', 'Enable selected');
		$this->chooseOkOnNextConfirmation();
		$this->click('goButton');
		$this->wait();
		$this->getConfirmation();
		$this->ok('Media type enabled');
		// checking that media type is enabled in the DB
		$sql = 'SELECT * FROM media_type WHERE status=0 AND mediatypeid = '.$mediatypeid.' ORDER BY mediatypeid';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Media type has not been enabled');

	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_DeleteSelected($mediatype) {

		DBsave_tables('media_type');

		$this->login('media_types.php');

		$mediatypeid = $mediatype['mediatypeid'];
		$this->checkTitle('Configuration of media types');

		$sql = 'SELECT mediatypeid FROM media_type WHERE mediatypeid = '.$mediatypeid;
		$result = DBfetch(DBselect($sql));

		$row = DBfetch(DBselect('SELECT count(*) AS count FROM opmessage WHERE mediatypeid = '.$mediatypeid.''));
		$used_in_operations = ($row['count'] > 0);

		$this->checkbox_select("mediatypeids_$mediatypeid");
		$this->dropdown_select('go', 'Delete selected');
		$this->chooseOkOnNextConfirmation();
		$this->click('goButton');
		$this->wait();
		$this->getConfirmation();

		if ($used_in_operations) {
				$this->nok('Media type deleted');
				$this->ok('Cannot delete media type');
				$this->ok('Media types used by action');
		}
		else {
				$this->ok('Media type deleted');
				$sql = 'SELECT * FROM media_type WHERE mediatypeid = '.zbx_dbstr($mediatypeid);
				$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Media type has not been deleted from the DB');
		}

		DBrestore_tables('media_type');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
/*	public function testPageAdministrationMediaTypes_MassDeleteAll($mediatype) {

		DBsave_tables('media_type');
		// DBsave_tables('media');
		$this->login('media_types.php');

		$mediatypeid = $mediatype['mediatypeid'];
		$this->checkTitle('Media types');

		$row = DBfetch(DBselect('SELECT count(*) AS cnt FROM opmessage WHERE mediatypeid = '.$mediatypeid.''));
		$used_in_operations = ($row['cnt'] > 0);

		$this->checkbox_select("all_media_types");
		$this->dropdown_select('go', 'Delete selected');
		$this->chooseOkOnNextConfirmation();
		$this->click('goButton');
		$this->wait();
		$this->getConfirmation();

		if ($used_in_operations) {
				$this->nok('Media type deleted');
				$this->ok('Cannot delete media type');
				$this->ok('Media types used by action');
		}
		else {
				$this->ok('Media type deleted');
				$sql = 'SELECT * FROM media_type WHERE mediatypeid = '.zbx_dbstr($mediatypeid);
				$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Media type has not been deleted from the DB');
		}

		DBrestore_tables('media_type');
	}
*/

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_MassDelete($mediatype) {
		$id = $mediatype['mediatypeid'];

		$row = DBfetch(DBselect('SELECT count(*) as cnt FROM opmessage WHERE mediatypeid = '.$id.''));
		$used_in_operations = ($row['cnt'] > 0);

		DBsave_tables('media_type');

		$this->chooseOkOnNextConfirmation();

		$this->login('media_types.php');
		$this->checkTitle('Configuration of media types');
		$this->checkbox_select("mediatypeids[$id]");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of media types');
		if ($used_in_operations) {
			$this->nok('Media type deleted');
			$this->ok('Cannot delete media type');
			$this->ok('Media types used by action');
		}
		else {
			$this->ok('Media type deleted');
			$sql = 'SELECT * FROM media_type WHERE mediatypeid = '.$id.'';
			$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Media type has not been deleted from the DB');
		}

		DBrestore_tables('media_type');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Sorting() {
		// TODO
		$this->markTestIncomplete();
	}

}
?>
