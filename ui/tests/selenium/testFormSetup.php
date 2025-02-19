<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/behaviors/CTableBehavior.php';

/**
 * @backup sessions
 *
 * @backupConfig
 */
class testFormSetup extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	/**
	 * @backup config
	 */
	public function testFormSetup_welcomeSectionLayout() {
		$this->page->login()->open('setup.php')->waitUntilReady();

		// Check Welcome section.
		$this->assertEquals("Welcome to\nZabbix ".ZABBIX_EXPORT_VERSION, $this->query('xpath://div[@class="setup-title"]')->one()->getText());
		$this->checkSections('Welcome');
		$form = $this->query('xpath://form')->asForm()->one();
		$language_field = $form->getField('Default language');
		$this->assertEquals('English (en_GB)', $language_field->getValue());
		$hint_text = 'You are not able to choose some of the languages, because locales for them are not installed '.
				'on the web server.';
		$this->assertEquals($hint_text, $this->query('xpath://button[@data-hintbox]')->one()
				->getAttribute('data-hintbox-contents'));
		$this->checkButtons('first section');

		$this->assertScreenshot($form, 'Welcome_En');

		// Check that default language can be changed.
		$language_field->fill('Russian (ru_RU)');
		$this->page->refresh()->waitUntilReady();
		$this->assertEquals("Добро пожаловать в\nZabbix ".ZABBIX_EXPORT_VERSION, $this->query('xpath://div[@class="setup-title"]')->one()->getText());

		$this->checkButtons('russian');
		$this->assertScreenshotExcept($form, $this->query('id:default-lang')->one(), 'Welcome_Rus');
	}

