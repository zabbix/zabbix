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


require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @dataSource LoginUsers
 */
class testFormLogin extends CWebTest {

	public static function getLoginLogoutData() {
		return [
			[
				[
					'login' => 'LDAP user',
					'password' => 'zabbix12345',
					'expected' => TEST_BAD,
					'error_message' => 'Cannot bind anonymously to LDAP server.'
				]
			],
			[
				[
					'login' => 'disabled-user',
					'password' => 'zabbix12345',
					'expected' => TEST_BAD,
					'error_message' => 'No permissions for system access.'
				]
			],
			[
				[
					'login' => 'no-access-to-the-frontend',
					'password' => 'zabbix12345',
					'expected' => TEST_BAD,
					'error_message' => 'GUI access disabled.'
				]
			],
			[
				[
					'login' => 'admin',
					'password' => 'zabbix',
					'expected' => TEST_BAD,
					'error_message' => 'Incorrect user name or password or account is temporarily blocked.'
				]
			],
			[
				[
					'login' => 'Admin',
					'password' => 'Zabbix',
					'expected' => TEST_BAD,
					'error_message' => 'Incorrect user name or password or account is temporarily blocked.',
					'dbCheck' => true
				]
			],
			[
				[
					'login' => 'Admin',
					'password' => '',
					'expected' => TEST_BAD,
					'error_message' => 'Incorrect user name or password or account is temporarily blocked.',
					'dbCheck' => true
				]
			],
			[
				[
					'login' => 'Admin',
					'password' => '!@$#%$&^*(\"\'\\*;:',
					'expected' => TEST_BAD,
					'error_message' => 'Incorrect user name or password or account is temporarily blocked.',
					'dbCheck' => true
				]
			],
			[
				[
					'login' => 'Admin',
					'password' => 'zabbix',
					'expected' => TEST_GOOD
				]
			],
			[
				[
					'login' => 'guest',
					'password' => '',
					'expected' => TEST_GOOD
				]
			]
		];
	}

	/**
	 * Function is using previously defined data in order to login into system by checking different type of
	 * user permissions. When expected view is opened with the user, function is logging out of system.
	 * Additionally function checks if database is gathering correct data.
	 *
	 * @onBeforeOnce removeGuestFromDisabledGroup
	 * @onAfterOnce addGuestToDisabledGroup
	 *
	 * @dataProvider getLoginLogoutData
	 **/
	public function testFormLogin_LoginLogout($data) {
		$this->page->userLogin($data['login'], $data['password']);

		if ($data['expected'] === TEST_BAD) {
			$this->assertEquals($data['error_message'], $this->query('class:red')->waitUntilVisible()->one()->getText());
		}
		else {
			$this->page->assertHeader('Global view');
			$this->query('class:icon-signout')->one()->click();
			$this->assertEquals('Remember me for 30 days', $this->query('xpath://label[@for="autologin"]')->one()->getText());
		}

		if (CTestArrayHelper::get($data, 'dbCheck', false)) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM users WHERE attempt_failed>0 AND username='.zbx_dbstr($data['login'])));
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM users WHERE attempt_clock>0 AND username='.zbx_dbstr($data['login'])));
			$this->assertEquals(1, CDBHelper::getCount("SELECT NULL FROM users WHERE attempt_ip<>'' AND username=".zbx_dbstr($data['login'])));
		}
	}

	/**
	 * Function is creating failed authentication in order to block account, afterwards by halting it's work for
	 * 30 seconds, function checks if correctly inserted authentication data for account gives access to view
	 * and properly returns message stating how many times failed attempts have been made to login into account.
	 **/
	public function testFormLogin_BlockAccountAndRecoverAfter30Seconds() {
		$user = 'user-for-blocking';

		$this->page->open('index.php');
		for ($i = 1; $i < 5; $i++) {
			$this->page->userLogin($user, '!@$#%$&^*(\"\'\\*;:');
			$this->assertEquals('Incorrect user name or password or account is temporarily blocked.',
					$this->query('class:red')->waitUntilVisible()->one()->getText()
			);
			$this->assertEquals($i, CDBHelper::getValue('SELECT attempt_failed FROM users WHERE username='.zbx_dbstr($user)));
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM users WHERE username='.zbx_dbstr($user).' AND attempt_clock>0'));
			$this->assertEquals(1, CDBHelper::getCount("SELECT NULL FROM users WHERE username=".zbx_dbstr($user)." AND attempt_ip<>''"));
		}

		$this->page->userLogin($user, '!@$#%$&^*(\"\'\\*;:');
		$this->assertEquals('Incorrect user name or password or account is temporarily blocked.',
				$this->query('class:red')->waitUntilVisible()->one()->getText()
		);

		// Account is blocked, waiting 30 sec and trying to login.
		sleep(30);
		$this->page->userLogin($user, 'zabbix12345');
		$this->page->assertHeader('Global view');
		$this->assertStringContainsString('5 failed login attempts logged.',
				$this->query('class:msg-bad')->waitUntilVisible()->one()->getText()
		);
	}

	/**
	 * Function makes two authentifications with different data to different Zabbix views, separately clicking on
	 * sign in button and checking by views header, if correct url is opened.
	 **/
	public function testFormLogin_LoginWithRequest() {
		foreach (['index.php?request=zabbix.php%3Faction%3Dhost.list', 'index.php?request=zabbix.php%3Faction%3Dproxy.list'] as $url) {
			$this->page->userLogin('Admin', 'zabbix', $url);
			$header = ($url === 'index.php?request=zabbix.php%3Faction%3Dhost.list') ? 'Hosts' : 'Proxies';
			$this->page->assertHeader($header);
		}
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 **/
	public function removeGuestFromDisabledGroup() {
		CDataHelper::call('user.update', [
			[
				'userid' => '2',
				'usrgrps' => [
					['usrgrpid' => '8']
				]
			]
		]);
	}

	public static function addGuestToDisabledGroup() {
		CDataHelper::call('user.update', [
			[
				'userid' => '2',
				'usrgrps' => [
					['usrgrpid' => '8'],
					['usrgrpid' => '9']
				]
			]
		]);
	}
}
