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


require_once dirname(__FILE__).'/../common/testFormTotp.php';

/**
 * @backup mfa, users
 *
 * @onBefore prepareData
 */
class testFormTotpValidate extends testFormTotp {

	protected const TOTP_SECRET_16 = 'AAAAAAAAAAAAAAAA';
	protected const TOTP_SECRET_32 = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	public function testFormTotpValidate_Layout() {
		$this->quickEnrollUser();
		$this->page->userLogin(self::USER_NAME, self::USER_PASS);

		// All elements in the Validate form are also present in the Enroll form, so reuse code from there.
		$this->testTotpLayout();
	}

	/**
	 * Test that user gets blocked if TOTP is entered wrong n times.
	 */
	public function testFormTotpVerify_Blocking() {
		$this->resetTotpConfiguration();
		$this->quickEnrollUser();

		// Blocking behaviour is shared with the Enroll form, reuse code.
		$this->testTotpBlocking();
	}

	/**
	 * To enroll quickly, the TOTP secret must be set.
	 * The secret can only be decided server-side, it can't be set by API or frontend.
	 * This method avoids having to manually enroll via UI by setting the secret in DB directly.
	 *
	 * @param string $secret    TOTP secret to set in DB.
	 */
	protected function quickEnrollUser($secret = self::TOTP_SECRET_32) {
		if (!CMfaTotpHelper::isValidSecretString($secret)) {
			throw new Exception('Invalid TOTP secret: '.$secret);
		}

		$db_data = [
			'mfaid' => self::$mfa_id,
			'userid' => self::$user_id,
			'totp_secret' => $secret,
			'status' => 1
		];
		DB::insert('mfa_totp_secret', [$db_data]);
	}
}
