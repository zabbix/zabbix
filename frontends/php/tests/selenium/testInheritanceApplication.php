<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * @backup applications
 */
class testInheritanceApplication extends CLegacyWebTest {

	private $template = 'Inheritance test template';
	private $host = 'Template inheritance test host';

	/**
	 * Select a single value from DB.
	 *
	 * @param string $query	sql query
	 *
	 * @return mixed
	 */
	private function DBSelectValue($query) {
		return (($result = DBSelect($query)) && ($row = DBfetch($result)) && $row) ? reset($row) : null;
	}

	/**
	 * Select host or template to open applications page.
	 */
	private function openApplicationsPage($host) {
		$this->zbxTestLogin('applications.php?groupid=0&hostid=0');
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//select[@name="hostid"]'));
		$this->zbxTestDropdownSelectWait('hostid', $host);
		$this->zbxTestCheckHeader('Applications');
	}

	public function testInheritanceApplication_CheckLayout() {
		$this->openApplicationsPage($this->host);

		// Get application names linked to host.
		$applications = 'SELECT name FROM applications WHERE hostid IN ('.
					'SELECT hostid FROM hosts WHERE host='.zbx_dbstr($this->host).
				')'.
				' AND applicationid IN ('.
					'SELECT applicationid FROM application_template'.
				')';

		// Check inherited application name near template name.
		foreach (CDBHelper::getAll($applications) as $application) {
			$get_text = $this->zbxTestGetText('//table//td[text()=": '.$application['name'].'"]');
			$this->assertEquals($get_text, $this->template.': '.$application['name']);
		}
	}

