<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup sessions
 */
class testFormSetup extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	public function testFormSetup_checkLayout() {
		$this->page->login()->open('setup.php')->waitUntilReady();

		// Check Welcome section
		$this->assertEquals("Welcome to\nZabbix 5.0", $this->query('xpath://div[@class="setup-title"]')->one()->getText());
		$this->checkSections('Welcome');

		$this->checkButtons('first section');
		$this->clickSectionButton('Next step');

		// Check Pre-requisites section
		$this->checkPageTextElements('Check of pre-requisites');
		$headers = $this->query('class:list-table')->asTable()->one()->getHeadersText();
		$this->assertEquals(['', 'Current value', 'Required', ''], $headers);

		$prerequisites = [
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
			'PHP gd GIF support',
			'PHP gd FreeType support',
			'PHP libxml',
			'PHP xmlwriter',
			'PHP xmlreader',
			'PHP LDAP',
			'PHP OpenSSL',
			'PHP ctype',
			'PHP session',
			'PHP option "session.auto_start"',
			'PHP gettext',
			'PHP option "arg_separator.output"'
		];
		$this->assertTableDataColumn($prerequisites, '');
		$this->checkSections('Check of pre-requesties');
		$this->checkButtons();
		$this->clickSectionButton('Next step');

		// Check Configure DB connection section
		$fields = [
			'Database host',
			'Database port',
			'Database name',
			'User',
			'Password'
		];
		$text = 'Please create database manually, and set the configuration parameters for connection to this database. '.
				'Press "Next step" button when done.';
		$this->checkPageTextElements('Configure DB connection', $text);
		$form = $this->query('xpath://form')->asForm()->one();

		// Check input fieldsin Configure DB connection section for each DB type
		$db_types = $form->getField('Database type')->getOptions()->asText();
		foreach ($db_types as $db_type) {
			$form->getField('Database type')->select($db_type);
			$form->invalidate();
			switch($db_type) {
				case 'Oracle':
					$this->assertFalse($form->query('xpath://label[text()="Database schema"]')->one(false)->isDisplayed());
					$this->assertFalse($form->query('xpath://label[text()="Database TLS encryption"]')->one(false)->isDisplayed());
					break;

				case 'MySQL':
					// Check that Database schema field is not available and that DB TLS encryption field contains text
					$this->assertFalse($form->query('xpath://label[text()="Database schema"]')->one(false)->isDisplayed());
					$tls_text = 'Connection will not be encrypted because it uses a socket file (on Unix) or shared '.
							'memory (Windows).';

					$this->assertEquals($tls_text, $form->query('id:tls_encryption_hint')->one()->getText());
					break;

				case 'PostgreSQL':
					// Check that Database Schema and Database TLS encryption fields are visible
					$schema_field = $form->getField('Database schema');
					$this->assertTrue($schema_field->isValid());
					$this->assertEquals($maxlength, $schema_field->getAttribute('maxlength'));
					$tls_encryption = $form->getField('Database TLS encryption');
					$this->assertTrue($tls_encryption->isChecked());

					// Check that Verify database certificate field is visible and set it.
					$verify_certificate = $form->query('xpath:.//label[@for="verify_certificate"]/span')->asCheckbox()->one();
					$this->assertTrue($verify_certificate->isDisplayed());
					$verify_certificate->check();

					$form->invalidate();
					$tls_fields = [
						'Database TLS CA file',
						'Database TLS key file',
						'Database TLS certificate file'
					];
					foreach ($tls_fields as $tls_field_name) {
						$tls_field = $form->getField($tls_field_name);
						$this->assertTrue($tls_field->isDisplayed());
						$this->assertEquals(255, $tls_field->getAttribute('maxlength'));
					}
					// Check that Database host verification field is displayed.
					$this->assertTrue($form->query('xpath:.//label[@for="verify_host"]/span')->one()->isDisplayed());
					// Uncheck the Database TLS encryption and verify that Verify database certificate field is hidden.
					$tls_encryption->uncheck();
					$this->assertFalse($verify_certificate->isDisplayed());
					break;
			}

			foreach ($fields as $field_name) {
				$maxlength = ($field_name === 'Database port') ? 5 : 255;
				$field = $form->getField($field_name);
				$this->assertTrue($field->isValid());
				$this->assertEquals($maxlength, $field->getAttribute('maxlength'));

			}
		}

