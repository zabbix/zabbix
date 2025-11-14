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
 * Tests the enrollment process for MFA using TOTP. Verifies behavior when the TOTP secret is not
 * yet stored in the database and the user needs to scan a QR code and submit a valid code.
 *
 * @backup mfa
 *
 * @onBefore prepareData
 */
class testFormTotpEnroll extends testFormTotp {

	// Maps Zabbix API hash algorithms to their UI display name.
	const ALGORITHMS = [
		TOTP_HASH_SHA1 => 'SHA1',
		TOTP_HASH_SHA256 => 'SHA256',
		TOTP_HASH_SHA512 => 'SHA512'
	];

	/**
	 * Assert elements and layout in the enroll form (the form with QR code).
	 */
	public function testFormTotpEnroll_Layout() {
		// Open the MFA enroll form.
		$this->userLogin();

		// Container of most elements.
		$container = $this->query('class:signin-container')->waitUntilVisible()->one();

		// Assert title.
		$this->assertTrue($container->query('xpath:.//div[text()="Scan this QR code"]')->one()->isVisible());
		// Assert subtitle.
		$subtitle = 'Please scan and get your verification code displayed in your authenticator app.';
		$this->assertTrue($container->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($subtitle).']')
				->one()->isVisible()
		);

		// Assert the QR code and get the secret.
		$totp_secret = $this->validateQrCodeAndExtractSecret();

		// Assert the QR code image.
		$qr_img = $container->query('class:qr-code')->query('tag:img')->one();
		$this->assertTrue($qr_img->isVisible());
		$this->assertEquals('Scan me!', $qr_img->getAttribute('alt'));

		// Assert the description text.
		$this->assertEnrollDescription($container, self::DEFAULT_ALGORITHM, $totp_secret);

