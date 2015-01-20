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

	private $sqlHashMediaType = '';
	private $oldHashMediaType = '';

	private $mediatypes = array(
		MEDIA_TYPE_EMAIL => 'Email',
		MEDIA_TYPE_EXEC => 'Script',
		MEDIA_TYPE_SMS => 'SMS',
		MEDIA_TYPE_JABBER => 'Jabber',
		MEDIA_TYPE_EZ_TEXTING => 'Ez Texting'
	);

	private function calculateHash($mediatypeid) {
		$this->sqlHashMediaType = 'SELECT * FROM media_type WHERE mediatypeid='.$mediatypeid;
		$this->oldHashMediaType = DBhash($this->sqlHashMediaType);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashMediaType, DBhash($this->sqlHashMediaType));
	}

	public static function allMediaTypes() {
		return DBdata('SELECT mediatypeid,description FROM media_type');
	}

	public function testPageAdministrationMediaTypes_CheckLayout() {
		$this->zbxTestLogin('media_types.php');
		$this->zbxTestCheckTitle('Configuration of media types');

		$this->zbxTestTextPresent('CONFIGURATION OF MEDIA TYPES');
		$this->zbxTestTextPresent('Media types');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(array('Name', 'Type', 'Status', 'Used in actions', 'Details'));

		$dbResult = DBselect('SELECT description,type FROM media_type');

		while ($dbRow = DBfetch($dbResult)) {
			$this->zbxTestTextPresent(array($dbRow['description'], $this->mediatypes[$dbRow['type']]));
		}

		$this->zbxTestDropdownHasOptions('action', array('Enable selected', 'Disable selected', 'Delete selected'));
		$this->assertElementValue('goButton', 'Go (0)');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_SimpleUpdate($mediatype) {
		$this->calculateHash($mediatype['mediatypeid']);

		$this->zbxTestLogin('media_types.php');
		$this->zbxTestClickWait('link='.$mediatype['description']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestTextPresent('Media type updated');
		$this->zbxTestTextPresent($mediatype['description']);

		$this->verifyHash();
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Disable($mediatype) {
		DBexecute(
			'UPDATE media_type'.
			' SET status='.MEDIA_TYPE_STATUS_ACTIVE.
			' WHERE mediatypeid='.$mediatype['mediatypeid']
		);

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('media_types.php');
		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestDropdownSelect('action', 'Disable selected');
		$this->zbxTestClickWait('goButton');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestTextPresent('Media type disabled');

		$this->assertEquals(1, DBcount(
			'SELECT NULL'.
			' FROM media_type'.
			' WHERE status='.MEDIA_TYPE_STATUS_DISABLED.
				' AND mediatypeid='.$mediatype['mediatypeid']
		));
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Enable($mediatype) {
		DBexecute(
			'UPDATE media_type'.
			' SET status='.MEDIA_TYPE_STATUS_DISABLED.
			' WHERE mediatypeid='.$mediatype['mediatypeid']
		);

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('media_types.php');
		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestDropdownSelect('action', 'Enable selected');
		$this->zbxTestClickWait('goButton');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestTextPresent('Media type enabled');

		$this->assertEquals(1, DBcount(
			'SELECT NULL'.
			' FROM media_type'.
			' WHERE status='.MEDIA_TYPE_STATUS_ACTIVE.
				' AND mediatypeid='.$mediatype['mediatypeid']
		));
	}

	public function testPageAdministrationMediaTypes_backup() {
		DBsave_tables('media_type');
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Delete($mediatype) {
		$dbRow = DBfetch(DBselect(
				'SELECT COUNT(*) AS count'.
				' FROM opmessage'.
				' WHERE mediatypeid='.$mediatype['mediatypeid']
		));
		$usedInOperations = ($dbRow['count'] > 0);

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('media_types.php');
		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		$this->zbxTestClickWait('goButton');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of media types');

		$sql = 'SELECT NULL FROM media_type WHERE mediatypeid='.$mediatype['mediatypeid'];

		if ($usedInOperations) {
				$this->zbxTestTextNotPresent('Media type deleted');
				$this->zbxTestTextPresent(array('ERROR: Cannot delete media type', 'Media types used by action'));
				$this->assertEquals(1, DBcount($sql));
		}
		else {
				$this->zbxTestTextPresent('Media type deleted');
				$this->assertEquals(0, DBcount($sql));
		}
	}

	public function testPageAdministrationMediaTypes_restore() {
		DBrestore_tables('media_type');
	}

}
