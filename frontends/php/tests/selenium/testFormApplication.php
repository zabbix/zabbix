<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class testFormApplication extends CWebTest {

	/**
	 * The name of the test application used in the test data set.
	 *
	 * @var string
	 */
	public static $application;

	/**
	 * The number of test case instances.
	 *
	 * @var integer
	 */
	protected static $instances = 0;

	public function __construct() {
		global $DB;

		if (!isset($DB['DB'])) {
			DBconnect($error);
		}

		call_user_func_array('parent::__construct', func_get_args());

		// Methods called once per suite.
		if (self::$instances === 0) {
			$this->backup();
			$this->prepareSuite();
		}

		DBclose();
		self::$instances++;
	}

	public function __destruct() {
		global $DB;

		self::$instances--;

		if (is_callable('parent::__destruct')) {
			parent::__destruct();
		}

		// Methods called once per suite.
		if (self::$instances === 0) {
			if (!isset($DB['DB'])) {
				DBconnect($error);
			}

			$this->restore();
			DBclose();
		}
	}

	/**
	 * Initialize test application name - random name is used.
	 */
	protected function prepareSuite() {
		self::$application .= 'Test application '.microtime(true);
	}

	/**
	 * Perform backup of DB tables.
	 */
	protected function backup() {
		DBsave_tables('applications');
	}

	/**
	 * Restore DB from a backup.
	 */
	protected function restore() {
		DBrestore_tables('applications');
	}

	/**
	 * Update application data.
	 *
	 * @param string $name      current application name
	 * @param string $new_name  new application name (can be null if application should not be renamed)
	 */
	protected function updateApplication($name, $new_name = null) {
		if ($new_name === null) {
			$new_name = $name;
		}

		// Open an application.
		$this->zbxTestLogin('applications.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestDropdownSelectWait('hostid', 'Zabbix server');
		$this->zbxTestClickLinkTextWait($name);

		// Change application name if new name differs from existing name.
		if ($new_name !== $name) {
			$this->zbxTestInputTypeOverwrite('appname', $new_name);
		}

		$this->zbxTestClickWait('update');

		// Check the results of an update.
		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application updated');
		$this->zbxTestTextPresent($new_name);

		// Check the results in DB.
		$sql = "SELECT null FROM applications WHERE name='$new_name'";
		$this->assertEquals(1, DBcount($sql));

		if ($new_name !== $name) {
			// There should be no application with previous name if name was changed.
			$sql = "SELECT null FROM applications WHERE name='$name'";
			$this->assertEquals(0, DBcount($sql));
		}
	}

	/**
	 * Test creation of an application.
	 */
	public function testFormApplication_Create() {
		$name = self::$application;

		// Select hostgroup and host, open a form.
		$this->zbxTestLogin('applications.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestDropdownSelectWait('hostid', 'Zabbix server');
		$this->zbxTestClickWait('form');

		// Set application name and submit the form.
		$this->zbxTestInputTypeWait('appname', $name);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");

		// Check the results in frontend.
		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application added');
		$this->zbxTestTextPresent($name);

		// Check the results in DB.
		$sql = "SELECT null FROM applications WHERE name='{$name}'";
		$this->assertEquals(1, DBcount($sql));
	}

	/**
	 * Test form validations.
	 */
	public function testFormApplication_CheckValidation() {
		// Select hostgroup and host, open a form.
		$this->zbxTestLogin('applications.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestDropdownSelectWait('hostid', 'Zabbix server');
		$this->zbxTestClickWait('form');

		// Check error message on posting the empty form.
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Incorrect value for field "Name": cannot be empty.');

		// Change application name to multiple spaces and check an error message.
		$this->zbxTestInputTypeOverwrite('appname', '      ');
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Incorrect value for field "Name": cannot be empty.');
	}

	/**
	 * Test update without any modification of application data.
	 */
	public function testFormApplication_SimpleUpdate() {
		$this->updateApplication(self::$application);
	}

	/**
	 * Test update by changing application name.
	 */
	public function testFormApplication_Update() {
		$suffix = ' (updated)';

		// Update is perfomed mutiple times to assure that consequential updates are not broken.
		for ($i = 0; $i < 3; $i++) {
			$this->updateApplication(self::$application, self::$application.$suffix);

			// Application name is also updated for the other test cases.
			self::$application .= $suffix;
		}
	}

	/**
	 * Test form canceling functionality.
	 */
	public function testFormApplication_Cancel() {
		// Select hostgroup and host, open a form.
		$this->zbxTestLogin('applications.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestDropdownSelectWait('hostid', 'Zabbix server');
		$this->zbxTestClickLinkTextWait(self::$application);

		// Close the form.
		$this->zbxTestClickXpathWait("//button[@id='cancel']");

		// Check the result in frontend.
		$this->zbxTestCheckTitle('Configuration of applications');
	}

	/**
	 * Test cloning of application.
	 */
	public function testFormApplication_Clone() {
		$suffix = ' (clone)';
		$name = self::$application;

		// Select hostgroup and host, open a form.
		$this->zbxTestLogin('applications.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestDropdownSelectWait('hostid', 'Zabbix server');
		$this->zbxTestClickLinkTextWait($name);

		// Clone the application, rename the clone and save it.
		$this->zbxTestClickXpathWait("//button[@id='clone' and @type='submit']");
		$this->zbxTestInputTypeOverwrite('appname', $name.$suffix);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");

		// Check the result in frontend.
		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application added');
		$this->zbxTestTextPresent($name.$suffix);
	}

	/**
	 * Test deleting of application.
	 */
	public function testFormApplication_Delete() {
		$name = self::$application;

		// Select hostgroup and host, open a form.
		$this->zbxTestLogin('applications.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
		$this->zbxTestDropdownSelectWait('hostid', 'Zabbix server');
		$this->zbxTestClickLinkTextWait($name);

		// Delete an application.
		$this->zbxTestClickAndAcceptAlert('delete');

		// Check the result in frontend.
		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application deleted');

		// Check the result in DB.
		$sql = "SELECT null FROM applications WHERE name='$name'";
		$this->assertEquals(0, DBcount($sql));
	}
}
