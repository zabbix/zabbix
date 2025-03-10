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


require_once __DIR__.'/../../include/CLegacyWebTest.php';

/**
 * @onBefore removeGuestFromDisabledGroup, addDefaultAllowAuthKeyToConfig
 *
 * @onAfter addGuestToDisabledGroup
 *
 * @backupConfig
 *
 * @dataSource LoginUsers
 */
class testUsersAuthenticationHttp extends CLegacyWebTest {

	const LOGIN_GUEST	= 1;
	const LOGIN_USER	= 2;
	const LOGIN_HTTP	= 3;

	protected function getFilePath() {
		return __DIR__.'/../../../conf/zabbix.conf.php';
	}

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public function testUsersAuthenticationHttp_Layout() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('HTTP settings');

		// Check default values.
		$default_values = [
			'Enable HTTP authentication' => false,
			'Default login form' => 'Zabbix login form',
			'Remove domain name' => '',
			'Case-sensitive login' => true
		];
		$form->checkValue($default_values);

		// Check disabled fields.
		$fields = ['Default login form', 'Remove domain name', 'Case-sensitive login'];
		foreach ($fields as $field) {
			$this->assertTrue($form->getField($field)->isEnabled(false));
		}

		// Check dropdown options.
		$this->assertEquals(['Zabbix login form', 'HTTP login form'], $form->getField('Default login form')
				->getOptions()->asText());

		// Check input field maxlength.
		$this->assertEquals('2048', $form->getField('Remove domain name')->getAttribute('maxlength'));

		// Check hintbox.
		$form->getLabel('Enable HTTP authentication')->query('class:zi-help-filled-small')->one()->click();
		$hintbox = $form->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent();
		$this->assertEquals('If HTTP authentication is enabled, all users (even with frontend access set to LDAP/Internal)'.
			' will be authenticated by the web server, not by Zabbix.', $hintbox->one()->getText());

		// Close the hintbox.
		$hintbox->query('class:btn-overlay-close')->one()->click()->waitUntilNotPresent();

