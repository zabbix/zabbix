<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

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
		$this->zbxTestLogin('media_types.php');
		$this->checkTitle('Configuration of media types');

		$this->zbxTestTextPresent(array('Media types', 'CONFIGURATION OF MEDIA TYPES', 'Displaying'));
		$this->zbxTestTextPresent(array('Description', 'Type', 'Status', 'Used in actions', 'Details'));
		$this->zbxTestTextPresent($mediatype['description']);
		switch ($mediatype['type']) {
			case MEDIA_TYPE_EMAIL:
				$this->zbxTestTextPresent('Email');
				break;
			case MEDIA_TYPE_EXEC:
				$this->zbxTestTextPresent('Script');
				break;
			case MEDIA_TYPE_SMS:
				$this->zbxTestTextPresent('SMS');
				break;
			case MEDIA_TYPE_JABBER:
				$this->zbxTestTextPresent('Jabber');
				break;
			case MEDIA_TYPE_EZ_TEXTING:
				$this->zbxTestTextPresent('Ez Texting');
				break;
		}
		$this->zbxTestDropdownHasOptions('go', array('Enable selected', 'Disable selected', 'Delete selected'));
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

		$this->zbxTestLogin('media_types.php');
		$this->checkTitle('Configuration of media types');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of media types');
		$this->zbxTestTextPresent(array('Media type updated', "$name", 'CONFIGURATION OF MEDIA TYPES'));

		$this->assertEquals($oldHashMediaTypes, DBhash($sql));
		$this->assertEquals($oldHashMediaTypes2, DBhash($sql2), 'Chuck Norris: Values for other Media types in the DB should not be changed');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Disable($mediatype) {
		$this->zbxTestLogin('media_types.php');
		$this->checkTitle('Configuration of media types');

		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestDropdownSelect('go', 'Disable selected');
		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClick('goButton');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent('Media type disabled');

		// checking that media type is disabled in the DB
		$sql = 'SELECT NULL FROM media_type WHERE status='.MEDIA_TYPE_STATUS_DISABLED.' AND mediatypeid='.$mediatype['mediatypeid'];
		$this->assertEquals(1, DBcount($sql));
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Enable($mediatype) {
		$this->zbxTestLogin('media_types.php');
		$this->checkTitle('Configuration of media types');

		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestDropdownSelect('go', 'Enable selected');
		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClick('goButton');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent('Media type enabled');

		// checking that media type is enabled in the DB
		$sql = 'SELECT * FROM media_type WHERE status='.MEDIA_TYPE_STATUS_ACTIVE.' AND mediatypeid = '.$mediatype['mediatypeid'];
		$this->assertEquals(1, DBcount($sql));
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_DeleteSelected($mediatype) {
		DBsave_tables('media_type');

		$row = DBfetch(DBselect(
				'SELECT count(*) AS count'.
				' FROM opmessage'.
				' WHERE mediatypeid='.$mediatype['mediatypeid']
		));
		$used_in_operations = ($row['count'] > 0);

		$this->zbxTestLogin('media_types.php');
		$this->checkTitle('Configuration of media types');

		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClick('goButton');
		$this->waitForConfirmation();
		$this->wait();

		$sql = 'SELECT NULL FROM media_type WHERE mediatypeid='.$mediatype['mediatypeid'];

		if ($used_in_operations) {
				$this->zbxTestTextNotPresent('Media type deleted');
				$this->zbxTestTextPresent(array('ERROR: Cannot delete media type', 'Media types used by action'));
				$this->assertEquals(1, DBcount($sql));
		}
		else {
				$this->zbxTestTextPresent('Media type deleted');
				$this->assertEquals(0, DBcount($sql));
		}

		DBrestore_tables('media_type');
	}

	public function testPageAdministrationMediaTypes_MassDeletedAll() {
		$this->markTestIncomplete();
/*		DBsave_tables('media_type');

		$this->zbxTestLogin('media_types.php');
		$this->checkTitle('Configuration of media types');

		$row = DBfetch(DBselect('SELECT count(*) AS cnt FROM opmessage WHERE mediatypeid = '.$mediatypeid.''));
		$used_in_operations = ($row['cnt'] > 0);

		$this->zbxTestCheckboxSelect('all_media_types');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->chooseOkOnNextConfirmation();
		$this->click('goButton');
		$this->wait();
		$this->getConfirmation();

		if ($used_in_operations) {
				$this->zbxTestTextNotPresent('Media type deleted');
				$this->zbxTestTextPresent('Cannot delete media type');
				$this->zbxTestTextPresent('Media types used by action');
		}
		else {
				$this->zbxTestTextPresent('Media type deleted');
				$sql = 'SELECT * FROM media_type WHERE mediatypeid = '.zbx_dbstr($mediatypeid);
				$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Media type has not been deleted from the DB');
		}

		DBrestore_tables('media_type');*/
	}

	public function testPageAdministrationMediaTypes_Sorting() {
		// TODO
		$this->markTestIncomplete();
	}

}
