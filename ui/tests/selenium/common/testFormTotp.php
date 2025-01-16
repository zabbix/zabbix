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


/**
 * @backup mfa, users
 *
 * @onBefore prepareData
 */
class testFormTotp extends CWebTest {

	// User for testing TOTP.
	protected const USER_NAME = 'totp-user';
	protected const USER_PASS = 'zabbixzabbix';

	// Default parameters for the TOTP MFA method.
	protected const DEFAULT_METHOD_NAME = 'TOTP';
	protected const DEFAULT_ALGO = TOTP_HASH_SHA1;
	protected const DEFAULT_TOTP_CODE_LENGTH = TOTP_CODE_LENGTH_6;

	// Number of times after which a user is blocked when a wrong TOTP is entered.
	protected const BLOCK_COUNT = 5;

	// For storing the objects created with API.
	protected static $user_id;
	protected static $mfa_id;
	protected static $usergroup_id;

	/**
	 * Attach behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	public function prepareData() {
		// Create a TOTP MFA method.
		self::$mfa_id = CDataHelper::call('mfa.create', [
			'type' => MFA_TYPE_TOTP,
			'name' => self::DEFAULT_METHOD_NAME,
			'hash_function' => self::DEFAULT_ALGO,
			'code_length' => self::DEFAULT_TOTP_CODE_LENGTH
		])['mfaids'][0];

		// Enable TOTP and set it as the default MFA method.
		CDataHelper::call('authentication.update', [
			'mfa_status' => MFA_ENABLED,
			'mfaid' => self::$mfa_id // set as default
		]);

		// Create a user group for testing MFA.
		self::$usergroup_id = CDataHelper::call('usergroup.create', [
			'name' => 'TOTP group',
			'mfa_status' => MFA_ENABLED
		])['usrgrpids'][0];

		// Create a user for testing MFA.
		self::$user_id = CDataHelper::call('user.create', [
			'username' => self::USER_NAME,
			'passwd' => self::USER_PASS,
			'roleid'=> 1, // User role
			'usrgrps' => [['usrgrpid' => self::$usergroup_id]]
		])['userids'][0];
	}

	/**
	 * Resets the TOTP configuration and secret.
	 */
	protected function resetTotpConfiguration($name = self::DEFAULT_METHOD_NAME, $hash_function = self::DEFAULT_ALGO,
			$code_length = self::DEFAULT_TOTP_CODE_LENGTH) {
		// Set the needed MFA configuration via API.
		CDataHelper::call('mfa.update', [
			'mfaid' => self::$mfa_id,
			'name' => $name,
			'hash_function' => $hash_function,
			'code_length' => $code_length
		]);

		// Makes sure the user is not already enrolled.
		CDataHelper::call('user.resettotp', [self::$user_id]);
	}

	/**
	 * Checks that the user has successfully logged in.
	 */
	protected function verifyLoggedIn() {
		$this->assertTrue($this->query('xpath://aside[@class="sidebar"]//a[text()="User settings"]')->exists());
	}
}