		// Fill Configure DB connection section depending on DB type
		global $DB;
		$db_parameters = [
			'Database host' => $DB['SERVER'],
			'Database name' => $DB['DATABASE'],
			'User' => $DB['USER'],
			'Password' => $DB['PASSWORD']
		];
		$db_parameters['Database type'] = ($DB['TYPE'] === 'POSTGRESQL') ? 'PostgreSQL' : 'MySQL';
		$form->fill($db_parameters);

		$this->checkButtons();
		$this->clickSectionButton('Next step');

		// Check Zabbix server details
		$server_params = [
			'Host' => 'localhost',
			'Port' => '10051',
			'Name' => ''
		];
		$text = 'Please enter the host name or host IP address and port number of the Zabbix server, as well as the '.
				'name of the installation (optional).';
		$this->checkPageTextElements('Zabbix server details', $text);

		$form = $this->query('xpath://form')->asForm()->one();
		foreach ($server_params as $field_name => $value) {
			$maxlength = ($field_name === 'Port') ? 5 : 255;
			$field = $form->getField($field_name);
			$this->assertEquals($maxlength, $field->getAttribute('maxlength'));
			$this->assertEquals($value, $field->getValue());
		}

		$this->checkButtons();
		$this->clickSectionButton('Next step');

		// Check Pre-installaion summary
		$text = 'Please check configuration parameters. If all is correct, press "Next step" button, or "Back" button '.
				'to change configuration parameters.';
		$this->checkPageTextElements('Pre-installation summary', $text);

		$summary_fields = [
			'Database server' => $DB['SERVER'],
			'Database name' => $DB['DATABASE'],
			'Database user' => $DB['USER'],
			'Database password' => '********',
			'Zabbix server' => 'localhost',
			'Zabbix server port' => '10051',
			'Zabbix server name' => '',
			'Database TLS encryption' => 'false'
		];
		if ($DB['TYPE'] === 'POSTGRESQL') {
			$summary_fields['Database type'] = 'PostgreSQL';
			$summary_fields['Database schema'] = '';
		}
		else {
			$summary_fields['Database type'] = 'MySQL';
			$this->assertFalse($this->query('xpath://span[text()="Database schema"]')->one(false)->isValid());
		}
		$summary_fields['Database port'] = ($DB['PORT'] === '0') ? 'default' : $DB['PORT'];
		foreach ($summary_fields as $field_name => $value) {
			$xpath = 'xpath://span[text()="'.$field_name.'"]/../../div[@class="table-forms-td-right"]';
			$this->assertEquals($value, $this->query($xpath)->one()->getText());
		}
		$this->checkButtons();
		$this->clickSectionButton('Next step');

		// Check Install section
		$this->checkPageTextElements('Install', '/conf/zabbix.conf.php" created.');
		$this->assertEquals('Congratulations! You have successfully installed Zabbix frontend.',
				$this->query('class:green')->one()->getText());
		$this->checkButtons('last section');

