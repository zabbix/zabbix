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
class testFormTotpValidate extends CWebTest {

	private const USER_NAME = 'totp-user';
	private const USER_PASS = 'zabbixzabbix';
	private const TOTP_SECRET_16 = 'AAAAAAAAAAAAAAAA';
	private const TOTP_SECRET_32 = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	// Number of times after which a user is blocked when a wrong TOTP is entered.
	private const BLOCK_COUNT = 5;

	private const DEFAULT_METHOD_NAME = 'TOTP';
	private const DEFAULT_ALGO = TOTP_HASH_SHA1;
	private const DEFAULT_TOTP_CODE_LENGTH = TOTP_CODE_LENGTH_6;

	protected static $mfa_id;
	protected static $user_id;
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

	public function testFormTotpValidate_Layout() {
		$this->quickEnrollUser(self::TOTP_SECRET_32);
		$this->page->userLogin(self::USER_NAME, self::USER_PASS);

		sleep(3);
	}

	/**
	 * The secret can only be decided server-side, it can't be set by API or frontend.
	 * Because of this the secret must be set directly in DB.
	 *
	 * @param $secret
	 */
	protected function quickEnrollUser($secret) {
		if (!CMfaTotpHelper::isValidSecretString($secret)) {
			throw new Exception('Invalid TOTP secret: '.$secret);
		}

		$db_data = [
			'mfaid' => self::$mfa_id,
			'userid' => self::$user_id,
			'totp_secret' => $secret,
			'status' => 1
		];
		var_dump(DB::insert('mfa_totp_secret', [$db_data]));

		sleep(10);
	}
}