		// Check confirmation popup.
		foreach (['button:Cancel', 'class:btn-overlay-close', 'button:Ok'] as $button) {
			$form->fill(['Enable HTTP authentication' => true]);
			$dialog = COverlayDialogElement::find()->one();
			$this->assertEquals('Confirm changes', $dialog->getTitle());
			$this->assertEquals('Enable HTTP authentication for all users.', $dialog->getContent()->getText());
			$dialog->query($button)->one()->click();
			COverlayDialogElement::ensureNotPresent();

			// Check disabled fields and checkbox status.
			$status = ($button === 'button:Ok') ? true : false;
			foreach ($fields as $field) {
				$this->assertTrue($form->getField($field)->isEnabled($status));
			}
			$this->assertEquals($status, $form->getField('Enable HTTP authentication')->getValue());
		}
	}

	public function getHttpData() {
		return [
			// HTTP authentication disabled, default zabbix login form.
			[
				[
					'user' => 'Admin',
					'password' => 'zabbix',
					'pages' => [
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'action' => self::LOGIN_GUEST,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						// Redirect to default zabbix login form, if open HTTP login form.
						[
							'page' => 'index_http.php',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						// Couldn't open GUI page due access.
						[
							'page' => 'zabbix.php?action=gui.edit',
							'error' => 'Access denied'
						],
						// Login after logout.
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						]
					],
					'db_check' => [
						'http_auth_enabled' => '0',
						'http_login_form' => '0',
						'http_strip_domains' => '',
						'http_case_sensitive' => '1'
					]
				]
			],
			// HTTP authentication enabled, but file isn't created.
			[
				[
					'user' => 'Admin',
					'password' => 'zabbix',
					'http_authentication' => [
						'Enable HTTP authentication' => true
					],
					'pages' => [
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'action' => self::LOGIN_GUEST,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						// Redirect to default zabbix login form, if open HTTP login form.
						[
							'page' => 'index_http.php',
							'error' => 'You are not logged in'
						],
						// Couldn't open GUI page due access.
						[
							'page' => 'zabbix.php?action=gui.edit',
							'error' => 'Access denied'
						],
						// Login after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'error' => 'Zabbix has received an incorrect request.',
							'no_login' => true
						]
					],
					'db_check' => [
						'http_auth_enabled' => '1',
						'http_login_form' => '0',
						'http_strip_domains' => '',
						'http_case_sensitive' => '1'
					]
				]
			],
			// HTTP authentication enabled (default login form is set to 'Zabbix login form').
			[
				[
					'user' => 'Admin',
					'password' => '123456',
					'db_password' => 'zabbix',
					'file' => 'pwfile',
					'http_authentication' => [
						'Enable HTTP authentication' => true,
						'Default login form' => 'Zabbix login form'
					],
					'pages' => [
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'action' => self::LOGIN_GUEST,
							'target' => 'Global view'
						],
						// No redirect - sign in through default zabbix login form.
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						// No redirect - sign in through default zabbix login form.
						[
							'page' => 'index.php',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						// Redirect to HTTP login form and user is signed on Dashboard page.
						[
							'page' => 'index_http.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						// Sign in through zabbix login form after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'error' => 'Zabbix has received an incorrect request.',
							'no_login' => true
						],
						// Couldn't open Hosts page due access.
						[
							'page' => self::HOST_LIST_PAGE,
							'error' => 'Access denied'
						],
						// Couldn't open GUI page due access.
						[
							'page' => 'zabbix.php?action=gui.edit',
							'error' => 'Access denied'
						]
					],
					'db_check' => [
						'http_auth_enabled' => '1',
						'http_login_form' => '0',
						'http_strip_domains' => '',
						'http_case_sensitive' => '1'
					]
				]
			],
			// HTTP authentication enabled (default login form is set to 'HTTP login form').
			[
				[
					'user' => 'Admin',
					'password' => '123456',
					'db_password' => 'zabbix',
					'file' => 'pwfile',
					'http_authentication' => [
						'Enable HTTP authentication' => true,
						'Default login form' => 'HTTP login form'
					],
					'pages' => [
						// No redirect - default zabbix login form.
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
//						// wait for ZBX-14774.
//						// Redirect to HTTP login form and user is signed on hosts page.
//						[
//							'page' => self::HOST_LIST_PAGE,
//							'action' => self::LOGIN_HTTP,
//							'target' => 'Hosts'
//						],
						// Redirect to HTTP login form and user is signed on dashboard page.
						[
							'page' => 'index.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						// Redirect to dashboard page and user is signed.
						[
							'page' => 'index_http.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						// Sign in through zabbix login form after logout.
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						// Redirect to HTTP login form and user is signed on GUI page.
						[
							'page' => 'zabbix.php?action=gui.edit',
							'action' => self::LOGIN_HTTP,
							'target' => 'GUI'
						]
					],
					'db_check' => [
						'http_auth_enabled' => '1',
						'http_login_form' => '1',
						'http_strip_domains' => '',
						'http_case_sensitive' => '1'
					]
				]
			],
			// HTTP authentication - Check domain (@local.com).
			[
				[
					'user' => 'Admin@local.com',
					'password' => '123456',
					'file' => 'htaccess',
					'db_password' => 'zabbix',
					'http_authentication' => [
						'Enable HTTP authentication' => true,
						'Default login form' => 'HTTP login form',
						'Remove domain name' => 'local.com'
					],
					'pages' => [
						[
							'page' => 'index.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						[
							'page' => 'index_http.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						]
					],
					'db_check' => [
						'http_auth_enabled' => '1',
						'http_login_form' => '1',
						'http_strip_domains' => 'local.com',
						'http_case_sensitive' => '1'
					]
				]
			],
			// HTTP authentication - Login with user http-auth-admin (Zabbix Admin).
			[
				[
					'user' => 'local.com\\http-auth-admin',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'http_authentication' => [
						'Enable HTTP authentication' => true,
						'Default login form' => 'HTTP login form',
						'Remove domain name' => 'local.com'
					],
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						[
							'page' => 'zabbix.php?action=user.list',
							'error' => 'Access denied',
							'no_login' => true
						],
//						// Redirect to HTTP login form and user is signed on hosts page.
//						// wait for ZBX-14774.
//						[
//							'page' => self::HOST_LIST_PAGE,
//							'action' => self::LOGIN_HTTP,
//							'target' => 'Hosts'
//						],
						// Redirect to HTTP login form and user is signed on dashboard page.
						[
							'page' => 'index.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						// Redirect to dashboard page and user is signed.
						[
							'page' => 'index_http.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						// Login after logout.
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						]
					],
					'db_check' => [
						'http_auth_enabled' => '1',
						'http_login_form' => '1',
						'http_strip_domains' => 'local.com',
						'http_case_sensitive' => '1'
					]
				]
			],
			// HTTP authentication - Login with user test-user (Zabbix User),
			[
				[
					'user' => 'local.com\\test-user',
					'password' => 'zabbix12345',
					'file' => 'htaccess',
					'http_authentication' => [
						'Enable HTTP authentication' => true,
						'Default login form' => 'HTTP login form',
						'Remove domain name' => 'local.com'
					],
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						],
						[
							'page' => 'zabbix.php?action=user.list',
							'error' => 'Access denied',
							'no_login' => true
						],
//						// wait for ZBX-14774.
//						[
//							'page' => self::HOST_LIST_PAGE,
//							'action' => self::LOGIN_HTTP,
//							'target' => 'hosts'
//						],
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						[
							'page' => 'index_http.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php?form=default',
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
						]
					],
					'db_check' => [
						'http_auth_enabled' => '1',
						'http_login_form' => '1',
						'http_strip_domains' => 'local.com',
						'http_case_sensitive' => '1'
					]
				]
			],
			// HTTP authentication - Case sensitive login,
			[
				[
					'user' => 'admin',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'http_authentication' => [
						'Enable HTTP authentication' => true,
						'Default login form' => 'Zabbix login form',
						'Case-sensitive login' => true
					],
					'pages' => [
						[
							'page' => 'index_http.php',
							'error' => 'You are not logged in'
						]
					],
					'db_check'  => [
						'http_auth_enabled' => '1',
						'http_login_form' => '0',
						'http_strip_domains' => '',
						'http_case_sensitive' => '1'
					]
				]
			],
			[
				[
					'user' => 'admin',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'user_case_sensitive' => 'Admin',
					'http_authentication' => [
						'Enable HTTP authentication' => true,
						'Default login form' => 'Zabbix login form',
						'Case-sensitive login' => false
					],
					'pages' => [
						[
							'page' => 'index_http.php',
							'action' => self::LOGIN_HTTP,
							'target' => 'Global view'
						]
					],
					'db_check'  => [
						'http_auth_enabled' => '1',
						'http_login_form' => '0',
						'http_strip_domains' => '',
						'http_case_sensitive' => '0'
					]
				]
			]
		];
	}

	/**
	 * Login with different authentication methods.
	 */
	protected function loginAs($data, $page) {
		switch (CTestArrayHelper::get($page, 'action', self::LOGIN_GUEST)) {
			case self::LOGIN_GUEST:
				$this->page->open($page['page']);

				if (CTestArrayHelper::get($page, 'no_login', false) === false) {
					$this->query('button:Login')->one()->click();
					$this->page->waitUntilReady();
					$this->query('link:sign in as guest')->one()->click();
					$this->page->waitUntilReady();
				}

				return 'guest';

			case self::LOGIN_USER:
				$this->page->open($page['page']);
				$this->page->waitUntilReady();

				// Check button 'Sign in with HTTP'.
				$xpath = '//a[@href="index_http.php"][text()="Sign in with HTTP"]';
				if (CTestArrayHelper::get($data, 'http_authentication.Enable HTTP authentication', false) === true) {
					$this->assertTrue($this->query('xpath', $xpath)->one()->isVisible());
				}
				else {
					$this->assertTrue($this->query('xpath', $xpath)->one(false)->isVisible(false));
				}

				$this->query('id:name')->one()->fill($this->getUsernameWithoutDomain($data['user']));
				$this->query('id:password')->one()->fill(CTestArrayHelper::get($data, 'db_password', $data['password']));
				$this->query('button:Sign in')->one()->click();

				break;

			case self::LOGIN_HTTP:
				$this->openAsHttpUser($data['user'], $data['password'], $page['page']);

				break;
		}

		return $this->getUsernameWithoutDomain($data['user']);
	}

	/**
	 * @dataProvider getHttpData
	 * @backup config
	 * @onAfter removeConfigurationFiles
	 *
	 * Internal authentication with HTTP settings.
	 */
	public function testUsersAuthenticationHttp_CheckSettings($data) {
		$this->setHttpConfiguration($data);

		// Check authentication on pages.
		foreach ($data['pages'] as $check) {

			$alias = $this->loginAs($data, $check);

			if (array_key_exists('error', $check)) {
				foreach (['simple', 'http'] as $type) {
					switch ($type) {
						case 'simple':
							$this->page->open($check['page']);
							break;

						case 'http':
							$this->openAsHttpUser($data['user'], $data['password'], $check['page']);
							break;
					}

					$this->assertMessage(TEST_BAD, $check['error']);
				}

				continue;
			}
			// Check page header after successful login.
			else {
				$this->assertEquals($check['target'], $this->query('tag:h1')->one()->getText());
			}

			// Check user data in DB after login.
			$session_cookie = $this->webDriver->manage()->getCookieNamed(ZBX_SESSION_NAME);
			$session_cookie = json_decode(base64_decode(urldecode($session_cookie['value'])), true);
			$session = $session_cookie['sessionid'];

			$user_data = CDBHelper::getRow(
				'SELECT u.username'.
				' FROM users u,sessions s'.
				' WHERE u.userid=s.userid'.
					' AND sessionid='.zbx_dbstr($session)
			);
			if (array_key_exists('user_case_sensitive', $data)) {
				$this->assertEquals($user_data['username'], $data['user_case_sensitive']);
			}
			else {
				$this->assertEquals($user_data['username'], $alias);
			}

			$this->page->logout();
			$this->page->reset();
			$this->page->open('index.php?form=default');
		}
	}

	public function getHttpAuthData() {
		return [
			// HTTP authentication enabled in zabbix.conf.php file.
			[
				[
					'config_string' => '$ALLOW_HTTP_AUTH = true;',
					'tabs' => ['Authentication', 'HTTP settings', 'LDAP settings', 'SAML settings', 'MFA settings']
				]
			],
			// HTTP authentication disabled in zabbix.conf.php file.
			[
				[
					'config_string' => '$ALLOW_HTTP_AUTH = false;',
					'tabs' => ['Authentication', 'LDAP settings', 'SAML settings', 'MFA settings']
				]
			]
		];
	}

	/**
	 * @dataProvider getHttpAuthData
	 */
	public function testUsersAuthenticationHttp_HttpAuthStatusChange($data) {
		// Update Zabbix frontend config.
		$pattern = array('/\/\/ \$ALLOW_HTTP_AUTH = false;/', '/\$ALLOW_HTTP_AUTH = true;/');
		$content = preg_replace($pattern, $data['config_string'], file_get_contents($this->getFilePath()), 1);
		file_put_contents($this->getFilePath(), $content);

		// Wait for frontend to get the new config from updated zabbix.conf.php file.
		sleep((int) ini_get('opcache.revalidate_freq') + 1);

		// Open authentication configuration form and verify that the HTTP settings tab is/isn't present.
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$this->assertEquals($data['tabs'], $form->getTabs());
	}

	/**
	 * Creating a configuration file.
	 *
	 * @param array $data	data array for HTTP settings setup
	 */
	protected function createConfigurationFiles($data) {
		switch (CTestArrayHelper::get($data, 'file')) {
			case 'htaccess':
				$this->assertTrue(file_put_contents(PHPUNIT_BASEDIR.'/ui/.htaccess', 'SetEnv REMOTE_USER "'.
						$data['user'].'"') !== false);

				break;

			case 'pwfile':
				$this->assertTrue(exec('htpasswd -c -b "'.PHPUNIT_BASEDIR.'/ui/.pwd" "'.$data['user'].'" "'.
						$data['password'].'" > /dev/null 2>&1') !== false);
				$content = '<Files index_http.php>'."\n".
						'	AuthType Basic'."\n".
						'	AuthName "Password Required"'."\n".
						'	AuthUserFile "'.PHPUNIT_BASEDIR.'/ui/.pwd"'."\n".
						'	Require valid-user'."\n".
						'</Files>';
				$this->assertTrue(file_put_contents(PHPUNIT_BASEDIR.'/ui/.htaccess', $content) !== false);

				break;
		}
	}

	/**
	 * Set HTTP authentication settings.
	 *
	 * @param array $data	data array for HTTP settings setup.
	 */
	private function setHttpConfiguration($data) {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$this->assertEquals('Authentication', $this->query('tag:h1')->one()->getText());
		$this->page->assertTitle('Configuration of authentication');

		// Fill fields in 'HTTP settings' tab.
		$form = $this->query('id:authentication-form')->asForm()->one();
		$http_options = CTestArrayHelper::get($data, 'http_authentication', ['Enable HTTP authentication' => false]);
		$form->selectTab('HTTP settings');

		if (CTestArrayHelper::get($data, 'http_authentication.Enable HTTP authentication', false) === true) {
			$form->fill(['Enable HTTP authentication' => true]);
			$this->query('button:Ok')->one()->click();
		}

		$form->fill($http_options);

		// Check disabled or enabled fields.
		$fields = ['Default login form', 'Remove domain name', 'Case-sensitive login'];
		foreach ($fields as $field) {
			$this->assertTrue($form->getField($field)->isEnabled($http_options['Enable HTTP authentication']));
		}

		$this->createConfigurationFiles($data);
		$form->submit();

		// Check DB configuration.
		$default_values = [
			'authentication_type' => '0',
			'ldap_auth_enabled' => '0',
			'ldap_case_sensitive' => '1',
			'ldap_userdirectoryid' => '0'
		];
		$sql = 'SELECT authentication_type,ldap_auth_enabled,ldap_case_sensitive,ldap_userdirectoryid,http_auth_enabled,http_login_form,'.
				'http_strip_domains,http_case_sensitive FROM config';

		$result = CDBHelper::getRow($sql);
		$this->assertEquals(array_merge($default_values, $data['db_check']), $result);

		$this->page->logout();
	}

	/**
	 * Open page with username and password in url.
	 */
	private function openAsHttpUser($user, $password, $url) {
		$parts = explode('//', PHPUNIT_URL.$url, 2);
		$full_url = $parts[0].'//'.urlencode($user).':'.urlencode($password).'@'.$parts[1];
		$this->webDriver->get($full_url);
	}

	/**
	 * Remove domain part of the username.
	 *
	 * @param string $alias		username
	 *
	 * @return string
	 */
	private function getUsernameWithoutDomain($alias) {
		$separator = strpos($alias, '@');
		if ($separator !== false) {
			$alias = substr($alias, 0, $separator);
		}
		else {
			$separator = strpos($alias, '\\');
			if ($separator !== false) {
				$alias = substr($alias, $separator + 1);
			}
		}
		return $alias;
	}

	/**
	 * Remove file after every test case.
	 */
	public function removeConfigurationFiles() {
		if (file_exists(PHPUNIT_BASEDIR.'/ui/.htaccess')) {
			unlink(PHPUNIT_BASEDIR.'/ui/.htaccess');
		}

		if (file_exists(PHPUNIT_BASEDIR.'/ui/.pwd')) {
			unlink(PHPUNIT_BASEDIR.'/ui/.pwd');
		}

		// Cleanup is required to avoid browser sending Basic auth header.
		self::closePage();
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	/**
	 * Add a commented $ALLOW_HTTP_AUTH variable to frontend configuration file to check disabling of HTTP authentication.
	 */
	public function addDefaultAllowAuthKeyToConfig() {
		$content = file_get_contents($this->getFilePath());
		$content .= '// $ALLOW_HTTP_AUTH = false;'."\n";

		file_put_contents($this->getFilePath(), $content);
	}

	public static function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (1551, 9, 2)');
	}
}
