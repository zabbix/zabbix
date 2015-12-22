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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormAdministrationGeneralInstallation extends CWebTest {

	private $pageLink = 'setup.php';
	private $pageName = 'Installation';

	private $failIfNotExistsInstall = array(
		'1. Welcome',
		'2. Check of pre-requisites',
		'3. Configure DB connection',
		'4. Zabbix server details',
		'5. Pre-Installation summary',
		'6. Install'
	);

	private $failIfNotExistsPrereq = array(
		'PHP version',
		'PHP option memory_limit',
		'PHP option post_max_size',
		'PHP option upload_max_filesize',
		'PHP option max_execution_time',
		'PHP option max_input_time',
		'PHP time zone',
		'PHP databases support',
		'PHP bcmath',
		'PHP mbstring',
		'PHP sockets',
		'PHP gd',
		'PHP gd PNG support',
		'PHP gd JPEG support',
		'PHP gd FreeType support',
		'PHP libxml',
		'PHP xmlwriter',
		'PHP xmlreader',
		'PHP ctype',
		'PHP session',
		'PHP session auto start',
		'PHP gettext',
		'Current value',
		'Required'
	);

	private $failIfNotExistsDBConf = array(
		'Database type',
		'Database host',
		'Database port',
		'Database name',
		'User',
		'Password'
	);

	public function testInstallPage() {

		$this->zbxTestLogin();
		// Setup Welcome page
		$this->zbxTestOpen($this->pageLink);
		$this->checkTitle($this->pageName);

		foreach ($this->failIfExists as $str) {
			$this->zbxTestTextNotPresent($str, 'assertTextNotPresent('.$this->pageLink.','.$str.')');
		}

		foreach ($this->failIfNotExistsInstall as $str) {
			$this->zbxTestTextPresent($str, 'assertTextPresent('.$this->pageLink.','.$str.')');
		}

		$this->assertElementPresent('cancel');
		$this->assertElementPresent('next_0');

		$this->zbxTestClickWait('next_0');

		// Setup Check of pre-requisites page
		$this->checkTitle($this->pageName);

		foreach ($this->failIfExists as $str) {
			$this->zbxTestTextNotPresent($str, 'assertTextNotPresent('.$this->pageLink.','.$str.')');
		}

		foreach ($this->failIfNotExistsInstall as $str) {
			$this->zbxTestTextPresent($str, 'assertTextPresent('.$this->pageLink.','.$str.')');
		}

		foreach ($this->failIfNotExistsPrereq as $str) {
			$this->zbxTestTextPresent($str, 'assertTextPresent('.$this->pageLink.','.$str.')');
		}

		$this->assertElementPresent('cancel');
		$this->assertElementPresent('back_1');
		$this->assertElementPresent('next_1');

		$this->zbxTestClickWait('next_1');

		// Setup Configure DB connection page
		$this->checkTitle($this->pageName);
		foreach ($this->failIfExists as $str) {
			$this->zbxTestTextNotPresent($str, 'assertTextNotPresent('.$this->pageLink.','.$str.')');
		}

		foreach ($this->failIfNotExistsInstall as $str) {
			$this->zbxTestTextPresent($str, 'assertTextPresent('.$this->pageLink.','.$str.')');
		}

		foreach ($this->failIfNotExistsDBConf as $str) {
			$this->zbxTestTextPresent($str, 'assertTextPresent('.$this->pageLink.','.$str.')');
		}

		// Asserting Form buttons
		$this->assertElementPresent('retry');

		$this->assertElementPresent('cancel');
		$this->assertElementPresent('back_2');
		$this->assertElementPresent('next_2');

		// Asserting Form elements
		$this->assertElementPresent('type');
		$this->assertElementPresent('server');
		$this->assertElementPresent('port');
		$this->assertElementPresent('database');
		$this->assertElementPresent('user');
		$this->assertElementPresent('password');

		// Asserting elements attributes
		$this->assertAttribute("//input[@id='server']/@maxlength", 255);
		$this->assertAttribute("//input[@id='server']/@size", 20);
		$this->assertAttribute("//input[@id='port']/@maxlength", 5);
		$this->assertAttribute("//input[@id='port']/@size", 5);
		$this->assertAttribute("//input[@id='database']/@maxlength", 255);
		$this->assertAttribute("//input[@id='database']/@size", 20);
		$this->assertAttribute("//input[@id='user']/@maxlength", 255);
		$this->assertAttribute("//input[@id='user']/@size", 20);
		$this->assertAttribute("//input[@id='password']/@maxlength", 255);
		$this->assertAttribute("//input[@id='password']/@size", 50);

		$this->zbxTestClickWait('cancel');
	}
}
