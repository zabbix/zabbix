<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

/**
 * @backup media_type
 */
class testFormAdministrationMediaTypes extends CLegacyWebTest {

	public static function allMediaTypes() {
		return CDBHelper::getDataProvider('SELECT * FROM media_type');
	}

	public static function layout() {
		return [
			[
				[
					'type' => 'Email',
					'smtp_server' => 'mail.example.com',
					'smtp_port' => 25,
					'smtp_helo' => 'example.com',
					'smtp_email' => 'zabbix@example.com'
				]
			],
			[
				[
					'type' => 'Email',
					'smtp_server' => 'mail.example.com',
					'smtp_port' => 25,
					'smtp_helo' => 'example.com',
					'smtp_email' => 'zabbix@example.com',
					'smtp_security' => 'STARTTLS',
					'smtp_authentication' => 'Username and password'
				]
			],
			[
				[
					'type' => 'Email',
					'smtp_server' => 'mail.example.com',
					'smtp_port' => 25,
					'smtp_helo' => 'example.com',
					'smtp_email' => 'zabbix@example.com',
					'smtp_security' => 'SSL/TLS'
				]
			],
			[
				[
					'type' => 'Email',
					'smtp_server' => 'mail.example.com',
					'smtp_port' => 25,
					'smtp_helo' => 'example.com',
					'smtp_email' => 'zabbix@example.com',
					'message_format' => 'Plain text'
				]
			],
			[
				[
					'type' => 'Script'
				]
			],
			[
				[
					'type' => 'SMS',
					'gsm_modem' => '/dev/ttyS0'
				]
			]
		];
	}

