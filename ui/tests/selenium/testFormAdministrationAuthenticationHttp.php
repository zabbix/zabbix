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
 * @onBefore removeGuestFromDisabledGroup
 * @onAfter addGuestToDisabledGroup
 */
class testFormAdministrationAuthenticationHttp extends CLegacyWebTest {

	const LOGIN_GUEST	= 1;
	const LOGIN_USER		= 2;
	const LOGIN_HTTP		= 3;

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
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
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
							'action' => self::LOGIN_USER,
							'target' => 'Global view'
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
							'page' => 'zabbix.php?action=dashboard.view',
							'action' => self::LOGIN_GUEST,
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
							'page' => 'zabbix.php?action=dashboard.view',
							'action' => self::LOGIN_GUEST,
							'target' => 'Global view'
						],
						[
							'page' => 'zabbix.php?action=user.list',
							'error' => 'Access denied'
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
							'error' => 'Access denied'
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
	public function testFormAdministrationAuthenticationHttp_CheckSettings($data) {
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

					$message = CMessageElement::find()->one();
					$this->assertEquals('msg-bad msg-global', $message->getAttribute('class'));
					$message_title= $message->getText();
					$this->assertStringContainsString($check['error'], $message_title);
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

	/**
	 * Creating a configuration file.
	 *
	 * @param type $data
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
		$form = $this->query('name:form_auth')->asForm()->one();
		$http_options = CTestArrayHelper::get($data, 'http_authentication', ['Enable HTTP authentication' => false]);
		$form->selectTab('HTTP settings');
		$form->fill($http_options);

		// Check disabled or enabled fields.
		$fields = ['Default login form', 'Remove domain name', 'Case-sensitive login'];
		foreach ($fields as $field) {
			$this->assertTrue($form->getField($field)->isEnabled($http_options['Enable HTTP authentication']));
		}

		$this->createConfigurationFiles($data);
		$form->submit();

		// Check DB configuration.
		$defautl_values = [
			'authentication_type' => '0',
			'ldap_host' => '',
			'ldap_port' => '389',
			'ldap_base_dn' => '',
			'ldap_bind_dn' => '',
			'ldap_bind_password' => '',
			'ldap_search_attribute' => '',
			'ldap_configured' => '0',
			'ldap_case_sensitive' => '1'
		];
		$sql = 'SELECT authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,'.
				'ldap_search_attribute,ldap_configured,ldap_case_sensitive,http_auth_enabled,http_login_form,'.
				'http_strip_domains,http_case_sensitive'.
				' FROM config';
		$result = CDBHelper::getRow($sql);
		$this->assertEquals(array_merge($defautl_values, $data['db_check']), $result);

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
	public static function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (150, 9, 2)');
	}
}
