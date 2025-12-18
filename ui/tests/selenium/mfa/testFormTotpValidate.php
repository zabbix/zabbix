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


require_once __DIR__.'/../common/testFormTotp.php';

/**
 * Tests the user login process when the MFA TOTP secret is already set in the database.
 * Simulates login using a TOTP code from an authenticator app (e.g., Google Authenticator).
 *
 * @backup mfa
 *
 * @onBefore prepareData
 */
class testFormTotpValidate extends testFormTotp {

	const TOTP_SECRET_16 = 'AAAAAAAAAAAAAAAA';
	const TOTP_SECRET_32 = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	public function testFormTotpValidate_Layout() {
		$this->quickEnrollUser();
		$this->userLogin();

		// All elements in the Validate form are also present in the enroll form, so reuse code from there.
		$this->testTotpLayout();

		// QR code should not be visible.
		$container = $this->query('class:signin-container')->waitUntilVisible()->one();
		$this->assertFalse($container->query('xpath:.//div[text()="Scan this QR code"]')->one(false)->isVisible());
		$this->assertFalse($container->query('class:qr-code')->query('tag:img')->one(false)->isVisible());
	}

	public function getValidateData() {
		// Many test cases overlap with the enroll form, so reuse the data provider.
		return array_merge($this->getGenericTotpData(), [
			[
				[
					// TOTP secret 16 characters long.
					'totp_secret' => self::TOTP_SECRET_16
				]
			]
		]);
	}

	public function prepareValidateData() {
		$data = $this->prepareMfaData();
		$this->quickEnrollUser(CTestArrayHelper::get($data, 'totp_secret', self::TOTP_SECRET_32));
	}

	/**
	 * Test different validation scenarios.
	 *
	 * @dataProvider getValidateData
	 *
	 * @onBefore prepareValidateData
	 */
	public function testFormTotpValidate_Validate($data) {
		// Open the validation form.
		$this->userLogin();

		// Get TOTP parameters.
		$totp_algo = CTestArrayHelper::get($data, 'mfa_data.hash_function', self::DEFAULT_ALGORITHM);
		$totp_code_length = CTestArrayHelper::get($data, 'mfa_data.code_length', self::DEFAULT_TOTP_CODE_LENGTH);
		$totp_secret = CTestArrayHelper::get($data, 'totp_secret', self::TOTP_SECRET_32);

		$form = $this->query('class:signin-container')->waitUntilVisible()->asForm()->one();

		// Get the verification code (the TOTP itself). Generate only if it is not defined in the data provider.
		CMfaTotpHelper::waitForSafeTotpWindow();
		$time_step_offset = CTestArrayHelper::get($data, 'time_step_offset', 0);
		$totp = CTestArrayHelper::get($data, 'totp',
				CMfaTotpHelper::generateTotp($totp_secret, $totp_code_length, $totp_algo, $time_step_offset)
		);
		$totp = CTestArrayHelper::get($data, 'totp_pre', '').$totp.CTestArrayHelper::get($data, 'totp_after', '');

		$form->getField('id:verification_code')->fill($totp);
		$form->query('button:Sign in')->one()->click();

		// Validate a successful login or an expected error.
		$this->page->waitUntilReady();
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			// Successful login.
			$this->page->assertUserIsLoggedIn();
			// Check that no error messages are displayed after logging in.
			$this->assertFalse($this->query('class:msg-bad')->one(false)->isValid(), 'Unexpected error on page.');
		}
		else {
			// Verify validation error.
			$this->assertEquals($data['error'], $form->query('class:red')->one()->getText());
		}
	}

	/**
	 * Test that it is not possible to use the same TOTP twice.
	 * It is a security feature for making sure that a stolen TOTP is not useful.
	 */
	public function testFormTotpValidate_ReuseTotp() {
		$this->resetTotpConfiguration();
		$this->quickEnrollUser();

		// Log in the first time, must be OK.
		$this->userLogin();
		CMfaTotpHelper::waitForSafeTotpWindow();
		$totp = CMfaTotpHelper::generateTotp(self::TOTP_SECRET_32);
		$form = $this->query('class:signin-container')->waitUntilVisible()->asForm()->one();
		$form->getField('id:verification_code')->fill($totp);
		$form->query('button:Sign in')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertUserIsLoggedIn();

		// Log out and try to log in using the same code. Should fail.
		$this->page->logout();
		$this->userLogin();
		$this->page->waitUntilReady();
		$form->invalidate();
		$form->getField('id:verification_code')->fill($totp);
		$form->query('button:Sign in')->one()->click();
		$this->assertEquals(self::DEFAULT_ERROR, $form->query('class:red')->one()->getText());
	}

	/**
	 * Test that user gets blocked if TOTP is entered wrong n times.
	 */
	public function testFormTotpValidate_Blocking() {
		$this->resetTotpConfiguration();
		$this->quickEnrollUser();

		// Blocking behaviour is shared with the enroll form, reuse code.
		$this->testTotpBlocking();
	}

	/**
	 * Takes screenshot of the validation form.
	 */
	public function testFormTotpValidate_Screenshot() {
		$this->resetTotpConfiguration();
		$this->quickEnrollUser();
		$this->userLogin();
		$form = $this->query('class:signin-container')->waitUntilVisible()->one();
		$this->page->removeFocus();
		$this->assertScreenshot($form, 'TOTP validation form');
	}

	/**
	 * To enroll quickly, the TOTP secret must be set.
	 * The secret can only be decided server-side, it can't be set by API or frontend.
	 * This method avoids having to manually enroll via UI by setting the secret in DB directly.
	 *
	 * @param string $secret  TOTP secret to set in DB.
	 *
	 * @throws Exception    If the TOTP secret is not valid, the test will fail.
	 */
	protected function quickEnrollUser($secret = self::TOTP_SECRET_32) {
		if (!CMfaTotpHelper::isValidSecretString($secret)) {
			throw new Exception('Invalid TOTP secret: '.$secret);
		}

		$db_data = [
			'mfaid' => self::$mfa_id,
			'userid' => self::$user_id,
			'totp_secret' => $secret,
			'status' => TOTP_SECRET_CONFIRMED
		];
		DB::insert('mfa_totp_secret', [$db_data]);
	}
}