		// The other elements are common with the Verification form, reuse the code.
		$this->testTotpLayout();
	}

	public function getEnrollData() {
		// Most of the test cases come from the parent class.
		return array_merge($this->getGenericTotpData(), [
			[
				[
					// Long MFA method name.
					'mfa_data' => [
						'name' => STRING_128
					]
				]
			],
			[
				[
					// MFA method name with special characters.
					'mfa_data' => [
						'name' => '<script>alert("hi!")</script>&nbsp;👍'
					]
				]
			]
		]);
	}

	public function prepareEnrollData() {
		$this->prepareMfaData();
	}

	/**
	 * Test different enrollment scenarios.
	 *
	 * @dataProvider getEnrollData
	 *
	 * @onBefore prepareEnrollData
	 */
	public function testFormTotpEnroll_Enroll($data) {
		// Open the enroll form.
		$this->userLogin();

		// Get the used TOTP parameters.
		$totp_name = CTestArrayHelper::get($data, 'mfa_data.name', self::DEFAULT_METHOD_NAME);
		$totp_algo = CTestArrayHelper::get($data, 'mfa_data.hash_function', self::DEFAULT_ALGORITHM);
		$totp_code_length = CTestArrayHelper::get($data, 'mfa_data.code_length', self::DEFAULT_TOTP_CODE_LENGTH);

		// Get elements.
		$form = $this->query('class:signin-container')->waitUntilVisible()->asForm()->one();

		// Assert the QR code and get the secret.
		$totp_secret = $this->validateQrCodeAndExtractSecret($totp_name, self::USER_NAME, $totp_algo,
				$totp_code_length
		);

		// Assert the description text.
		$this->assertEnrollDescription($form, $totp_algo, $totp_secret);

		// Get the TOTP. Generate only if a custom values is not defined in the data provider.
		CMfaTotpHelper::waitForSafeTotpWindow();
		$time_step_offset = CTestArrayHelper::get($data, 'time_step_offset', 0);
		$totp = CTestArrayHelper::get($data, 'totp',
				CMfaTotpHelper::generateTotp($totp_secret, $totp_code_length, $totp_algo, $time_step_offset)
		);
		$totp = CTestArrayHelper::get($data, 'totp_pre', '').$totp.CTestArrayHelper::get($data, 'totp_after', '');

		$form->getField('id:verification_code')->fill($totp);
		$form->query('button:Sign in')->one()->hoverMouse()->click();

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
	 * Test that reopening the enroll form generates a new secret.
	 * It is important for a secret to change each time to ensure no one else has seen the secret before.
	 */
	public function testFormTotpEnroll_Regeneration() {
		// Reset TOTP secret to make sure the user has not already been enrolled.
		$this->resetTotpConfiguration();

		// Open the enroll form and get the secret.
		$this->userLogin();
		$old_totp_secret = $this->validateQrCodeAndExtractSecret();

		// Reload the page and make sure the secret has changed.
		$this->page->refresh()->waitUntilReady();
		$new_totp_secret = $this->validateQrCodeAndExtractSecret();
		$this->assertNotEquals($old_totp_secret, $new_totp_secret,
				'The TOTP secret seems to have stayed the same after reload, when it should have changed.'
		);
	}

	/**
	 * Tests that new enrollment is required after changing the MFA method.
	 *
	 * @backup settings
	 */
	public function testFormTotpEnroll_ChangeMfaMethod() {
		// Reset TOTP secret to make sure user has not already been enrolled.
		$this->resetTotpConfiguration();

		// Log in and enroll, check that login successful.
		$this->userLogin();
		$form = $this->query('class:signin-container')->waitUntilVisible()->asForm()->one();
		$this->performEnroll($form);
		$this->page->assertUserIsLoggedIn();
		$this->page->logout();

		// Create a different MFA method and assign it to the user.
		$alternative_mfa_name = 'Alternative MFA method';
		$mfa_id = CDataHelper::call('mfa.create', [
			'type' => MFA_TYPE_TOTP,
			'name' => $alternative_mfa_name,
			'hash_function' => self::DEFAULT_ALGORITHM,
			'code_length' => self::DEFAULT_TOTP_CODE_LENGTH
		])['mfaids'][0];

		// Set the new MFA method as the default.
		CDataHelper::call('authentication.update', [
			'mfa_status' => MFA_ENABLED,
			'mfaid' => $mfa_id
		]);

		// Check that enrollment is required again.
		$this->userLogin();
		$this->performEnroll($form, $alternative_mfa_name);
		$this->page->assertUserIsLoggedIn();
		$this->page->logout();

		// Change the default MFA method back to the original.
		CDataHelper::call('authentication.update', [
			'mfa_status' => MFA_ENABLED,
			'mfaid' => self::$mfa_id
		]);

		// Check that the user has to enroll the initial MFA method again.
		$this->userLogin();
		$this->performEnroll($form);
		$this->page->assertUserIsLoggedIn();
	}

	/**
	 * Test that user gets blocked if TOTP is entered wrong n times.
	 */
	public function testFormTotpEnroll_Blocking() {
		// Reset TOTP secret to make sure user has not already been enrolled.
		$this->resetTotpConfiguration();

		// Blocking behaviour is shared with the verify form, reuse code.
		$this->testTotpBlocking();
	}

	/**
	 * Takes a screenshot of the enroll form.
	 */
	public function testFormTotpEnroll_Screenshot() {
		$this->resetTotpConfiguration();
		$this->userLogin();
		$form = $this->query('class:signin-container')->waitUntilVisible()->one();
		$skip_fields = [
			// Hide the QR code.
			$this->page->query('class:qr-code')->one(),
			// Hide the secret string. It does not have a good selector, sadly.
			$this->page->query('xpath://form/div[last()]')->one()
		];
		$this->page->removeFocus();
		$this->assertScreenshotExcept($form, $skip_fields, 'TOTP enroll form');
	}

	/**
	 * Validates if the QR code is displaying the correct data and extracts the TOTP secret string and returns it.
	 * This is done by looking at the QR code's HTML 'title' attribute, no visual inspection is done.
	 *
	 * @param string   $method_name  The expected TOTP method name.
	 * @param string   $user_name    User that is trying to enroll.
	 * @param int      $algorithm    The expected TOTP Cryptographic algorithm.
	 * @param int      $code_length  The expected TOTP code length, number of digits.
	 *
	 * @return string
	 */
	protected function validateQrCodeAndExtractSecret($method_name = self::DEFAULT_METHOD_NAME,
			$user_name = self::USER_NAME, $algorithm = self::DEFAULT_ALGORITHM,
			$code_length = self::DEFAULT_TOTP_CODE_LENGTH) {
		// QR code element.
		$qr_code = $this->page->query('class:qr-code')->one();

		/*
		 * The expected QR code's URL should follow this format:
		 * otpauth://totp/{method-name}:{user-name}?secret={secret}&issuer={method-name}
		 * &algorithm={algorithms}&digits={code_length}&period=30
		 */
		$regex = '@^otpauth:\/\/totp\/'.preg_quote($method_name).':'.$user_name.
			'\?secret=(['.CMfaTotpHelper::VALID_BASE32_CHARS.']{32})'.
			'&issuer='.preg_quote($method_name).
			'&algorithm='.self::ALGORITHMS[$algorithm].
			'&digits='.$code_length.
			'&period=30$@';

		// The QR code title contains URL-encoded characters (e.g., spaces as %20), so we need to decode it.
		$qr_title = urldecode($qr_code->getAttribute('title'));
		$this->assertEquals(1, preg_match($regex, $qr_title, $matches),
				"Failed to assert the QR code.\nExpected title regex: ".$regex."\nActual title: ".$qr_title
		);

		// Extract the secret with regex and return it.
		$this->assertArrayHasKey(1, $matches, 'Secret not found in QR code URL');
		return $matches[1];
	}

	/**
	 * Performs the enrollment steps.
	 *
	 * @param CElement $form      Enroll form element.
	 * @param string   $mfa_name  Override for the expected MFA method name.
	 */
	protected function performEnroll($form, $mfa_name = null) {
		$totp_secret = $this->validateQrCodeAndExtractSecret($mfa_name ?? self::DEFAULT_METHOD_NAME);
		$totp = CMfaTotpHelper::generateTotp($totp_secret);
		$form->invalidate();
		$form->getField('id:verification_code')->fill($totp);
		$form->query('button:Sign in')->one()->click();
		$this->page->waitUntilReady();
	}

	/**
	 * Asserts the description text under the QR code.
	 *
	 * @param CElement $container  Container element that should contain the description.
	 * @param int      $algorithm  The cryptographic algorithm that should be displayed.
	 * @param string   $secret     The secret that should be displayed.
	 */
	protected function assertEnrollDescription($container, $algorithm, $secret) {
		$description = 'Unable to scan? You can use '.self::ALGORITHMS[$algorithm].
			' secret key to manually configure your authenticator app:';
		$this->assertTrue($container->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($description).']')
				->one()->isVisible()
		);

		// Assert that the secret is visible.
		$this->assertTrue($container->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($secret).']')
				->one()->isVisible()
		);
	}
}
