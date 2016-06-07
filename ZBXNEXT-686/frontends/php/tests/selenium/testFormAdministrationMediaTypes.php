<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

class testFormAdministrationMediaTypes extends CWebTest {

	public static function allMediaTypes() {
		return DBdata('SELECT * FROM media_type');
	}

	public static function newMediaTypes() {
		$data=[
			[
				'Email', ['Description' => 'Email2', 'SMTP server' => 'mail.zabbix.com',
						'SMTP helo' => 'zabbix.com', 'SMTP email' => 'zabbix@zabbix.com']
			],
			[
				'Email', ['Description' => 'Email3', 'SMTP server' => 'mail2.zabbix.com',
					'SMTP helo' => 'zabbix.com', 'SMTP email' => 'zabbix2@zabbix.com']
			],
			[
				'Script', ['Description' => 'Skype message', 'Script' => '/usr/local/bin/skype.sh']
			],
			[
				'Script', ['Description' => 'Skype message2', 'Script' => '/usr/local/bin/skyp2.sh']
			],
			[
				'SMS', ['Description' => 'Direct SMS messaging', 'GSM modem' => '/dev/ttyS3']
			],
			[
				'Jabber',
				[
					'Description' => 'Jabber messages',
					'Jabber identifier' => 'zabbix@jabber.com',
					'Password' => 'Secret password'
				]
			]
		];
		return $data;
	}

	public function testFormAdministrationMediaTypes_backup() {
		DBsave_tables('media_type');
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_CheckLayout($allMediaTypes) {
		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');

		$this->zbxTestClickButtonText('Create media type');

		$this->zbxTestTextPresent('Media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestTextNotPresent('Displaying');
		$this->zbxTestTextPresent(['Name', 'Type', 'SMTP server', 'SMTP server port', 'SMTP helo', 'SMTP email', 'Connection security', 'Authentication']);

		$this->zbxAssertElementPresent(WebDriverBy::id('description'));
		$this->zbxAssertAttribute("//input[@id='description']", "maxlength", 100);
		$this->zbxAssertAttribute("//input[@id='description']", "size", 20);

		$this->zbxAssertElementPresent(WebDriverBy::id('type'));
		$this->zbxTestDropdownHasOptions('type', ['Email', 'Script', 'SMS', 'Jabber']);
		$this->zbxAssertElementPresent(WebDriverBy::xpath("//select[@id='type']/optgroup[@label='Commercial']/option[text()='Ez Texting']"));

		$this->zbxAssertElementPresent(WebDriverBy::id('smtp_server'));
		$this->zbxAssertAttribute("//input[@id='smtp_server']", "maxlength", 255);
		$this->zbxAssertAttribute("//input[@id='smtp_server']", "size", 20);

		$this->zbxAssertElementPresent(WebDriverBy::id('smtp_helo'));
		$this->zbxAssertAttribute("//input[@id='smtp_helo']", "maxlength", 255);
		$this->zbxAssertAttribute("//input[@id='smtp_helo']", "size", 20);

		$this->zbxAssertElementPresent(WebDriverBy::id('smtp_email'));
		$this->zbxAssertAttribute("//input[@id='smtp_email']", "maxlength", 255);
		$this->zbxAssertAttribute("//input[@id='smtp_email']", "size", 20);

		$this->zbxAssertElementPresent(WebDriverBy::id('status'));
		if ($allMediaTypes['status']) {
			$this->assertFalse($this->zbxTestCheckboxSelected('status'));
		}

		// media type enabled
		if ($allMediaTypes['status']==0) {
			$this->assertTrue($this->zbxTestCheckboxSelected('status'));
		}

		$this->zbxTestClickWait('cancel');

		$this->zbxTestCheckTitle('Configuration of media types');
	}

	/**
	* @dataProvider newMediaTypes
	*/
	public function testFormAdministrationMediaTypes_Create($type, $data) {
		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestClickButtonText('Create media type');

		switch ($type) {
			case 'Email':
				$this->zbxTestDropdownSelect('type', $type);
				$this->input_type('description', $data['Description']);
				$this->input_type('smtp_server', $data['SMTP server']);
				$this->input_type('smtp_helo', $data['SMTP helo']);
				$this->input_type('smtp_email', $data['SMTP email']);
				break;
			case 'Script':
				$this->zbxTestDropdownSelectWait('type', $type);
				$this->input_type('description', $data['Description']);
				$this->input_type('exec_path', $data['Script']);
				break;
			case 'SMS':
				$this->zbxTestDropdownSelectWait('type', $type);
				$this->input_type('description', $data['Description']);
				$this->input_type('gsm_modem', $data['GSM modem']);
				break;
			case 'Jabber':
				$this->zbxTestDropdownSelectWait('type', $type);
				$this->input_type('description', $data['Description']);
				$this->input_type('jabber_username', $data['Jabber identifier']);
				$this->input_type('passwd', $data['Password']);
				break;
			case 'Ez Texting':
				$this->zbxTestDropdownSelectWait('type', $type);
				$this->input_type('description', $data['Description']);
				$this->input_type('eztext_username', $data['Username']);
				$this->input_type('passwd', $data['Password']);
				break;
		}

		$this->zbxTestClickWait('add');

		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestTextNotPresent('Cannot add media type');
		$this->zbxTestTextPresent('Media type added');
		$this->zbxTestTextPresent($data['Description']);
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_SimpleCancel($mediatype) {
		$name = $mediatype['description'];

		$sql = 'SELECT * FROM media_type ORDER BY mediatypeid';
		$oldHashMediaType = DBhash($sql);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestClickLinkText($name);
		$this->zbxTestClickWait('cancel');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestCheckHeader('Media types');

		$this->assertEquals($oldHashMediaType, DBhash($sql), 'Chuck Norris: Media type values in the DB should not be changed in this case');
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_SimpleUpdate($mediatype) {

		$name = $mediatype['description'];
		$sqlMediaType = 'SELECT * FROM  media_type ORDER BY description';
		$oldHashMediaType=DBhash($sqlMediaType);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestClickLinkText($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Media type updated');

		$newHashMediaType = DBhash($sqlMediaType);
		$this->assertEquals($oldHashMediaType, $newHashMediaType, "Chuck Norris: no-change media type update should not update data in table 'media_type'");

	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_SimpleDelete($mediatype) {

		$name = $mediatype['description'];
		$id = $mediatype['mediatypeid'];

		$row = DBfetch(DBselect('SELECT count(*) AS cnt FROM opmessage WHERE mediatypeid='.zbx_dbstr($id).''));
		$used_by_operations = ($row['cnt'] > 0);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestClickLinkText($name);

		$this->zbxTestClickWait('delete');

		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestCheckTitle('Configuration of media types');
		if ($used_by_operations) {
				$this->zbxTestTextNotPresent('Media type deleted');
				$this->zbxTestTextPresent('Cannot delete media type');
				$this->zbxTestTextPresent('Media types used by action');
		}
		else {
				$this->zbxTestTextPresent('Media type deleted');
				$sql = 'SELECT * FROM media_type WHERE mediatypeid='.zbx_dbstr($id).'';
				$this->assertEquals(0, DBcount($sql), "Chuck Norris: Media type has not been deleted from the DB");
		}

	}

	public function testFormAdministrationMediaTypes_restore() {
		DBrestore_tables('media_type');
	}
}
