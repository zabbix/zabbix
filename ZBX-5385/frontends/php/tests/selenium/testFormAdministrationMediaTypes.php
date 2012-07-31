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

class testFormAdministrationMediaTypes extends CWebTest {

	public static function allMediaTypes() {
		return DBdata('SELECT * FROM media_type');
	}

	public static function newMediaTypes() {
		$data=array(
			array(
				'Email', array('Description' => 'Email2', 'SMTP server' => 'mail.zabbix.com',
						'SMTP helo' => 'zabbix.com', 'SMTP email' => 'zabbix@zabbix.com')
			),
			array(
				'Email', array('Description' => 'Email3', 'SMTP server' => 'mail2.zabbix.com',
					'SMTP helo' => 'zabbix.com', 'SMTP email' => 'zabbix2@zabbix.com')),
			array('Script', array('Description' => 'Skype message', 'Script' => '/usr/local/bin/skype.sh')),
			array(
				'Script', array('Description' => 'Skype message2',
						'Script' => '/usr/local/bin/skyp2.sh')),
			array('SMS', array('Description' => 'Direct SMS messaging', 'GSM modem' => '/dev/ttyS3')),
			array(
				'Jabber',
				array(
					'Description' => 'Jabber messages',
					'Jabber identifier' => 'zabbix@jabber.com',
					'Password' => 'Secret password'
				)
			)
		);
		return $data;
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_CheckLayout($allMediaTypes) {
		$this->login('media_types.php');
		$this->checkTitle('Configuration of media types');

		$this->click('form');
		$this->wait();

		$this->ok('Media types');
		$this->ok('CONFIGURATION OF MEDIA TYPES');
		$this->ok('Media');
		$this->nok('Displaying');
		$this->ok(array('Description', 'Type', 'SMTP server', 'SMTP helo', 'SMTP email'));

		$this->assertElementPresent('description');
		$this->assertAttribute("//input[@id='description']/@maxlength", '100');
		$this->assertAttribute("//input[@id='description']/@size", '50');

		$this->assertElementPresent('type');
		$this->assertElementPresent("//select[@id='type']/option[text()='Email']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Script']");
		$this->assertElementPresent("//select[@id='type']/option[text()='SMS']");
		$this->assertElementPresent("//select[@id='type']/option[text()='Jabber']");

		$this->assertElementPresent("//select[@id='type']/optgroup[@label='Commercial']/option[text()='Ez Texting']");

		$this->assertElementPresent('smtp_server');
		$this->assertAttribute("//input[@id='smtp_server']/@maxlength", '255');
		$this->assertAttribute("//input[@id='smtp_server']/@size", '50');

		$this->assertElementPresent('smtp_helo');
		$this->assertAttribute("//input[@id='smtp_helo']/@maxlength", '255');
		$this->assertAttribute("//input[@id='smtp_helo']/@size", '50');

		$this->assertElementPresent('smtp_email');
		$this->assertAttribute("//input[@id='smtp_email']/@maxlength", '255');
		$this->assertAttribute("//input[@id='smtp_email']/@size", '50');

		$this->assertElementPresent('status');
		if ($allMediaTypes['status']) {
			$this->assertElementPresent("//input[@id='status' and not @checked]");
		}

		// media type enabled
		if ($allMediaTypes['status']==0) {
			$this->assertElementPresent("//input[@id='status' and (@checked)]");
		}

		$this->click('cancel');
		$this->wait();

		$this->checkTitle('Configuration of media types');
	}

	/**
	* @dataProvider newMediaTypes
	*/
	public function testFormAdministrationMediaTypes_Create($type, $data) {
		$this->login('media_types.php');
		$this->checkTitle('Configuration of media types');
		$this->button_click('form');
		$this->wait();

		switch ($type) {
			case 'Email':
				$this->dropdown_select('type', $type);
				$this->input_type('description', $data['Description']);
				$this->input_type('smtp_server', $data['SMTP server']);
				$this->input_type('smtp_helo', $data['SMTP helo']);
				$this->input_type('smtp_email', $data['SMTP email']);
				break;
			case 'Script':
				$this->dropdown_select('type', $type);
				$this->wait();
				$this->input_type('description', $data['Description']);
				$this->input_type('exec_path', $data['Script']);
				break;
			case 'SMS':
				$this->dropdown_select('type', $type);
				$this->wait();
				$this->input_type('description', $data['Description']);
				$this->input_type('gsm_modem', $data['GSM modem']);
				break;
			case 'Jabber':
				$this->dropdown_select('type', $type);
				$this->wait();
				$this->input_type('description', $data['Description']);
				$this->input_type('username', $data['Jabber identifier']);
				$this->input_type('password', $data['Password']);
				break;
			case 'Ez Texting':
				$this->dropdown_select('type', $type);
				$this->wait();
				$this->input_type('description', $data['Description']);
				$this->input_type('username', $data['Username']);
				$this->input_type('password', $data['Password']);
				break;
		}

		$this->click('save');
		$this->wait();

		$this->checkTitle('Configuration of media types');
		$this->nok('ERROR');
		$this->ok($data['Description']);
		$this->ok('CONFIGURATION OF MEDIA TYPES');
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_SimpleCancel($mediatype) {
		$name = $mediatype['description'];

		$sql = 'SELECT * FROM media_type ORDER BY mediatypeid';
		$oldHashMediaType = DBhash($sql);

		$this->login('media_types.php');
		$this->checkTitle('Configuration of media types');
		$this->click('link='.$name);
		$this->wait();
		$this->button_click('cancel');
		$this->wait();
		$this->checkTitle('Configuration of media types');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF MEDIA TYPES');

		$this->assertEquals($oldHashMediaType, DBhash($sql), 'Chuck Norris: Media type values in the DB should not be changed in this case');
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_SimpleUpdate($mediatype) {

		$name = $mediatype['description'];
		$sqlMediaType = 'SELECT * FROM  media_type ORDER BY description';
		$oldHashMediaType=DBhash($sqlMediaType);

		$this->login('media_types.php');
		$this->checkTitle('Configuration of media types');
		$this->ok('CONFIGURATION OF MEDIA TYPES');
		$this->click('link='.$name);
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->ok('Media type updated');

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

		DBsave_tables('media_type');

		$this->login('media_types.php');
		$this->checkTitle('Configuration of media types');
		$this->click('link='.$name);
		$this->chooseOkOnNextConfirmation();
		$this->wait();

		$this->button_click('delete');

		$this->getConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of media types');
		if ($used_by_operations) {
				$this->nok('Media type deleted');
				$this->ok('Cannot delete media type');
				$this->ok('Media types used by action');
		}
		else {
				$this->ok('Media type deleted');
				$sql = 'SELECT count(*) AS cnt FROM media_type WHERE mediatypeid='.zbx_dbstr($id).'';
				//$this->assertEquals(0, DBcount($sql), "Chuck Norris: Media type has not been deleted from the DB");
		}

		DBrestore_tables('media_type');
	}
}
?>
