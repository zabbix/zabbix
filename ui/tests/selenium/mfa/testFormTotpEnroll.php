<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	private const TOTP_METHOD_NAME = 'TOTP';

	private static $user_id;

	public function prepareData() {
		// Create a TOTP MFA method.
		$result = CDataHelper::call('mfa.create', [
			'type' => MFA_TYPE_TOTP,
			'name' => self::TOTP_METHOD_NAME,
			'hash_function' => TOTP_HASH_SHA1,
			'code_length' => TOTP_CODE_LENGTH_6
		]);

		// Enable TOTP and set it as the default MFA method.
		CDataHelper::call('authentication.update', [
			'mfa_status' => MFA_ENABLED,
			'mfaid' => $result['mfaids'][0] // this sets it as the default
		]);

		// Create a user group for testing MFA.
		$result = CDataHelper::call('usergroup.create', [
			'name' => 'TOTP group',
			'mfa_status' => MFA_ENABLED,
			//'mfaid'=> $result['mfaids'][0]
		]);

		// Create a user for testing MFA.
		self::$user_id = CDataHelper::call('user.create', [
			'username' => self::USER_NAME,
			'passwd' => 'zabbixzabbix',
			'roleid'=> 1, // User role
			'usrgrps' => [['usrgrpid' => $result['usrgrpids'][0]]]
		])['userids'][0];
	}

	/**
	 * Assert elements and layout in the enroll form (the form with QR code).
	 */
	public function testFormTotpEnroll_Layout() {
		$this->page->userLogin(self::USER_NAME, 'zabbixzabbix');

		// Assert Zabbix logo visible.
		$this->assertTrue($this->page->query('class:zabbix-logo')->one()->isVisible());

		// The form contains most other elements.
		$form = $this->page->query('class:signin-container')->asForm()->one();
		// Assert title.
		$this->assertTrue($form->query('xpath:./div[text()="Scan this QR code"]')->one()->isVisible());
		// Assert subtitle.
		$subtitle = 'Please scan and get your verification code displayed in your authenticator app.';
		$this->assertTrue($form->query('xpath:./div[text()='.CXPathHelper::escapeQuotes($subtitle).']')->one()->isVisible());

		// Assert the QR code.
		$qr_code = $form->query('class:qr-code')->one();
		// Assert the URL in the title attribute.
		$regex = $this->buildExpectedQrCodeUrlRegex(self::TOTP_METHOD_NAME, self::USER_NAME, SHA_1, 6);
		$this->assertEquals(1, preg_match($regex, $qr_code->getAttribute('title'), $matches), 'Failed to assert the QR code url.');
		// Save the secret string for later.
		$secret = $matches[1];
		// Assert the QR image visible.
		$qr_img = $qr_code->query('tag:img')->one();
		$this->assertTrue($qr_img->isVisible());
		$this->assertEquals("Scan me!", $qr_img->getAttribute('alt'));

		// Assert the description text.
		$description = 'Unable to scan? You can use '.strtoupper(SHA_1).' secret key to manually configure your authenticator app:';
		$this->assertTrue($form->query('xpath:./div[text()='.CXPathHelper::escapeQuotes($description).']')->one()->isVisible());
		// Assert the secret is visible.
		$this->assertTrue($form->query('xpath:./div[text()='.CXPathHelper::escapeQuotes($secret).']')->one()->isVisible());

		// Assert the 'Verification code' field.
		//$this->assertTrue($form->getField('Verification code')->isVisible());



	}

	/**
	 * Builds the QR code's URL as a regex string. Regex because the secret string is not known.
	 *
	 * @param string $method_name    The expected TOTP method name
	 * @param string $user_name      User that is trying to enroll
	 * @param string $algorithm      The expected Cryptographic algorithm, used for creating tokens
	 * @param int    $digits         The expected TOTP code length, number of digits
	 *
	 * @return string    Regex that matches the expected QR code's URL.
	 */
	protected function buildExpectedQrCodeUrlRegex($method_name, $user_name, $algorithm, $digits) {
		// The expected QR url should follow this format:
		// otpauth://totp/{method-name}:{user-name}?secret={secret}&issuer={method-name}&algorithm={algo}&digits={digits}&period=30
		$regex = '/^otpauth:\/\/totp\/'.$method_name.':'.$user_name.'\?secret=(['.CMfaTotpHelper::VALID_BASE32_CHARS.
				']{32})&issuer='.$method_name.'&algorithm='.strtoupper($algorithm).'&digits='.$digits.'&period=30$/';
		return $regex;
	}
}
