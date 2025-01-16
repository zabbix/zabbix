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