// TODO: Commented until Jenkins issue investigated.
//	public function testFormSetup_prerequisitesSectionLayout() {
//		$this->page->login()->open('setup.php')->waitUntilReady();
//		$this->query('button:Next step')->one()->click();
//
//		// Check Pre-requisites section.
//		$this->checkPageTextElements('Check of pre-requisites');
//		$headers = $this->query('class:list-table')->asTable()->one()->getHeadersText();
//		$this->assertEquals(['', 'Current value', 'Required', ''], $headers);
//
//		$prerequisites = [
//			'PHP version',
//			'PHP option "memory_limit"',
//			'PHP option "post_max_size"',
//			'PHP option "upload_max_filesize"',
//			'PHP option "max_execution_time"',
//			'PHP option "max_input_time"',
//			'PHP databases support',
//			'PHP bcmath',
//			'PHP mbstring',
//			'PHP option "mbstring.func_overload"',
//			'PHP sockets',
//			'PHP gd',
//			'PHP gd PNG support',
//			'PHP gd JPEG support',
//			'PHP gd GIF support',
//			'PHP gd FreeType support',
//			'PHP libxml',
//			'PHP xmlwriter',
//			'PHP xmlreader',
//			'PHP LDAP',
//			'PHP OpenSSL',
//			'PHP ctype',
//			'PHP session',
//			'PHP option "session.auto_start"',
//			'PHP gettext',
//			'PHP option "arg_separator.output"',
//			'PHP curl',
//			'System locale'
//		];
//		$this->assertTableDataColumn($prerequisites, '');
//		$this->checkSections('Check of pre-requesties');
//		$this->checkButtons();
//
//		global $DB;
//		$php_version = $this->query('xpath://td[text()="PHP version"]/following-sibling::td')->one();
//		$this->assertScreenshotExcept($this->query('xpath://form')->one(), $php_version, 'Prerequisites_'.$DB['TYPE']);
//	}

	public function testFormSetup_dbConnectionSectionLayout() {
		$this->openSpecifiedSection('Configure DB connection');
		$db_parameters = $this->getDbParameters();

		// Check Configure DB connection section.
		$fields = [
			'Database port' => '0',
			'Database name' => 'zabbix',
			'User' => 'zabbix',
			'Password' => ''
		];
		$fields['Database host'] = ($db_parameters['Database type'] === 'PostgreSQL') ?
				'localhost' : $db_parameters['Database host'];
		$text = 'Please create database manually, and set the configuration parameters for connection to this database. '.
				'Press "Next step" button when done.';
		$this->checkPageTextElements('Configure DB connection', $text);
		$form = $this->query('xpath://form')->asForm()->one();

		// Check input fields in Configure DB connection section for each DB type.
		$db_types = $form->getField('Database type')->getOptions()->asText();
		foreach ($db_types as $db_type) {
			$form->getField('Database type')->select($db_type);
			$form->invalidate();
			switch ($db_type) {
				case 'Oracle':
					$this->assertFalse($form->query('xpath://label[text()="Database schema"]')->one(false)->isDisplayed());
					$this->assertFalse($form->query('xpath://label[text()="Database TLS encryption"]')->one(false)->isDisplayed());
					break;

				case 'MySQL':
					// Check that Database schema field is not available.
					$this->assertFalse($form->query('xpath://label[text()="Database schema"]')->one(false)->isDisplayed());
					// Check TLS fields if such should be displayed.
					if ($db_parameters['Database host'] === 'localhost') {
						$tls_text = 'Connection will not be encrypted because it uses a socket file (on Unix) or shared '.
								'memory (Windows).';
						$this->assertEquals($tls_text, $form->query('id:tls_encryption_hint')->one()->getText());
					}
					else {
						$form->getField('Database host')->fill($db_parameters['Database host']);
						$this->page->removeFocus();
						$this->checkTlsFieldsLayout();
					}
					break;

				case 'PostgreSQL':
					// Check that Database Schema and Database TLS encryption fields are visible.
					$schema_field = $form->getField('Database schema');
					$this->assertEquals(255, $schema_field->getAttribute('maxlength'));
					$this->checkTlsFieldsLayout();
					break;
			}

			foreach ($fields as $field_name => $field_value) {
				$maxlength = ($field_name === 'Database port') ? 5 : 255;
				$field = $form->getField($field_name);
				$this->assertEquals($field_value, $field->getValue());
				$this->assertEquals($maxlength, $field->getAttribute('maxlength'));

			}
			// Array of fields to be skipped by the screenshot check.
			$skip_db_fields = [];
			foreach(['Database host', 'Database name', 'Store credentials in'] as $skip_field) {
				$skip_db_fields[] = $form->getField($skip_field);
			}
			// Check screenshot for "Store credentials in" = Plain text.
			$this->assertScreenshotExcept($form, $skip_db_fields, 'ConfigureDB_plainText_'.$db_type);

			// Check 'Store credentials in' field, switch to Vault and check Vault rellated fields.
			$credentials_field = $form->getField('Store credentials in');
			$this->assertEquals('Plain text', $credentials_field->getSelected());

			// All vault fields labels.
			$labels = [
				'Vault API endpoint',
				'Vault secret path',
				'Vault authentication token',
				'Vault secret query string',
				'Vault certificates'
			];
			foreach ($labels as $label_name) {
				$this->assertFalse($form->getField($label_name)->isVisible());
			}

			// HashiCorp and Cyberark vault fields.
			$vaults = [
				'HashiCorp Vault' => [
					'Vault API endpoint' => 'https://localhost:8200',
					'Vault prefix' => '/v1/secret/data/',
					'Vault secret path' => 'path/to/secret',
					'Vault authentication token' => ''
				],
				'CyberArk Vault' => [
					'Vault API endpoint' => 'https://localhost:1858',
					'Vault prefix' => '/AIMWebService/api/Accounts?',
					'Vault secret query string' => 'AppID=foo&Query=Safe=bar;Object=buzz',
					'SSL certificate file' => 'conf/certs/cyberark-cert.pem',
					'SSL key file' => 'conf/certs/cyberark-key.pem'
				]
			];

			// Check layout when "Store credentials in" is set to "HashiCorp Vault" or "CyberArk Vault".
			foreach ($vaults as $vault => $vault_fields) {
				$credentials_field->select($vault);
				$form->invalidate();

				if ($vault === 'CyberArk Vault') {
					$form->fill(['Vault certificates' => true]);
				}

				foreach (['User', 'Password'] as $parameter) {
					$this->assertFalse($form->getField($parameter)->isVisible());
				}

				foreach ($vault_fields as $field_name => $parameter) {
					$vault_maxlength = ($field_name === 'Vault API endpoint' || $field_name === 'Vault secret path') ? 255 : 2048;
					$field = $form->getField($field_name);
					$this->assertEquals($vault_maxlength, $field->getAttribute('maxlength'));
					if (in_array($field_name, ['Vault secret query string', 'Vault secret path', 'Vault prefix'])) {
						$this->assertEquals($parameter, $field->getAttribute('placeholder'));
					}
					else {
						$this->assertEquals($parameter, $field->getValue());
					}
				}

				// Array of fields to be skipped by the screenshot check.
				$skip_fields_vault = [];

				foreach(['Database host', 'Database name', 'Store credentials in'] as $skip_field) {
					$skip_fields_vault[] = $form->getField($skip_field);
				}

				// Check screenshot for "Store credentials in" = Vault.
				$this->assertScreenshotExcept($form, $skip_fields_vault, 'ConfigureDB_Vault_'.$db_type.$vault);
			}

			$credentials_field->select('Plain text');
		}
	}

	/**
	 * @backup config
	 */
	public function testFormSetup_settingsSection() {
		// Open the Pre-installation summary section.
		$this->openSpecifiedSection('Settings');
		// Check GUI settings section.
		$this->checkPageTextElements('Settings');
		$this->checkButtons();
		$form = $this->query('xpath://form')->asForm()->one();
		// Check layout via screenshot for default theme.
		$this->assertScreenshotExcept($form, $this->query('id:label-default-timezone')->one(), 'GUISettings_Default');

		// Check Zabbix server name field.
		$server_name = $form->getField('Zabbix server name');
		$this->assertEquals(255, $server_name->getAttribute('maxlength'));
		$this->assertEquals('', $server_name->getValue());

		// Check timezone field.
		$timezones_field = $form->getField('Default time zone');
		$timezones = $timezones_field->getOptions()->asText();

		// Note that count of available timezones may differ based on the local environment configuration and php version.
		$this->assertGreaterThan(415, count($timezones));
		$this->assertContains(CDateTimeHelper::getTimeZoneFormat('Europe/Riga'), $timezones);

		foreach (['System', 'Europe/Riga'] as $timezone_value) {
			$timezone = CDateTimeHelper::getTimeZoneFormat($timezone_value);
			$this->assertContains($timezone, $timezones);
		}
		// Select a certain timezone.
		$form->getField('Default time zone')->select(CDateTimeHelper::getTimeZoneFormat('Europe/Riga'));

		// Check Default theme field.
		$themes = $form->getField('Default theme');
		$this->assertEquals(['Blue', 'Dark', 'High-contrast light', 'High-contrast dark'], $themes->getOptions()->asText());
		// Select Dark theme.
		$form->getField('Default theme')->select('Dark');

		// Check that default theme has changed.
		$stylesheet = $this->query('xpath://link[@rel="stylesheet"]')->one();
		$parts = explode('/', $stylesheet->getAttribute('href'));
		$this->assertContains('dark-theme.css', explode('?', end($parts)));
		// Check layout via screenshot for dark theme.
		$this->assertScreenshotExcept($form, $this->query('id:label-default-timezone')->one(), 'GUISettings_Dark');

		// Complete the setup and check in DB that the default timezone was applied.
		$this->query('button:Next step')->one()->click();
		$this->query('button:Next step')->one()->click();
		$this->query('button:Finish')->one()->click();
		$db_values = CDBHelper::getRow('SELECT default_theme, default_timezone FROM config');
		$this->assertEquals(['dark-theme', 'Europe/Riga'], array_values($db_values));
	}

	public function testFormSetup_summarySection() {
		$this->openSpecifiedSection('Pre-installation summary');

		// Check that Zabbix server name field is not displayed if it is not populated.
		$this->assertFalse($this->query('xpath://span[text()="Zabbix server name"]')->one(false)->isValid());
		$this->query('button:Back')->one()->click();
		// Fill in the Zabbix server name field and proceed with checking Pre-installation summary.
		$this->query('id:setup-form')->asForm()->one()->getField('Zabbix server name')->fill('Zabbix server name');
		$this->query('button:Next step')->one()->click();
		$db_parameters = $this->getDbParameters();
		$text = 'Please check configuration parameters. If all is correct, press "Next step" button, or "Back" button '.
				'to change configuration parameters.';
		$this->checkPageTextElements('Pre-installation summary', $text);

		$summary_fields = [
			'Database server' => $db_parameters['Database host'],
			'Database name' => $db_parameters['Database name'],
			'Database user' => $db_parameters['User'],
			'Database password' => '******',
			'Zabbix server name' => 'Zabbix server name'
		];

		if ($db_parameters['Database type'] === 'PostgreSQL') {
			$summary_fields['Database type'] = 'PostgreSQL';
			$summary_fields['Database schema'] = '';
			$summary_fields['Database TLS encryption'] = 'true';
		}
		else {
			$summary_fields['Database type'] = 'MySQL';
			$this->assertFalse($this->query('xpath://span[text()="Database schema"]')->one(false)->isValid());
			$summary_fields['Database TLS encryption'] = ($db_parameters['Database host'] === 'localhost') ? 'false' : 'true';
		}
		$summary_fields['Database port'] = ($db_parameters['Database port'] === '0') ? 'default' : $db_parameters['Database port'];
		foreach ($summary_fields as $field_name => $value) {
			$xpath = 'xpath://span[text()='.CXPathHelper::escapeQuotes($field_name).']/../../div[@class="table-forms-td-right"]';
			// Assert contains is used as Password length can differ.
			if ($field_name === 'Database password') {
				$this->assertStringContainsString($value, $this->query($xpath)->one()->getText());
			}
			else {
				$this->assertEquals($value, $this->query($xpath)->one()->getText());
			}
		}
		$this->checkButtons();

		// Check screenshot of the Pre-installation summary section.
		$skip_fields = [];
		foreach(['Database server', 'Database port', 'Database name'] as $skip_field) {
			$xpath = 'xpath://span[text()='.CXPathHelper::escapeQuotes($skip_field).']/../../div[@class="table-forms-td-right"]';
			$skip_fields[] = $this->query($xpath)->one();
		}
		$this->assertScreenshotExcept($this->query('xpath://form')->one(), $skip_fields, 'PreInstall_'.$db_parameters['Database type']);
	}

	public function testFormSetup_installSection() {
		$this->openSpecifiedSection('Install');
		$this->checkPageTextElements('Install', 'Configuration file "conf/zabbix.conf.php" created.');
		$this->assertEquals('Congratulations! You have successfully installed Zabbix frontend.',
				$this->query('class:green')->one()->getText());
		$this->checkButtons('last section');
		$this->assertScreenshotExcept($this->query('xpath://form')->one(), $this->query('xpath://p')->one(), 'Install');

		// Check that Dashboard view is opened after completing the form.
		$this->query('button:Finish')->one()->click();
		$this->page->waitUntilReady();
		$this->assertStringContainsString('index.php', $this->page->getCurrentURL());
	}

	public function getDbConnectionDetails() {
		$provider = [
			// Incorrect DB host.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Database host',
						'value'=> 'incorrect_DB_host'
					],
					'mysql_error' => 'php_network_getaddresses: getaddrinfo failed: Name or service not known'
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
					'check_port' => 123,
					'mysql_error' => 'Connection refused'
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
					],
					'mysql_error' => "Unknown database 'Wrong database name'"
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
					],
					'mysql_error' => 'Access denied for user'
				]
			],
			// Set incorrect password.
			[
				[
					'expected' => TEST_BAD,
					'field' => [
						'name' => 'Password',
						'value' => 'this_password_is_incorrect'
					],
					'mysql_error' => 'Access denied for user'
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
					'tls_encryption' => true,
					'mysql_error' => 'Error connecting to database. Empty cipher.'
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
					'fill_ca_file' => true,
					'mysql_error' => 'Error connecting to database. Empty cipher.'
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
					'fill_ca_file' => true,
					'mysql_error' => 'Error connecting to database. Empty cipher.'
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
			// Non-default port.
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

		// MySQL database error depends on php version.
		$mapping = [
			'Error connecting to database. Empty cipher.' => [
				'8.1.0' => 'Cannot connect to MySQL using SSL'
			],
			'php_network_getaddresses: getaddrinfo failed: Name or service not known' => [
				'8.1.0' => 'php_network_getaddresses: getaddrinfo for incorrect_DB_host failed: Name or service not known'
			]
		];

		foreach ($provider as &$data) {
			if (array_key_exists('mysql_error', $data[0]) && array_key_exists($data[0]['mysql_error'], $mapping)) {
				foreach ($mapping[$data[0]['mysql_error']] as $version => $map) {
					if (version_compare(phpversion(), $version, '<')) {
						continue;
					}

					$data[0]['mysql_error'] = $map;
				}
			}
		}
		unset($data);

		return $provider;
	}

	/**
	 * @dataProvider getDbConnectionDetails
	 */
	public function testFormSetup_dbConfigSectionParameters($data) {
		// Prepare array with DB parameter values.
		$db_parameters = $this->getDbParameters();
		$db_parameters[$data['field']['name']] = $data['field']['value'];

		// Use default database port if specified in data provider.
		if (array_key_exists('change_port', $data)) {
			$db_parameters['Database port'] = ($db_parameters['Database type'] === 'PostgreSQL') ? 5432 : 3306;
		}

		// Skip the case with invalid DB schema if DB type is MySQL.
		if ($data['field']['name'] === 'Database schema' && $db_parameters['Database type'] === 'MySQL') {

			return;
		}
		// Open "Configure DB connection" section.
		$this->openSpecifiedSection('Configure DB connection');

		// Fill Database connection parameters.
		$form = $this->query('xpath://form')->asForm()->one();
		// Fill required TLS rellated field values.
		if (array_key_exists('tls_encryption', $data)) {
			// TLS fields are not present in case if DB type = MySQL and for DB host = localhost.
			if (($db_parameters['Database type'] === 'MySQL' && $db_parameters['Database host'] === 'localhost')) {
				$tls_text = 'Connection will not be encrypted because it uses a socket file (on Unix) or shared memory (Windows).';
				$this->assertEquals($tls_text, $form->query('id:tls_encryption_hint')->one()->getText());
				// Skip data provider as TLS encryption fields are not visible.

				return;
			}
			else {
				$form->getField('Database type')->fill($db_parameters['Database type']);
				$form->getField('Database host')->fill($db_parameters['Database host']);
				$this->page->removeFocus();
				$form->getField('Database TLS encryption')->check();
				$form->query('xpath:.//label[@for="verify_certificate"]/span')->asCheckbox()->one()->check();
				if (array_key_exists('fill_ca_file', $data)) {
					$form->getField('Database TLS CA file')->fill('/etc/apache2/magic');
				}
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

		// Check the outcome for the specified database configuration.
		$this->query('button:Next step')->one()->click();
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			// Define the reference error message details and assert error message.
			if (array_key_exists('error_details', $data)) {
				$error_details = $data['error_details'];
			}
			else {
				$error_details = ($db_parameters['Database type'] === 'MySQL') ? $data['mysql_error'] :
					'Error connecting to database.';
			}
			$this->assertMessage(TEST_BAD, 'Cannot connect to the database.', $error_details);
		}
		else {
			$this->assertEquals('Settings', $this->query('xpath://h1')->one()->getText());
		}
	}

	public function getDbConnectionDetailsForTls() {
		return [
			// TLS available when IP address is used as host name - MySQL.
			[
				[
					'fields' => [
						'Database type' => 'MySQL',
						'Database host'=> '127.0.0.1'
					],
					'tls_displayed' => true
				]
			],
			// TLS available when string is used as host name - MySQL.
			[
				[
					'fields' => [
						'Database type' => 'MySQL',
						'Database host'=> 'abc'
					],
					'tls_displayed' => true
				]
			],
			// TLS available when empty space is used as host name - MySQL.
			[
				[
					'fields' => [
						'Database type' => 'MySQL',
						'Database host'=> ' '
					],
					'tls_displayed' => true
				]
			],
			// TLS NOT available when host name is empty - MySQL.
			[
				[
					'fields' => [
						'Database type' => 'MySQL',
						'Database host'=> ''
					]
				]
			],
			// TLS NOT available when host name is equal to "localhost" - MySQL.
			[
				[
					'fields' => [
						'Database type' => 'MySQL',
						'Database host'=> 'localhost'
					]
				]
			],
			// TLS is available when host name starts with a slash - MySQL.
			[
				[
					'fields' => [
						'Database type' => 'MySQL',
						'Database host'=> '/123'
					],
					'tls_displayed' => true
				]
			],
			// TLS available when IP address is used as host name - PostgreSQL.
			[
				[
					'fields' => [
						'Database type' => 'PostgreSQL',
						'Database host'=> '127.0.0.1'
					],
					'tls_displayed' => true
				]
			],
			// TLS available when string is used as host name - PostgreSQL.
			[
				[
					'fields' => [
						'Database type' => 'PostgreSQL',
						'Database host'=> 'abc'
					],
					'tls_displayed' => true
				]
			],
			// TLS available when empty space is used as host name - PostgreSQL.
			[
				[
					'fields' => [
						'Database type' => 'PostgreSQL',
						'Database host'=> ' '
					],
					'tls_displayed' => true
				]
			],
			// TLS NOT available when host name is empty - PostgreSQL.
			[
				[
					'fields' => [
						'Database type' => 'PostgreSQL',
						'Database host'=> ''
					]
				]
			],
			// TLS is available when host name is equal to "localhost" - PostgreSQL.
			[
				[
					'fields' => [
						'Database type' => 'PostgreSQL',
						'Database host'=> 'localhost'
					],
					'tls_displayed' => true
				]
			],
			// TLS NOT available when host name starts with a slash - PostgreSQL.
			[
				[
					'fields' => [
						'Database type' => 'PostgreSQL',
						'Database host'=> '/123'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getDbConnectionDetailsForTls
	 */
	public function testFormSetup_tlsParameterPresence($data) {
		// Open "Configure DB connection" section.
		$this->openSpecifiedSection('Configure DB connection');
		$form = $this->query('xpath://form')->asForm()->one();
		$database_types = $form->getField('Database type')->getOptions()->asText();

		// Skip data provider if the defined DB type is not available on the current machine.
		if (!in_array($data['fields']['Database type'], $database_types)) {

			return;
		}
		// Fill DB parameters and check if TLS parameters are displayed.
		$form->fill($data['fields']);
		$form->invalidate();
		$this->page->removeFocus();
		if (CTestArrayHelper::get($data, 'tls_displayed', false)) {
			$form->getField('Database TLS encryption')->check();
			$form->query('xpath:.//label[@for="verify_certificate"]/span')->asCheckbox()->one()->check();
			$tls_fields = [
				'Database TLS CA file',
				'Database TLS key file',
				'Database TLS certificate file'
			];
			foreach ($tls_fields as $tls_field) {
				$this->assertTrue($form->getField($tls_field)->isDisplayed());
			}
			$verify_host_field = $form->query('id:verify_host')->asCheckbox()->one();
			if ($data['fields']['Database type'] === 'MySQL') {
				$this->assertTrue($form->getField('Database TLS cipher list')->isDisplayed());
				$this->assertFalse($verify_host_field->isEnabled());
			}
			else {
				$this->assertFalse($this->query('xpath://span[text()="Database TLS cipher list"]')->one(false)->isValid());
				$this->assertTrue($verify_host_field->isEnabled());
			}
		}
		else {
			$tls_text = 'Connection will not be encrypted because it uses a socket file (on Unix) or shared memory (Windows).';
			$this->assertEquals($tls_text, $form->query('id:tls_encryption_hint')->one()->getText());
		}
	}

	public function testFormSetup_backButtons() {
		// Open the Pre-installation summary section.
		$this->openSpecifiedSection('Pre-installation summary');

		// Proceed back to the 1st section of the setup form.
		$this->query('button:Back')->one()->click();
		$this->assertEquals('Settings', $this->query('xpath://h1')->one()->getText());
		$this->query('button:Back')->one()->click();
		$this->assertEquals('Configure DB connection', $this->query('xpath://h1')->one()->getText());
		$this->query('button:Back')->one()->click();
		$this->assertEquals('Check of pre-requisites', $this->query('xpath://h1')->one()->getText());
		$this->query('button:Back')->one()->click();
		$this->assertEquals("Welcome to\nZabbix ".ZABBIX_EXPORT_VERSION, $this->query('xpath://div[@class="setup-title"]')->one()->getText());
		$this->checkSections('Welcome');
		$this->checkButtons('first section');

		// Cancel setup form update.
		$this->query('button:Cancel')->one()->click();
		$this->assertStringContainsString('zabbix.php?action=dashboard.view', $this->page->getCurrentURL());
	}

	/**
	 * Function checks the title of the current section, section navigation column and presence of text if defined.
	 *
	 * @param	string	$title		title of the current setup form section
	 * @param	string	$text		text that should be present in a paragraph of the current setup form section
	 */
	private function checkPageTextElements($title, $text = null) {
		$this->assertTrue($this->query('xpath://h1[text()='.CXPathHelper::escapeQuotes($title).']')->one()->isValid());
		$this->checkSections($title);
		if ($text) {
			$this->assertStringContainsString($text, $this->query('xpath:.//p')->one()->getText());
		}
	}

	/**
	 * Function checks if the buttons on the currently opened setup form section are clickable.
	 *
	 * @param	string	$section	position of current section in the form (first, last, middle)
	 */
	private function checkButtons($section = 'middle section') {
		switch ($section) {
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

			case 'russian':
				$buttons = [
					'Отмена' => true,
					'Назад' => false,
					'Далее' => true
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
			'Settings',
			'Pre-installation summary',
			'Install'
		];

		foreach ($sections as $section_name) {
			$section = $this->query('xpath://li[text()='.CXPathHelper::escapeQuotes($section_name).']')->one();
			$this->assertTrue($section->isValid());
			// It is required to check that all sections are grayed out because Install is the last step.
			if ($section_name === $current || $current === 'Install') {
				$this->assertEquals('setup-left-current', $section->getAttribute('class'));
			}
		}
	}

	/**
	 * Function opens the setup form and navigates to the specified section.
	 *
	 * @param	string	$section	the name of the section to be opened
	 */
	private function openSpecifiedSection($section) {
		$this->page->login()->open('setup.php')->waitUntilReady();
		$this->query('button:Next step')->one()->click();
		$this->query('button:Next step')->one()->click();
		// No actions required in case of Configure DB connection section.
		if ($section === 'Configure DB connection') {
			return;
		}
		// Define the number of clicks on the Next step button depending on the name of the desired section.
		$skip_sections = [
			'Settings' => 1,
			'Pre-installation summary' => 2,
			'Install' => 3
		];
		// Fill in DB parameters and navigate to the desired section.
		$db_parameters = $this->getDbParameters();
		$form = $this->query('xpath://form')->asForm()->one();
		$form->fill($db_parameters);

		for ($i = 0; $i < $skip_sections[$section]; $i++) {
			$this->query('button:Next step')->one()->click();
		}
	}

	/**
	 * Function retrieves the values to be filled in the Configure DB connection section.
	 *
	 * @return	array
	 */
	private function getDbParameters() {
		global $DB;
		$db_parameters = [
			'Database host' => $DB['SERVER'],
			'Database name' => $DB['DATABASE'],
			'Database port' => $DB['PORT'],
			'User' => $DB['USER'],
			'Password' => $DB['PASSWORD']
		];
		$db_parameters['Database type'] = ($DB['TYPE'] === ZBX_DB_POSTGRESQL) ? 'PostgreSQL' : 'MySQL';

		return $db_parameters;
	}

	/**
	 * Function checks the layout of the TLS encryption fields
	 */
	private function checkTlsFieldsLayout() {
		$form = $this->query('xpath://form')->asForm()->one();
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
	}
}