	/**
	* @dataProvider layout
	*/
	public function testFormAdministrationMediaTypes_CheckLayout($data) {
		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');

		$this->zbxTestClickButtonText('Create media type');

		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestTextPresent(['Name', 'Type', 'SMTP server', 'SMTP server port', 'SMTP helo', 'SMTP email',
			'Connection security', 'Authentication', 'Message format']);

		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", "maxlength", 100);

		$this->zbxTestAssertElementPresentId('type');
		$this->zbxTestDropdownAssertSelected('type', 'Email');
		$this->zbxTestDropdownHasOptions('type', ['Email', 'Script', 'SMS']);

		$this->zbxTestAssertNotVisibleId('exec_path');
		$this->zbxTestAssertNotVisibleId('exec_params_table');
		$this->zbxTestAssertNotVisibleId('gsm_modem');
		$this->zbxTestAssertNotVisibleId('passwd');

		if($data['type'] != 'Email') {
			$this->zbxTestDropdownSelectWait('type', $data['type']);
			$this->zbxTestAssertNotVisibleId('smtp_server');
			$this->zbxTestAssertNotVisibleId('smtp_port');
			$this->zbxTestAssertNotVisibleId('smtp_helo');
			$this->zbxTestAssertNotVisibleId('smtp_email');
			$this->zbxTestAssertNotVisibleId('smtp_security_0');
			$this->zbxTestAssertNotVisibleId('smtp_authentication_0');
		}

		switch ($data['type']) {
			case 'Email':
				$this->zbxTestAssertElementValue('smtp_server', $data['smtp_server']);
				$this->zbxTestAssertAttribute("//input[@id='smtp_server']", "maxlength", 255);

				$this->zbxTestAssertElementValue('smtp_port', $data['smtp_port']);
				$this->zbxTestAssertAttribute("//input[@id='smtp_port']", "maxlength", 5);

				$this->zbxTestAssertElementValue('smtp_helo', $data['smtp_helo']);
				$this->zbxTestAssertAttribute("//input[@id='smtp_helo']", "maxlength", 255);

				$this->zbxTestAssertElementValue('smtp_email', $data['smtp_email']);
				$this->zbxTestAssertAttribute("//input[@id='smtp_email']", "maxlength", 255);

				if (array_key_exists('smtp_security', $data)) {
					$smtp_security_id = $data['smtp_security']=='STARTTLS' ? 'smtp_security_1' : 'smtp_security_2';
					$this->zbxTestClickXpath("//label[text()='".$data['smtp_security']."']");
					$this->assertTrue($this->zbxTestCheckboxSelected($smtp_security_id));
					$this->zbxTestAssertVisibleXpath("//label[@for='smtp_verify_host']");
					$this->zbxTestAssertVisibleXpath("//label[@for='smtp_verify_peer']");
				}
				else {
					$this->assertTrue($this->zbxTestCheckboxSelected('smtp_security_0'));
					$this->zbxTestAssertNotVisibleXpath("//label[@for='smtp_verify_host']");
					$this->zbxTestAssertNotVisibleXpath("//label[@for='smtp_verify_peer']");
				}

				if (array_key_exists('smtp_authentication', $data)) {
					$this->zbxTestClickXpath("//label[text()='".$data['smtp_authentication']."']");
					$this->assertTrue($this->zbxTestCheckboxSelected('smtp_authentication_1'));
					$this->zbxTestAssertVisibleId('smtp_username');
					$this->zbxTestAssertVisibleId('passwd');
				}
				else {
					$this->assertTrue($this->zbxTestCheckboxSelected('smtp_authentication_0'));
					$this->zbxTestAssertNotVisibleId('smtp_username');
					$this->zbxTestAssertNotVisibleId('passwd');
				}

				if (array_key_exists('message_format', $data)) {
					$this->zbxTestClickXpath("//label[text()='".$data['message_format']."']");
					$this->assertTrue($this->zbxTestCheckboxSelected('content_type_1'));
				}
				else {
					$this->assertTrue($this->zbxTestCheckboxSelected('content_type_0'));
				}
				break;
			case 'Script':
				$this->zbxTestAssertVisibleId('exec_path');
				$this->zbxTestAssertAttribute("//input[@id='exec_path']", "maxlength", 255);
				$this->zbxTestAssertVisibleId('exec_params_table');
				break;
			case 'SMS':
				$this->zbxTestAssertElementValue('gsm_modem', $data['gsm_modem']);
				$this->zbxTestAssertAttribute("//input[@id='gsm_modem']", "maxlength", 255);
				break;
		}

		$this->zbxTestTabSwitch('Options');
		$this->assertTrue($this->zbxTestCheckboxSelected('maxsessions_type_0'));
		$this->zbxTestAssertElementValue('maxattempts', 3);
		$this->zbxTestAssertElementValue('attempt_interval', '10s');
		$this->zbxTestAssertNotVisibleId('maxsessions');

		if($data['type'] == 'SMS') {
			$this->zbxTestAssertElementPresentXpath("//input[@id='maxsessions_type_1'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//input[@id='maxsessions_type_2'][@disabled]");
			$this->zbxTestAssertNotVisibleId('maxsessions');
		}
		else {
			$this->zbxTestClickXpath("//label[text()='Custom']");
			$this->assertTrue($this->zbxTestCheckboxSelected('maxsessions_type_2'));
			$this->zbxTestAssertVisibleId('maxsessions');
			$this->zbxTestAssertElementValue('maxsessions', 0);
		}

		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		$this->zbxTestClickWait('cancel');

		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_SimpleCancel($mediatype) {
		$name = $mediatype['name'];

		$sql = 'SELECT * FROM media_type ORDER BY mediatypeid';
		$oldHashMediaType = CDBHelper::getHash($sql);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('cancel');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestTextPresent($name);

		$this->assertEquals($oldHashMediaType, CDBHelper::getHash($sql));
	}

	public static function newMediaTypes() {
		$data = [
			[
				'Email', ['Name' => 'Email2', 'SMTP server' => 'mail.zabbix.com',
						'SMTP helo' => 'zabbix.com', 'SMTP email' => 'zabbix@zabbix.com',
						'message_format' => 'Plain text']
			],
			[
				'Email', ['Name' => 'Email3', 'SMTP server' => 'mail2.zabbix.com',
					'SMTP helo' => 'zabbix.com', 'SMTP email' => 'zabbix2@zabbix.com']
			],
			[
				'Script', ['Name' => 'Skype message', 'Script' => '/usr/local/bin/skype.sh']
			],
			[
				'Script', ['Name' => 'Skype message2', 'Script' => '/usr/local/bin/skyp2.sh']
			],
			[
				'SMS', ['Name' => 'Direct SMS messaging', 'GSM modem' => '/dev/ttyS3']
			]
		];
		return $data;
	}

	/**
	* @dataProvider newMediaTypes
	*/
	public function testFormAdministrationMediaTypes_Create($type, $data) {
		$this->zbxTestLogin('zabbix.php?action=mediatype.edit');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');

		switch ($type) {
			case 'Email':
				$this->zbxTestDropdownSelect('type', $type);
				$this->zbxTestInputType('name', $data['Name']);
				$this->zbxTestInputType('smtp_server', $data['SMTP server']);
				$this->zbxTestInputType('smtp_helo', $data['SMTP helo']);
				$this->zbxTestInputType('smtp_email', $data['SMTP email']);
				if (array_key_exists('message_format', $data)) {
					$this->zbxTestClickXpath("//label[text()='".$data['message_format']."']");
				}
				break;
			case 'Script':
				$this->zbxTestDropdownSelectWait('type', $type);
				$this->zbxTestInputType('name', $data['Name']);
				$this->zbxTestInputType('exec_path', $data['Script']);
				break;
			case 'SMS':
				$this->zbxTestDropdownSelectWait('type', $type);
				$this->zbxTestInputType('name', $data['Name']);
				$this->zbxTestInputType('gsm_modem', $data['GSM modem']);
				break;
		}

		$this->zbxTestClickWait('add');

		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestTextNotPresent('Cannot add media type');
		$this->zbxTestTextPresent('Media type added');
		$this->zbxTestTextPresent($data['Name']);
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_SimpleUpdate($mediatype) {
		$name = $mediatype['name'];
		$sqlMediaType = 'SELECT * FROM  media_type ORDER BY name';
		$oldHashMediaType=CDBHelper::getHash($sqlMediaType);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Media type updated');
		$this->zbxTestTextPresent($name);

		$newHashMediaType = CDBHelper::getHash($sqlMediaType);
		$this->assertEquals($oldHashMediaType, $newHashMediaType);
	}

	/**
	* @dataProvider allMediaTypes
	*/
	public function testFormAdministrationMediaTypes_SimpleDelete($mediatype) {
		$name = $mediatype['name'];
		$id = $mediatype['mediatypeid'];

		$row = DBfetch(DBselect('SELECT count(*) AS cnt FROM opmessage WHERE mediatypeid='.zbx_dbstr($id).''));
		$used_by_operations = ($row['cnt'] > 0);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestClickLinkTextWait($name);

		$this->zbxTestClickWait('delete');

		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of media types');
		if ($used_by_operations) {
			$this->zbxTestTextNotPresent('Media type deleted');
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete media type');
			$this->zbxTestTextPresent('Media types used by action');
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Media type deleted');
			$sql = 'SELECT * FROM media_type WHERE mediatypeid='.zbx_dbstr($id);
			$this->assertEquals(0, CDBHelper::getCount($sql));
		}
	}

	public static function create_options(){
		return [
			// attempts validation
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with zero attempt',
					'attempts' => 0,
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with empty attempt',
					'attempts' => '',
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with 101 attempts',
					'attempts' => 101,
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Email attempts with symbols',
					'type' => 'Email',
					'attempts' => 'æų',
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.',
					'jenkins' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Email attempts with symbols',
					'type' => 'Email',
					'attempts' => '☺',
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.',
					'jenkins' => true
				]
			],
			// interval validation
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with 3601s in interval',
					'interval' => '3601s',
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with 3601 in interval',
					'interval' => '3601',
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with 61m in interval',
					'interval' => '61m',
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with 2h in inerval',
					'interval' => '2h',
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with symbols in interval',
					'interval' => '1msms',
					'error' => 'Invalid parameter "/1/attempt_interval": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with symbols in interval',
					'interval' => '☺',
					'error' => 'Invalid parameter "/1/attempt_interval": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with -1s interval',
					'interval' => '-1s',
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with -1 interval',
					'interval' => -1,
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			// maxsessions validation
			[
				[
					'expected' => TEST_BAD,
					'type' => 'Email',
					'name' => 'Email with 101 in custom sessions',
					'sessions' => 'Custom',
					'maxsessions' => '101',
					'error' => 'Invalid parameter "/1/maxsessions": value must be one of 0-100.'
				]
			],
			// Successfully create media type with different options
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Email',
					'name' => 'Email with one concurrent sessions',
					'sessions' => 'One',
					'attempts' => 10,
					'interval' => '60m',
					'dbCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Email',
					'name' => 'Email with unlimited concurrent sessions',
					'sessions' => 'Unlimited',
					'attempts' => 1,
					'interval' => 0
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Email',
					'name' => 'Email with 0h intrval',
					'sessions' => 'Unlimited',
					'attempts' => 1,
					'interval' => '0h'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Email',
					'name' => 'Email with 0d interval',
					'sessions' => 'Unlimited',
					'attempts' => 1,
					'interval' => '0d'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Email',
					'name' => 'Email with 100 attempts',
					'sessions' => 'Unlimited',
					'attempts' => 100,
					'interval' => 3600
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Email',
					'name' => 'Email with 3600s interval',
					'sessions' => 'Unlimited',
					'attempts' => 100,
					'interval' => '3600s'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Email',
					'name' => 'Email with 0s interval',
					'sessions' => 'Unlimited',
					'attempts' => 1,
					'interval' => '0s'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Email',
					'name' => 'Email with 1h interval',
					'sessions' => 'Custom',
					'maxsessions' => 100,
					'interval' => '1h'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'type' => 'Script',
					'name' => 'Script media type',
					'script_name' => 'test',
					'sessions' => 'Custom',
					'maxsessions' => 'abc',
					'attempts' => 10,
					'interval' => '60m',
					'dbCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'SMS media type',
					'type' => 'SMS',
					'attempts' => 1,
					'interval' => '6s'
				]
			]
		];
	}

	/**
	 * Test media type creation with properties from tab "Options".
	 *
	 * @dataProvider create_options
	 */
	public function testFormAdministrationMediaTypes_CreateWithOptions($data) {
		$this->zbxTestLogin('zabbix.php?action=mediatype.edit');
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestDropdownSelect('type', $data['type']);
		$this->zbxTestInputType('name', $data['name']);

		if ($data['type'] == 'Script' ) {
			$this->zbxTestInputType('exec_path', $data['script_name']);
		}
		if (array_key_exists('passwd', $data)){
			$this->zbxTestInputType('passwd', $data['passwd']);
		}

		$this->zbxTestTabSwitch('Options');

		if (array_key_exists('attempts', $data)){
			$this->zbxTestInputTypeOverwrite('maxattempts', $data['attempts']);
		}
		if (array_key_exists('interval', $data)){
			$this->zbxTestInputTypeOverwrite('attempt_interval', $data['interval']);
		}
		if (array_key_exists('sessions', $data)){
			$this->zbxTestClickXpath("//label[text()='".$data['sessions']."']");
			if ($data['sessions'] == 'Custom' && array_key_exists('maxsessions', $data)) {
				$this->zbxTestInputTypeOverwrite('maxsessions', $data['maxsessions']);
				// Fire onchange event.
				$this->webDriver->executeScript('var event = document.createEvent("HTMLEvents");'.
					'event.initEvent("change", false, true);'.
					'document.getElementById("maxsessions").dispatchEvent(event);'
				);
			}
		}

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Media type added');
				$this->zbxTestCheckTitle('Configuration of media types');
				$this->zbxTestTextPresent($data['name']);
				break;

			case TEST_BAD:
				if (array_key_exists('jenkins', $data) && $this->zbxTestIsElementPresent("//*[@id='back']")) {
					$this->zbxTestTextPresent('Fatal error, please report to the Zabbix team');
				}
				else {
					$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add media type');
					$this->zbxTestTextPresent($data['error']);
				}

				$sql = "SELECT * FROM media_type WHERE name = '".$data['name']."'";
				$this->assertEquals(0, CDBHelper::getCount($sql));
				break;
		}

		if(array_key_exists('dbCheck', $data)) {
			$result = DBselect("SELECT * FROM media_type WHERE name = '".$data['name']."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['maxattempts'], $data['attempts']);
				$this->assertEquals($row['attempt_interval'], $data['interval']);
				switch($data['sessions']) {
					case 'One':
						$this->assertEquals($row['maxsessions'], 1);
						break;
					case 'Unlimited':
						$this->assertEquals($row['maxsessions'], 0);
						break;
					case 'Custom':
						if (is_numeric($data['maxsessions'])){
							$this->assertEquals($row['maxsessions'], $data['maxsessions']);
						}
						else {
							$this->assertEquals($row['maxsessions'], 0);
						}
						break;
				}
			}
		}
	}
}