		// Chek that Dashboard view is opened after completing the form
		$this->query('button:Finish')->one()->click();
		$this->page->waitUntilReady();
		$this->assertContains('zabbix.php?action=dashboard.view', $this->page->getCurrentURL());
	}

	public function getDbConnectionDetails() {
		return [
			// Incorrect DB host
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database host',
						'value'=> 'incorrect_DB_host'
					]
				]
			],
			// Partially non-numeric port number.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database port',
						'value' => '123aaa'
					],
					'check_port' => 123
				]
			],
			// Large port number.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database port',
						'value' => '99999'
					],
					'error_details' => 'Incorrect value "99999" for "Database port" field: must be between 0 and 65535.'
				]
			],
			// Incorrect DB name.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database name',
						'value' => 'Wrong database name'
					]
				]
			],
			// Incorrect DB schema for PostgreSQL.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database schema',
						'value' => 'incorrect schema'
					],
					'error_details' => 'Unable to determine current Zabbix database version: the table "dbversion" was not found.'
				]
			],
			// Incorrect user name.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'User',
						'value' => 'incorrect user name'
					]
				]
			],
			// Set incorrect password.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Password',
						'value' => 'this_password_is_incorrect'
					]
				]
			],
			// Empty "Database TLS CA file" field.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database TLS CA file',
						'value' => ''
					],
					'tls_encryption' => true,
					'error_details' => 'Incorrect file path for "Database TLS CA file": .'
				]
			],
			// Wrong "Database TLS CA file" field format.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database TLS CA file',
						'value' => '123456'
					],
					'tls_encryption' => true,
					'error_details' => 'Incorrect file path for "Database TLS CA file": 123456.'
				]
			],
			// Wrong "Database TLS CA file" path leads to wrong file.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database TLS CA file',
						'value' => '/etc/apache2/magic'
					],
					'tls_encryption' => true
				]
			],
			// Wrong "Database TLS key file" field format.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database TLS key file',
						'value' => '123'
					],
					'tls_encryption' => true,
					'fill_ca_file' => true,
					'error_details' => 'Incorrect file path for "Database TLS key file": 123.'
				]
			],
			// Wrong "Database TLS key file" path leads to wrong file.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database TLS key file',
						'value' => '/etc/apache2/magic'
					],
					'tls_encryption' => true,
					'fill_ca_file' => true
				]
			],
			// Wrong "Database TLS certificate file" field format.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database TLS certificate file',
						'value' => '123'
					],
					'tls_encryption' => true,
					'fill_ca_file' => true,
					'error_details' => 'Incorrect file path for "Database TLS certificate file": 123.'
				]
			],
			// Wrong "Database TLS certificate file" path leads to wrong file.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database TLS certificate file',
						'value' => '/etc/apache2/magic'
					],
					'tls_encryption' => true,
					'fill_ca_file' => true
				]
			],
			// With "Database TLS encryption" set.
			[
				[
					'field' => [
						'name' => 'Database TLS encryption',
						'value' => true
					]
				]
			],
			// Non-numeric port.
			[
				[
					'field' => [
						'name' => 'Database port',
						'value' => 'aaa1'
					],
					'check_port' => 0
				]
			],
			// Non-default port
			[
				[
					'field' => [
						'name' => 'Database port',
						'value' => 'should_be_changed'
					],
					'change_port' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getDbConnectionDetails
	 */
	public function testFormSetup_checkDbSection($data) {
		// Prepare array with DB parameter values
		global $DB;
		$db_parameters = [
			'Database host' => $DB['SERVER'],
			'Database name' => $DB['DATABASE'],
			'User' => $DB['USER'],
			'Password' => $DB['PASSWORD']
		];
		$db_parameters['Database type'] = ($DB['TYPE'] === 'POSTGRESQL') ? 'PostgreSQL' : 'MySQL';
		$db_parameters[$data['field']['name']] = $data['field']['value'];

		// Use default database port if specified in data provider
		if (array_key_exists('change_port', $data)) {
			$db_parameters['Database port'] = ($DB['TYPE'] === 'POSTGRESQL') ? 5432 : 3306;
		}

		// Skip the case with invalid DB schema if DB type is not PostgreSQL
		if (array_key_exists('Database schema', $data['field']) && $DB['TYPE'] !== 'POSTGRESQL') {

			return;
		}
		// Open "Configure DB connection" section
		$this->page->login()->open('setup.php')->waitUntilReady();
		$this->clickSectionButton('Next step', 2);
		$this->assertEquals('Configure DB connection', $this->query('xpath://h1')->one()->getText());

		// Fill Database connection parameters
		$form = $this->query('xpath://form')->asForm()->one();
		// Fill required TLS rellated field values
		if (array_key_exists('tls_encryption', $data) && $db_parameters['Database type'] === 'PostgreSQL') {
			$form->getField('Database type')->fill('PostgreSQL');
			$form->getField('Database TLS encryption')->check();
			$form->query('xpath:.//label[@for="verify_certificate"]/span')->asCheckbox()->one()->check();
			if (array_key_exists('fill_ca_file', $data)) {
				$form->getField('Database TLS CA file')->fill('/etc/apache2/magic');
			}
		}
		$form->fill($db_parameters);

		// Check that port number was trimmed after removing focus, starting with 1st non-numeric symbol.
		if ($data['field']['name'] === 'Database port') {
			$this->page->removeFocus();
		}
		if (array_key_exists('check_port', $data)) {
			$this->assertEquals($data['check_port'], $form->getField('Database port')->getValue());
		}

		// Check the outcome for the specified database configuration
		$this->clickSectionButton('Next step');
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$error_details = CTestArrayHelper::get($data, 'error_details', 'Error connecting to database.');
			$this->assertMessage(TEST_BAD, 'Cannot connect to the database.', $error_details);
		}
		else {
			$this->assertEquals('Zabbix server details', $this->query('xpath://h1')->one()->getText());
		}
	}

	public function testFormSetup_checkZabbixServerSection() {
		// Open Zabbix server configuration section.
		$this->openZabbixServerDetailsSection();

		$this->assertEquals('Zabbix server details', $this->query('xpath://h1')->one()->getText());
		$server_parameters = [
			'Host' => 'Zabbix_server_imaginary_host',
			'Port' => '65535',
			'Name' => 'Zabbix_server_imaginary_name'
		];
		$form = $this->query('xpath://form')->asForm()->one();

		// Check that port number was trimmed after removing focus and now is set to 0.
		$form->getField('Port')->fill('a999');
		$this->page->removeFocus();
		$this->assertEquals(0, $form->getField('Port')->getValue());

		// Check that port number higher than 65535 is not accepted.
		// Uncomment the below section once ZBX-18627 is merged.
//		$form->getField('Port')->fill('65536');
//		$this->clickSectionButton('Next step');
//		$this->assertMessage(TEST_BAD, 'Cannot connect to the database.', 'Incorrect value "99999" for "Database port" '.
//				'field: must be between 0 and 65535.');

		$form->fill($server_parameters);
		$this->clickSectionButton('Next step');

		// Check that the vields are filled correctly in the Pre-installation summary section
		$summary_fields = [
			'Zabbix server' => $server_parameters['Host'],
			'Zabbix server port' => $server_parameters['Port'],
			'Zabbix server name' => $server_parameters['Name']
		];

		foreach ($summary_fields as $field_name => $value) {
			$xpath = 'xpath://span[text()="'.$field_name.'"]/../../div[@class="table-forms-td-right"]';
			$this->assertEquals($value, $this->query($xpath)->one()->getText());
		}
		$this->clickSectionButton('Next step');

		// Need to wait for 3s for php cache to reload and for zabbix server parameter the changes to take place
		sleep(3);
		$this->clickSectionButton('Finish');

		// Check Zabbix server params
		$this->assertEquals($server_parameters['Name'].': Dashboard', $this->page->getTitle());
		$system_info = CDashboardElement::find()->one()->getWidget('System information')->getContent();
		$this->assertContains($server_parameters['Host'].':'.$server_parameters['Port'], $system_info->getText());
	}

	public function testFormSetup_checkBackButtons() {
		// Open the Pre-installation summary section
		$this->openZabbixServerDetailsSection();
		$this->clickSectionButton('Next step');
		$this->assertEquals('Pre-installation summary', $this->query('xpath://h1')->one()->getText());

		// Proceed back to the 1st section of the setup form
		$this->clickSectionButton('Back');
		$this->assertEquals('Zabbix server details', $this->query('xpath://h1')->one()->getText());
		$this->clickSectionButton('Back');
		$this->assertEquals('Configure DB connection', $this->query('xpath://h1')->one()->getText());
		$this->clickSectionButton('Back');
		$this->assertEquals('Check of pre-requisites', $this->query('xpath://h1')->one()->getText());
		$this->clickSectionButton('Back');
		$this->assertEquals("Welcome to\nZabbix 5.0", $this->query('xpath://div[@class="setup-title"]')->one()->getText());
		$this->checkSections('Welcome');
		$this->checkButtons('first section');

		// Cancel setup form update
		$this->clickSectionButton('Cancel');
		$this->assertContains('zabbix.php?action=dashboard.view', $this->page->getCurrentURL());
	}

	public function testFormSetup_restoreServerConfig() {
		// Open the last section of the setup form
		$this->openZabbixServerDetailsSection();
		$this->clickSectionButton('Next step', 2);

		// Need to wait for 3s for php cache to reload and for zabbix server parameter the changes to take place
		sleep(3);
		$this->clickSectionButton('Finish');
	}

	/**
	 * Function checks the title of the current section, section navigation column and presence of text if defined.
	 *
	 * @param	string	$title		title of the current setup form section
	 * @param	string	$text		text that should be present in a paragraph of the current setup form section
	 */
	private function checkPageTextElements($title, $text = null) {
		$this->assertTrue($this->query('xpath://h1[text()="'.$title.'"]')->one()->isValid());
		$this->checkSections($title);
		if ($text) {
			$this->assertContains($text, $this->query('xpath:.//p')->one()->getText());
		}
	}

	/**
	 * Function checks if the buttons on the currently opened setup form section are clickable.
	 *
	 * @param	string	$section	position of current section in the form (first, last, middle)
	 */
	private function checkButtons($section = 'middle section') {
		switch($section) {
			case 'first section':
				$buttons = [
					'Cancel' => true,
					'Back' => false,
					'Next step' => true
				];
				break;

			case 'last section':
				$buttons = [
					'Cancel' => false,
					'Back' => false,
					'Finish' => true
				];
				break;

			case 'middle section':
				$buttons = [
					'Cancel' => true,
					'Back' => true,
					'Next step' => true
				];
				break;
		}
		foreach ($buttons as $button => $clickable) {
			$this->assertEquals($clickable, $this->query('button', $button)->one()->isCLickable());
		}
	}

	/**
	 * Function checks that all sections are present in the section navigation column, and that the current (or all
	 * section) are grayed out.
	 *
	 * @param	string	$current	title of the current setup form section.
	 */
	private function checkSections($current) {
		$sections = [
			'Welcome',
			'Check of pre-requisites',
			'Configure DB connection',
			'Zabbix server details',
			'Pre-installation summary',
			'Install'
		];

		foreach ($sections as $section_name) {
			$section = $this->query('xpath://li[text()="'.$section_name.'"]')->one();
			$this->assertTrue($section->isValid());
			// It is required to check that all sections are grayed out because Install is the last step
			if ($section_name === $current || $current === 'Install') {
				$this->assertEquals('setup-left-current', $section->getAttribute('class'));
			}
		}
	}

	/**
	 * Function clicks on the specified button specified number of times (in different sections).
	 *
	 * @param	string	$button		tite of the button that should be clicked
	 * @param	integer	$times		number of times that the specified button should be clicked
	 */
	private function clickSectionButton($button, $times = 1) {
		for ($i = 0; $i < $times; $i++) {
			$this->query('button', $button)->one()->click();
		}
	}

	/**
	 * Function opens the setup form and navigates to the "Zabbix server details" section.
	 */
	private function openZabbixServerDetailsSection() {
		global $DB;
		$db_parameters = [
			'Database host' => $DB['SERVER'],
			'Database name' => $DB['DATABASE'],
			'User' => $DB['USER'],
			'Password' => $DB['PASSWORD']
		];
		$db_parameters['Database type'] = ($DB['TYPE'] === 'POSTGRESQL') ? 'PostgreSQL' : 'MySQL';

		// Open "Configure DB connection" section
		$this->page->login()->open('setup.php')->waitUntilReady();
		$this->clickSectionButton('Next step', 2);
		$form = $this->query('xpath://form')->asForm()->one();
		$form->fill($db_parameters);
		$this->clickSectionButton('Next step');
	}
}