	public static function getCreateData() {
		return [
			// Create a new application on template and check it on host.
			[
				[
					'template' => 'Inheritance test template',
					'host' => 'Template inheritance test host',
					'application' => 'NEW inheritance application'
				]
			],
			// Create a new application on template with the same name as on host, and check that appliance is inherited now.
			[
				[
					'template' => 'Inheritance test template',
					'host' => 'Template inheritance test host',
					'application' => 'Application on host'
				]
			],
			// Try to create application on host with the same name as inherited application name.
			[
				[
					'host' => 'Template inheritance test host',
					'application' => 'Inheritance application',
					'error' => 'Cannot add application',
					'error_datails' => 'Application "Inheritance application" already exists.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testInheritanceApplication_Create($data) {
		// Add application.
		$this->openApplicationsPage(CTestArrayHelper::get($data, 'template', $data['host']));
		$this->zbxTestContentControlButtonClickText('Create application');
		$this->zbxTestInputTypeWait('appname', $data['application']);
		$this->zbxTestClick('add');

		if (array_key_exists('template', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application added');

			// Check created application on host.
			$this->zbxTestDropdownSelectWait('hostid', $data['host']);
			$get_text = $this->zbxTestGetText('//table//td[text()=": '.$data['application'].'"]');
			$this->assertEquals($get_text, $data['template'].': '.$data['application']);

			// Check the results in DB.
			$host_application = $this->DBSelectValue('SELECT applicationid FROM applications'.
					' WHERE name='.zbx_dbstr($data['application']).' AND hostid IN ('.
						'SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['host']).
					')');
			$template_application = $this->DBSelectValue('SELECT applicationid FROM applications'.
					' WHERE name='.zbx_dbstr($data['application']).' AND hostid IN ('.
						'SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['template']).
					')');
			$linked_application = 'SELECT NULL FROM application_template WHERE applicationid='.zbx_dbstr($host_application).
					' AND templateid='.zbx_dbstr($template_application);
			$this->assertEquals(1, CDBHelper::getCount($linked_application));
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
			$this->zbxTestTextPresentInMessageDetails($data['error_datails']);

			// Check the results in DB.
			$host_application = 'SELECT NULL FROM applications WHERE name='.zbx_dbstr($data['application']).
					' AND hostid IN ('.
						'SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['host']).
					')';
			$this->assertEquals(1, CDBHelper::getCount($host_application));
		}
	}

	public function testInheritanceApplication_SimpleUpdate() {
		$sql_hash = 'SELECT * FROM applications ORDER BY applicationid';
		$old_hash = CDBHelper::getHash($sql_hash);

		// Get application names on template.
		$applications = 'SELECT name FROM applications WHERE hostid IN ('.
					'SELECT hostid FROM hosts WHERE host='.zbx_dbstr($this->template).
				')'.
				' AND applicationid IN ('.
					'SELECT templateid FROM application_template'.
				')';

		$this->openApplicationsPage($this->template);

		foreach (CDBHelper::getAll($applications) as $application) {
			// Update application on template.
			$this->zbxTestClickLinkTextWait($application['name']);
			$this->zbxTestClickWait('update');
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application updated');
		}

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function testInheritanceApplication_Update() {
		$application = 'Inheritance application for update';
		$new_name = 'UPDATED '.$application;
		$application_id = $this->DBSelectValue('SELECT applicationid FROM applications WHERE name='.zbx_dbstr($application).
				' AND hostid IN ('.
					'SELECT hostid FROM hosts WHERE host='.zbx_dbstr($this->template).
				')');

		// Open template page and update application name.
		$this->openApplicationsPage($this->template);
		$this->zbxTestClickLinkTextWait($application);
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::id('appname'));
		$this->zbxTestInputType('appname', $new_name);
		$this->zbxTestClickWait('update');

		// Check updated application name on template.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application updated');
		$get_template_application = $this->zbxTestGetText('//table//a[contains(@href, "applicationid='.$application_id.'")]');
		$this->assertEquals($new_name, $get_template_application);

		// Check updated application name on host.
		$this->zbxTestDropdownSelectWait('hostid', $this->host);
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//table//td[text()=": '.$new_name.'"]'));
		$get_host_application = $this->zbxTestGetText('//table//td[text()=": '.$new_name.'"]');
		$this->assertEquals($get_host_application, $this->template.': '.$new_name);

		// Check the results in DB.
		$hosts = ['templateid' => $this->template, 'applicationid' => $this->host];
		foreach ($hosts as $db_column => $host) {
			$sql = 'SELECT NULL FROM applications WHERE name='.zbx_dbstr($new_name).
					' AND hostid IN ('.
						'SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host).
					')'.
					' AND applicationid IN ('.
						'SELECT '.$db_column.' FROM application_template'.
					')';
			$this->assertEquals(1, CDBHelper::getCount($sql));
		}
	}

	public static function getDeleteData() {
		return [
			// Delete template application without items on host. Application deleted on template and host.
			[
				[
					'template' => 'Inheritance test template',
					'host' => 'Template inheritance test host',
					'application' => 'Inheritance application for delete without items'
				]
			],
			// Delete template application with items on host. Application deleted only on template.
			[
				[
					'template' => 'Inheritance test template',
					'host' => 'Template inheritance test host',
					'application' => 'Inheritance application for delete with items',
					'items' => true
				]
			],
			// Try to delete inherited application on host.
			[
				[
					'host' => 'Template inheritance test host',
					'application' => 'Inheritance application',
					'error' => 'Cannot delete application',
					'error_datails' => 'Cannot delete templated application.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testInheritanceApplication_Delete($data) {
		$this->openApplicationsPage(CTestArrayHelper::get($data, 'template', $data['host']));

		if (array_key_exists('template', $data)) {
			// Delete application.
			$this->zbxTestClickLinkTextWait($data['application']);
			$this->zbxTestClickAndAcceptAlert('delete');
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application deleted');
			$this->zbxTestAssertElementNotPresentXpath('//tbody//td[text()=": '.$data['application'].'"]');

			// Check application on host.
			$this->zbxTestDropdownSelectWait('hostid', $data['host']);
			$this->zbxTestWaitForPageToLoad();

			// Check the results in DB.
			$sql = 'SELECT NULL FROM applications WHERE name='.zbx_dbstr($data['application']);

			if (array_key_exists('items', $data)) {
				$this->zbxTestAssertElementPresentXpath('//tbody//a[text()="'.$data['application'].'"]');

				$this->assertEquals(1, CDBHelper::getCount($sql));
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath('//tbody//td[text()=": '.$data['application'].'"]');

				$this->assertEquals(0, CDBHelper::getCount($sql));
			}
		}
		else {
			$this->zbxTestClickXpath('//table//td[text()=": '.$data['application'].'"]/..//input');
			$this->zbxTestClickButtonText('Delete');
			$this->zbxTestAcceptAlert();
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
			$this->zbxTestTextPresentInMessageDetails($data['error_datails']);

			// Check the results in DB.
			$sql = 'SELECT NULL FROM applications WHERE name='.zbx_dbstr($data['application']);
			$this->assertEquals(2, CDBHelper::getCount($sql));
		}
	}
}
