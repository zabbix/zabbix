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
class testFormTotpEnroll extends CWebTest {

	private const USER_NAME = 'totp-user';
	private const USER_PASS = 'zabbixzabbix';

	private const DEFAULT_METHOD_NAME = 'TOTP';
	private const DEFAULT_ALGO = TOTP_HASH_SHA1;
	private const DEFAULT_TOTP_CODE_LENGTH = TOTP_CODE_LENGTH_6;

	protected static $mfa_id;
	protected static $user_id;

	// Maps Zabbix API hash algorithms to their UI display name.
	protected static $algo_ui_map = [
		TOTP_HASH_SHA1 => 'SHA1',
		TOTP_HASH_SHA256 => 'SHA256',
		TOTP_HASH_SHA512 => 'SHA512'
	];

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
		$result = CDataHelper::call('usergroup.create', [
			'name' => 'TOTP group',
			'mfa_status' => MFA_ENABLED
		]);

		// Create a user for testing MFA.
		self::$user_id = CDataHelper::call('user.create', [
			'username' => self::USER_NAME,
			'passwd' => self::USER_PASS,
			'roleid'=> 1, // User role
			'usrgrps' => [['usrgrpid' => $result['usrgrpids'][0]]]
		])['userids'][0];
	}

	/**
	 * Assert elements and layout in the enroll form (the form with QR code).
	 */
	public function testFormTotpEnroll_Layout() {
		// Open the MFA enroll form.
		$this->page->userLogin(self::USER_NAME, self::USER_PASS);

		// Container of most elements.
		$container = $this->page->query('class:signin-container')->one();

		// Assert Zabbix logo.
		$this->assertTrue($container->query('class:zabbix-logo')->one()->isVisible());
		// Assert title.
		$this->assertTrue($container->query('xpath:.//div[text()="Scan this QR code"]')->one()->isVisible());
		// Assert subtitle.
		$subtitle = 'Please scan and get your verification code displayed in your authenticator app.';
		$this->assertTrue($container->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($subtitle).']')->one()->isVisible());

		// Assert the QR code.
		$qr_code = $container->query('class:qr-code')->one();
		// Assert the URL in the title attribute.
		$regex = $this->buildExpectedQrCodeUrlRegex(
			self::DEFAULT_METHOD_NAME, self::USER_NAME, self::DEFAULT_ALGO, self::DEFAULT_TOTP_CODE_LENGTH
		);
		$this->assertEquals(1, preg_match($regex, $qr_code->getAttribute('title'), $matches), 'Failed to assert the QR code url.');
		// Save the secret string for later.
		$totp_secret = $matches[1];
		// Assert the QR code image.
		$qr_img = $qr_code->query('tag:img')->one();
		$this->assertTrue($qr_img->isVisible());
		$this->assertEquals("Scan me!", $qr_img->getAttribute('alt'));

		// Assert the description text.
		$this->assertEnrollDescription($container, self::DEFAULT_ALGO, $totp_secret);

		// Assert 'Verification code' label.
		$label = $container->query('xpath:.//label[@for="verification_code"]')->one();
		$this->assertTrue($label->isVisible());
		$this->assertEquals('Verification code', $label->getText());
		// Assert 'Verification code' field.
		$code_field = $container->query('id:verification_code')->one();
		$this->assertTrue($code_field->isVisible() && $code_field->isEnabled());
		$this->assertEquals('255', $code_field->getAttribute('maxlength'));

		// Assert 'Sign in' button.
		$button = $container->query('id:enter')->one();
		$this->assertEquals('Sign in', $button->getText());
		$this->assertEquals('submit', $button->getAttribute('type'));
		$this->assertTrue($button->isClickable());

		// Since index_mfa.php is a unique form, also check the generic elements.
		$links = $this->page->query('class:signin-links')->one();

		$help_link = $links->query('xpath:./a[text()="Help"]')->one();
		$this->assertTrue($help_link->isClickable());
		$this->assertEquals(1,
			preg_match('/^https:\/\/www.zabbix.com\/documentation\/\d.\d\/$/', $help_link->getAttribute('href'))
		);
		$this->assertEquals('_blank', $help_link->getAttribute('target')); // opens link in a new tab

		$support_link = $links->query('xpath:./a[text()="Support"]')->one();
		$this->assertTrue($support_link->isClickable());
		$this->assertEquals('https://www.zabbix.com/support', $support_link->getAttribute('href'));
		$this->assertEquals('_blank', $support_link->getAttribute('target')); // opens link in a new tab

		$copyright = $this->page->query('xpath://footer[@role="contentinfo"]')->one();
		$this->assertTrue($copyright->isVisible());
		$this->assertEquals(1, preg_match('/^© 2001–20\d\d, Zabbix SIA$/', $copyright->getText()));
	}


	public function getEnrollData() {
		return [
			[
				[
					// Default MFA settings.
				]
			],
			[
				[
					// All MFA settings different.
					'mfa_data' => [
						'name' => 'Different name',
						'hash_function' => TOTP_HASH_SHA256,
						'code_length' => TOTP_CODE_LENGTH_8
					]
				]
			],
		];
	}

	public function prepareEnrollData() {
		$providedData = $this->getProvidedData();
		$data = reset($providedData);

		// Set the needed MFA configuration via API.
		CDataHelper::call('mfa.update', [
			'mfaid' => self::$mfa_id,
			'name' => CTestArrayHelper::get($data, 'mfa_data.name', self::DEFAULT_METHOD_NAME),
			'hash_function' => CTestArrayHelper::get($data, 'mfa_data.hash_function', self::DEFAULT_ALGO),
			'code_length' => CTestArrayHelper::get($data, 'mfa_data.code_length', self::DEFAULT_TOTP_CODE_LENGTH)
		]);
	}

	/**
	 * Test different enrollment scenarios.
	 *
	 * @dataProvider  getEnrollData
	 * @onBefore      prepareEnrollData
	 */
	public function testFormTotpEnroll_Enroll($data) {
		// Open the enroll form.
		$this->page->userLogin(self::USER_NAME, self::USER_PASS);

		// Get the used TOTP parameters.
		$totp_name = CTestArrayHelper::get($data, 'mfa_data.name', self::DEFAULT_METHOD_NAME);
		$totp_algo = CTestArrayHelper::get($data, 'mfa_data.hash_function', self::DEFAULT_ALGO);
		$totp_code_length = CTestArrayHelper::get($data, 'mfa_data.code_length', self::DEFAULT_TOTP_CODE_LENGTH);

		// Get elements.
		$form = $this->page->query('class:signin-container')->asForm()->one();
		$qr_code = $form->query('class:qr-code')->one();

		// Assert the QR code by validating the title attribute.
		$regex = $this->buildExpectedQrCodeUrlRegex($totp_name,self::USER_NAME, $totp_algo, $totp_code_length);
		$this->assertEquals(1, preg_match($regex, urldecode($qr_code->getAttribute('title')), $matches),
			'Failed to assert the QR code\'s title attribute.'
		);
		$totp_secret = $matches[1];

		// Assert the description text.
		$this->assertEnrollDescription($form, $totp_algo, $totp_secret);

		// Fill in the verification code (the TOTP itself).
		$totp = CMfaTotpHelper::generateTotp($totp_secret, $totp_code_length, $totp_algo);
		$form->getField('id:verification_code')->fill($totp);
		$form->query('button:Sign in')->one()->click();

		// Check if login successful.

	}

	/**
	 * Builds the QR code's URL as a regex string. Regex, because the secret string is not known.
	 *
	 * @param string $method_name    The expected TOTP method name.
	 * @param string $user_name      User that is trying to enroll.
	 * @param int    $algorithm      The expected TOTP Cryptographic algorithm.
	 * @param int    $digits         The expected TOTP code length, number of digits.
	 *
	 * @return string    Regex that matches the expected QR code's URL.
	 */
	protected function buildExpectedQrCodeUrlRegex($method_name, $user_name, $algorithm, $digits) {
		// The expected QR url should follow this format:
		// otpauth://totp/{method-name}:{user-name}?secret={secret}&issuer={method-name}&algorithm={algo}&digits={digits}&period=30
		$regex = '/^otpauth:\/\/totp\/'.$method_name.':'.$user_name.'\?secret=(['.CMfaTotpHelper::VALID_BASE32_CHARS.
				']{32})&issuer='.$method_name.'&algorithm='.self::$algo_ui_map[$algorithm].'&digits='.$digits.'&period=30$/';
		return $regex;
	}

	/**
	 * Asserts the description text under the QR code.
	 *
	 * @param CElement $container    Container element that should contain the description.
	 * @param int      $algorithm    The cryptographic algorithm that should be displayed.
	 * @param string   $secret       The secret that should be displayed.
	 */
	protected function assertEnrollDescription($container, $algorithm, $secret) {
		$description = 'Unable to scan? You can use '.self::$algo_ui_map[$algorithm].
				' secret key to manually configure your authenticator app:';
		$this->assertTrue($container->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($description).']')
			->one()->isVisible()
		);

		// Assert the secret is visible.
		$this->assertTrue($container->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($secret).']')
			->one()->isVisible());
	}
}
