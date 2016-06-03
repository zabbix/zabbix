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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormAdministrationGeneralInstallation extends CWebTest {

	private $failIfNotExistsInstall = [
		'Welcome',
		'Check of pre-requisites',
		'Configure DB connection',
		'Zabbix server details',
		'Pre-installation summary',
		'Install'
	];

	private $failIfNotExistsPrereq = [
		'PHP version',
		'PHP option "memory_limit"',
		'PHP option "post_max_size"',
		'PHP option "upload_max_filesize"',
		'PHP option "max_execution_time"',
		'PHP option "max_input_time"',
		'PHP option "date.timezone"',
		'PHP databases support',
		'PHP bcmath',
		'PHP mbstring',
		'PHP option "mbstring.func_overload"',
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
		'PHP option "session.auto_start"',
		'PHP gettext',
		'PHP option "arg_separator.output"',
		'Current value',
		'Required'
	];

	private $failIfNotExistsDBConf = [
		'Database type',
		'Database host',
		'Database port',
		'Database name',
		'User',
		'Password'
	];

	public function testInstallPage() {
		$this->zbxTestLogin('setup.php');

		// welcome page

		$this->zbxTestCheckTitle('Installation');
		$this->zbxTestTextNotPresent($this->failIfExists);
		$this->zbxTestTextPresent($this->failIfNotExistsInstall);

		$this->zbxAssertElementPresent(WebDriverBy::id('cancel'));
		$this->zbxAssertElementPresent(WebDriverBy::id('next_0'));

		$this->zbxTestClickWait('next_0');

		// check of pre-requisites

		$this->zbxTestCheckTitle('Installation');
		$this->zbxTestCheckHeader('Check of pre-requisites');
		$this->zbxTestTextNotPresent($this->failIfExists);
		$this->zbxTestTextPresent($this->failIfNotExistsInstall);
		$this->zbxTestTextPresent($this->failIfNotExistsPrereq);

		$this->zbxAssertElementPresent(WebDriverBy::id('cancel'));
		$this->zbxAssertElementPresent(WebDriverBy::id('back_1'));
		$this->zbxAssertElementPresent(WebDriverBy::id('next_1'));

		$this->zbxTestClickWait('next_1');

		// configure db connection

		$this->zbxTestCheckTitle('Installation');
		$this->zbxTestCheckHeader('Configure DB connection');
		$this->zbxTestTextNotPresent($this->failIfExists);
		$this->zbxTestTextPresent($this->failIfNotExistsInstall);
		$this->zbxTestTextPresent($this->failIfNotExistsDBConf);

		$this->zbxAssertElementPresent(WebDriverBy::id('cancel'));
		$this->zbxAssertElementPresent(WebDriverBy::id('back_2'));
		$this->zbxAssertElementPresent(WebDriverBy::id('next_2'));

		$this->zbxAssertElementPresent(WebDriverBy::id('type'));

		$this->zbxAssertElementPresent(WebDriverBy::id('server'));
		$this->zbxAssertAttribute("//input[@id='server']", "maxlength", 255);
		$this->zbxAssertAttribute("//input[@id='server']", "size", 20);

		$this->zbxAssertElementPresent(WebDriverBy::id('port'));
		$this->zbxAssertAttribute("//input[@id='port']", "maxlength", 5);
		$this->zbxAssertAttribute("//input[@id='port']", "size", 20);

		$this->zbxAssertElementPresent(WebDriverBy::id('database'));
		$this->zbxAssertAttribute("//input[@id='database']", "maxlength", 255);
		$this->zbxAssertAttribute("//input[@id='database']", "size", 20);

		$this->zbxAssertElementPresent(WebDriverBy::id('user'));
		$this->zbxAssertAttribute("//input[@id='user']", "maxlength", 255);
		$this->zbxAssertAttribute("//input[@id='user']", "size", 20);

		$this->zbxAssertElementPresent(WebDriverBy::id('password'));
		$this->zbxAssertAttribute("//input[@id='password']", "maxlength", 255);
		$this->zbxAssertAttribute("//input[@id='password']", "size", 20);

		$this->zbxTestClickWait('cancel');

		$this->zbxTestCheckTitle('Dashboard');
		$this->zbxTestCheckHeader('Dashboard');
	}
}
