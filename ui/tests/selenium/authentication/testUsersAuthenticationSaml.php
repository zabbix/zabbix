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


require_once __DIR__.'/../common/testFormAuthentication.php';

/**
 * @onBefore getConfFileContent, setSamlCertificatesStorage
 * @onAfter revertConfFile
 *
 * @backup settings
 */
class testUsersAuthenticationSaml extends testFormAuthentication {

	const SSL_CERTIFICATE = "-----BEGIN CERTIFICATE-----\r\n".
			"MIID+TCCAuGgAwIBAgIUSpDnLjL2DVS0YTRGOQh+MMoUtDowDQYJKoZIhvcNAQEL\r\n".
			"BQAwgYsxCzAJBgNVBAYTAlBMMQ0wCwYDVQQIDARXcm9jMRAwDgYDVQQHDAdXcm9j\r\n".
			"bGF3MQ8wDQYDVQQKDAZaYWJiaXgxCzAJBgNVBAsMAklUMRUwEwYDVQQDDAxJcnlu\r\n".
			"YSBTaGFyaGExJjAkBgkqhkiG9w0BCQEWF2lyeW5hLnNoYXJoYUB6YWJiaXguY29t\r\n".
			"MB4XDTI1MDQyNDE2NDE0M1oXDTI2MDQyNDE2NDE0M1owgYsxCzAJBgNVBAYTAlBM\r\n".
			"MQ0wCwYDVQQIDARXcm9jMRAwDgYDVQQHDAdXcm9jbGF3MQ8wDQYDVQQKDAZaYWJi\r\n".
			"aXgxCzAJBgNVBAsMAklUMRUwEwYDVQQDDAxJcnluYSBTaGFyaGExJjAkBgkqhkiG\r\n".
			"9w0BCQEWF2lyeW5hLnNoYXJoYUB6YWJiaXguY29tMIIBIjANBgkqhkiG9w0BAQEF\r\n".
			"AAOCAQ8AMIIBCgKCAQEAvHwPw8t5wB6e73ciAJ6LrHFSKRjgMQlGP31Sku/g8pTA\r\n".
			"8dFbblBj/yXKPkyqrnO1EvBoZB330HqRnlarXsstCFcC8ESQ+EzlB0737dc0jDdy\r\n".
			"WD3MsN2+YZRisKtaFwdswnYd23D4A6ymEYtjCAgKcpPJ4ciX+aZUkjS6BkMqyeGq\r\n".
			"zm0ig9GYwC8OsfG0ZxWV0s8m8MwC0DDPGnTSeFuCRwVftwqIjZOPocm2xpuWXQzF\r\n".
			"e2k4C5GofJ8BW0hNYeyzxnI+eOJHpgamtNlA5MeIcSTrtpGrqmm3XGz1H8F27kVi\r\n".
			"rrLVdfLcy1BYxb7I0eca4YjByvqqWrWukFq4Xs+/cQIDAQABo1MwUTAdBgNVHQ4E\r\n".
			"FgQUd9PRJ5ORONgZzkgVUE9SpCpeW9IwHwYDVR0jBBgwFoAUd9PRJ5ORONgZzkgV\r\n".
			"UE9SpCpeW9IwDwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0BAQsFAAOCAQEAV8dE\r\n".
			"Al+/w/8eOEqAlSeS9g2+g+4hvAAkTtp8HfWzMiqy56ZoAtzMOY4A/1QINZn4gvUk\r\n".
			"VW+SCr2X/AAqW7rIXFEbng7LyfwPUYJ++L1/aRqlEIuSvCmwa3Ypj6PqtN1RepEL\r\n".
			"jSXIQ/c5h+R+e4MUGU+mhS0evonfGaklB9xVz+amOMhU0Ag04Sp3HH+MayqWrkJP\r\n".
			"ntEXfn6G1X/mRSefF2k3UC5gZRYsWRybYtmtddrZcSApMedpx6YjtpFAd6+Z2UlL\r\n".
			"XlyFUVaZU/mT+orYNshgWEjBR2Mra1m0MKC1yWLG/eS7OUdzYLTyl0rClB5M0YFe\r\n".
			"pYDypczpOVk8mPLcZg==\r\n".
			"-----END CERTIFICATE-----";

	const SSL_PRIVATE_KEY = "-----BEGIN PRIVATE KEY-----\r\n".
			"MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQC8fA/Dy3nAHp7v\r\n".
			"dyIAnouscVIpGOAxCUY/fVKS7+DylMDx0VtuUGP/Jco+TKquc7US8GhkHffQepGe\r\n".
			"Vqteyy0IVwLwRJD4TOUHTvft1zSMN3JYPcyw3b5hlGKwq1oXB2zCdh3bcPgDrKYR\r\n".
			"i2MICApyk8nhyJf5plSSNLoGQyrJ4arObSKD0ZjALw6x8bRnFZXSzybwzALQMM8a\r\n".
			"dNJ4W4JHBV+3CoiNk4+hybbGm5ZdDMV7aTgLkah8nwFbSE1h7LPGcj544kemBqa0\r\n".
			"2UDkx4hxJOu2kauqabdcbPUfwXbuRWKustV18tzLUFjFvsjR5xrhiMHK+qpata6Q\r\n".
			"Wrhez79xAgMBAAECggEAANK9KIGvl4t57hWbTIex8amdpKrczfY2co+SMAgVtpe8\r\n".
			"UGfmgcOGMwLIweu1Tqb3p7QJTL7UigiM2bVWKe/Y9iVKsj1jcGouo7N5+zzTc7Je\r\n".
			"tclteBvu7j0j3g+l+DcIZWtIT+0EsUyO/8Fc/PEYTXGI9Kef86FEACIrN2DROnn2\r\n".
			"Ek4R1Lg/92dq10bpnYTMT8txctREQ2gQTXRuNUGIUACUzdoXARNqGth6+k9+gWYh\r\n".
			"IpXiCoBz5Sh8bS/QveehEZtdB/j+/iCcVBjuxwsxNcN7Iq9TKuPoC5/pOa+KoxXC\r\n".
			"kss1mU5A+boJMSL0lZHsFaaGJINliNBPqGZRkU2xxwKBgQD5XtqpYrWFRiYrX1YM\r\n".
			"nFfgIzl69h9EufI/DRHwFObFz7gvaDyQf0HMPT52YwEJiZSwaES+E4fFrmPxg53V\r\n".
			"VbdkBL3LsAXGZqJqQjHuWw0Lx756jd7mABAF4CIR2d9hk3YAA1+d8djsSLqN2xl4\r\n".
			"ptQoxehZLAQzCQkiMC0KxFSnowKBgQDBftXDnZWGUTlezunur/HBymiHdMzUTHsk\r\n".
			"7CgUEOEfaA6eu1yA7udyVsSbhss0AFZgqCevb90J8iBnLnQNqte+gY3qglY1L3od\r\n".
			"9Yv4kvDTGgdAesafiivo+TY3g6JD14M/2LbUutN6kWywLeJGSwgnJwAWSjznA6VM\r\n".
			"TW5+WOu92wKBgQC8//ZMYSLg0u0E/GnUfv5fQ3NCTZ4fUatXvEk3JDBQBoI7dA5L\r\n".
			"Ghg9esGHqrvThbHrDevkABtsaSMYnj+WvDOVm75ZzZxi5dD9JhR/6gR2RDqK2lHx\r\n".
			"EmUSfvBzhSS36LKLigMDS5S0aN7zuvaQKiksierzAthf8d45SjgpK+pZbwKBgQCT\r\n".
			"GPctNPldGRaCKs7Qc9VYO6XnhDXLFzFuylFVn9dk5thmd41FP1mYJLpmeby1FaSU\r\n".
			"6oDw8Bub2gQkLL5xPXWyEA9xPhCHckZlzCvSlvKZqWnl7PBejM4A2KQM4/dRl97h\r\n".
			"hMDJTBZFUZTNArTIN3ZFPXLlfx55iN36+cqMJtFgjQKBgQDcffg1rc/ayzuJd7Ym\r\n".
			"OzQ7joemEK5DIDRxryFxWnDXLrAZA1V+iUiKESIX1E8TGSAMymwUW2nWWCuhUps6\r\n".
			"pFw9z8Z3AaerRZA5fl655v500jUqziwBfifSimNL0hzmZfG6XUt6F7y4rxa2HFuu\r\n".
			"uwMrOBKatg7CZ1Uenv1K3ioD5w==\r\n".
			"-----END PRIVATE KEY-----";

	protected function onBeforeTestSuite() {
		if (!defined('PHPUNIT_SAML_TESTS_ENABLED') || !PHPUNIT_SAML_TESTS_ENABLED) {
			self::markTestSuiteSkipped();
		}
	}

	const CONF_PATH = __DIR__.'/../../../conf/zabbix.conf.php';
	protected static $conf_file_content;

	/**
	 * The original contents of frontend configuration file before test.
	 */
	protected function getConfFileContent() {
		self::$conf_file_content = file_get_contents(self::CONF_PATH);
	}

	/**
	 * @onAfter setSamlCertificatesStorage
	 */
	public function testUsersAuthenticationSaml_Layout() {
		$saml_form = $this->openFormAndCheckBasics('SAML');

		// Check that private key and certificates fields ar not visible if set 'file' in conf file.
		$storage_fields = ['id:idp_certificate', 'id:idp_certificate_file',	'id:sp_private_key', 'id:sp_private_key_file',
			'id:sp_certificate', 'id:sp_certificate_file'];
		foreach ($storage_fields as $field) {
			$this->assertTrue($saml_form->query($field)->one(false)->isVisible(false),
					'Field id '.$field.' is visible on page.'
			);
		}

		// Change storage to 'database' in frontend configuration file.
		$this->setSamlCertificatesStorage('database');
		$this->page->refresh()->waitUntilReady();
		$saml_form->invalidate()->selectTab('SAML settings');
		$saml_form->getField('Enable SAML authentication');

		// Check SAML form default values.
		$saml_fields = [
			'Enable JIT provisioning' => ['value' => false, 'visible' => true],
			'IdP entity ID' => ['value' => '', 'visible' => true, 'maxlength' => 1024],
			'SSO service URL' => ['value' => '', 'visible' => true, 'maxlength' => 2048],
			'SLO service URL' => ['value' => '', 'visible' => true, 'maxlength' => 2048],
			'Username attribute' => ['value' => '', 'visible' => true, 'maxlength' => 128],
			'SP entity ID' => ['value' => '', 'visible' => true, 'maxlength' => 1024],
			'SP name ID format' => ['value' => '', 'visible' => true, 'maxlength' => 2048,
					'placeholder' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient'
			],
			'id:idp_certificate' => ['value' => '', 'visible' => true, 'placeholder' => 'PEM-encoded IdP certificate'],
			'id:idp_certificate_file' => ['visible' => true],
			'id:sp_private_key' => ['value' => '', 'visible' => true, 'placeholder' => 'PEM-encoded SP private key'],
			'id:sp_private_key_file' => ['visible' => true],
			'id:sp_certificate' => ['value' => '', 'visible' => true, 'placeholder' => 'PEM-encoded SP certificate'],
			'id:sp_certificate_file' => ['visible' => true],
			'id:sign_messages' => ['value' => false, 'visible' => true],
			'id:sign_assertions' => ['value' => false, 'visible' => true],
			'id:sign_authn_requests' => ['value' => false, 'visible' => true],
			'id:sign_logout_requests' => ['value' => false, 'visible' => true],
			'id:sign_logout_responses' => ['value' => false, 'visible' => true],
			'id:encrypt_nameid' => ['value' => false, 'visible' => true],
			'id:encrypt_assertions' => ['value' => false, 'visible' => true],
			'Case-sensitive login' => ['value' => false, 'visible' => true],
			'Configure JIT provisioning' => ['value' => false, 'visible' => true],
			'Group name attribute' => ['value' => '', 'visible' => false, 'maxlength' => 255],
			'User name attribute' => ['value' => '', 'visible' => false, 'maxlength' => 255],
			'User last name attribute' => ['value' => '', 'visible' => false, 'maxlength' => 255],
			'User group mapping' => ['visible' => false],
			'Media type mapping' => ['visible' => false],
			'Enable SCIM provisioning' => ['value' => false, 'visible' => false]
		];

		foreach ($saml_fields as $field => $attributes) {
			$this->assertEquals($attributes['visible'], $saml_form->getField($field)->isVisible());
			$this->assertFalse($saml_form->getField($field)->isEnabled());

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $saml_form->getField($field)->getValue());
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $saml_form->getField($field)->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $saml_form->getField($field)->getAttribute('placeholder'));
			}
		}

		// Check visible mandatory fields.
		$this->assertEquals(['IdP entity ID', 'SSO service URL', 'Username attribute', 'SP entity ID', 'IdP certificate'],
				$saml_form->getRequiredLabels()
		);

		// Check invisible mandatory field.
		foreach (['Group name attribute', 'User group mapping'] as $manadatory_field) {
			$saml_form->isRequired($manadatory_field);
		}

		// Enable SAML and check that fields become enabled.
		$saml_form->fill(['Enable SAML authentication' => true]);

		foreach (array_keys($saml_fields) as $label) {
			$this->assertTrue($saml_form->getField($label)->isEnabled());
		}

		// Check mandatory SP fields.
		$sp_fields = ['SP private key', 'SP certificate'];
		$checkbox_groups = [
			'Sign' => ['Messages', 'Assertions', 'AuthN requests', 'Logout requests', 'Logout responses'],
			'Encrypt' => ['Name ID', 'Assertions']
		];

		foreach ($checkbox_groups as $group => $checkboxes) {
			foreach ($checkboxes as $label) {
				$saml_form->getField($group)->check($label);

				foreach ($sp_fields as $sp_field) {
					$this->assertTrue($saml_form->isRequired($sp_field), 'Field '.$sp_field.
							' should be mandatory when '.$label.' is checked.');
				}

				$saml_form->getField($group)->uncheck($label);

				foreach ($sp_fields as $sp_field) {
					$this->assertFalse($saml_form->isRequired($sp_field), 'Field '.$sp_field.' should not be mandatory.');
				}

			}

		}

		// Check that JIT fields remain invisible and depend on "Configure JIT" checkbox.
		$jit_fields = array_slice($saml_fields, 22);

		foreach ([false, true] as $jit_status) {
			$saml_form->fill(['Configure JIT provisioning' => $jit_status]);

			foreach (array_keys($jit_fields) as $label) {
				$this->assertTrue($saml_form->getField($label)->isVisible($jit_status));
			}
		}

		$hintboxes = [
			'Media type mapping' => "Map user's SAML media attributes (e.g. email) to Zabbix user media for".
				" sending notifications."
		];

		// Mapping tables headers.
		$mapping_tables = [
			'User group mapping' => [
				'id' => 'saml-group-table',
				'headers' => ['SAML group pattern', 'User groups', 'User role', 'Action']
			],
			'Media type mapping' => [
				'id' => 'saml-media-type-mapping-table',
				'headers' => ['Name', 'Media type', 'Attribute', 'Action']
			]
		];

		$this->checkFormHintsAndMapping($saml_form, $hintboxes, $mapping_tables, 'SAML');
	}

	public function getConfigureValidationData() {
		return [
			// #0 Missing IdP entity ID.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "idp_entityid": cannot be empty.'
				]
			],
			// #1 Missing SSO service URL.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "sso_url": cannot be empty.'
				]
			],
			// #2 Missing Username attribute.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "username_attribute": cannot be empty.'
				]
			],
			// #3 Missing SP entity ID.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA'
					],
					'error' => 'Incorrect value for field "sp_entityid": cannot be empty.'
				]
			],
			// #4 Missing Group name attribute.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP entity',
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP entity',
						'Configure JIT provisioning' => true
					],
					'error' => 'Incorrect value for field "saml_group_name": cannot be empty.'
				]
			],
			// #5 Missing Group name attribute.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP entity',
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP entity',
						'Configure JIT provisioning' => true,
						'Group name attribute' => 'group name attribute'
					],
					'error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
				]
			],
			// #6 Group mapping dialog form validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP entity',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'Configure JIT provisioning' => true,
						'Group name attribute' => 'group name attribute'
					],
					'User group mapping' => [[]],
					'mapping_error' => 'Invalid user group mapping configuration.',
					'mapping_error_details' => [
						'Field "roleid" is mandatory.',
						'Incorrect value for field "name": cannot be empty.',
						'Field "user_groups" is mandatory.'
					],
					'error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
				]
			],
			// #7 Media mapping dialog form validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP entity',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'Configure JIT provisioning' => true,
						'Group name attribute' => 'group name attribute'
					],
					'User group mapping' => [[]],
					'mapping_error' => 'Invalid media type mapping configuration.',
					'mapping_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "attribute": cannot be empty.'
					],
					'error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
				]
			],
			// #8 Missing IdP certificate.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'error' => 'Invalid parameter "/1/idp_certificate": cannot be empty.'
				]
			],
			// #9 IdP certificate encoding validation.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => STRING_32
					],
					'error' => 'Invalid parameter "/1/idp_certificate": a PEM-encoded certificate is expected.'
				]
			],
			// #10 IdP certificate max length validation.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => STRING_6000.STRING_6000
					],
					'error' => 'Invalid parameter "/1/idp_certificate": value is too long.'
				]
			],
			// #11 Missing SP private key.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => self::SSL_CERTIFICATE,
						'SP certificate' => self::SSL_CERTIFICATE,
						// Sign.
						'id:sign_messages' => true

					],
					'error' => 'Invalid parameter "/1/sp_private_key": cannot be empty.'
				]
			],
			// #12 SP private key encoding validation.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => self::SSL_CERTIFICATE,
						'SP private key' => STRING_32,
						'SP certificate' => self::SSL_CERTIFICATE,
						// Sign.
						'id:sign_assertions' => true
					],
					'error' => 'Invalid parameter "/1/sp_private_key": a PEM-encoded private key is expected.'
				]
			],
			// #13 SP private key max length validation.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => self::SSL_CERTIFICATE,
						'SP private key' => STRING_6000.STRING_6000,
						'SP certificate' => self::SSL_CERTIFICATE,
						// Sign.
						'id:sign_authn_requests' => true
					],
					'error' => 'Invalid parameter "/1/sp_private_key": value is too long.'
				]
			],
			// #14 Missing SP certificate.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => self::SSL_CERTIFICATE,
						'SP private key' => self::SSL_PRIVATE_KEY,
						// Encrypt.
						'id:encrypt_nameid' => true

					],
					'error' => 'Invalid parameter "/1/sp_certificate": cannot be empty.'
				]
			],
			// #15 SP certificate encoding validation.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => self::SSL_CERTIFICATE,
						'SP private key' => self::SSL_PRIVATE_KEY,
						'SP certificate' => STRING_32,
						// Encrypt.
						'id:encrypt_assertions' => true
					],
					'error' => 'Invalid parameter "/1/sp_certificate": a PEM-encoded certificate is expected.'
				]
			],
			// #16 SP certificate max length validation.
			[
				[
					'expected' => TEST_BAD,
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => self::SSL_CERTIFICATE,
						'SP private key' => self::SSL_PRIVATE_KEY,
						'SP certificate' => STRING_6000.STRING_6000,
						// Sign.
						'id:sign_logout_requests' => true,
						'id:sign_logout_responses' => true
					],
					'error' => 'Invalid parameter "/1/sp_certificate": value is too long.'
				]
			]
		];
	}

	public function getConfigureData() {
		return [
			// #0 Configure SAML with minimal fields.
			[
				[
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'db_check' => [
						'settings' => [
							'saml_auth_enabled' => 1,
							'saml_case_sensitive' => 0,
							'saml_jit_status' => 0
						],
						'userdirectory_saml' => [
							[
								'idp_entityid' => 'IdP',
								'sso_url' => 'SSO',
								'username_attribute' => 'UA',
								'sp_entityid' => 'SP'
							]
						]
					]
				]
			],
			// #1 Various UTF-8 characters in SAML settings fields + All possible fields with JIT configuration.
			[
				[
					'Deprovisioned users group' => 'Disabled',
					'fields' => [
						'Enable JIT provisioning' => true,
						'IdP entity ID' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SSO service URL' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SLO service URL' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Username attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SP entity ID' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						// Sign.
						'id:sign_messages' => true,
						'id:sign_assertions' => true,
						'id:sign_authn_requests' => true,
						'id:sign_logout_requests' => true,
						'id:sign_logout_responses' => true,
						// Encrypt.
						'id:encrypt_nameid' => true,
						'id:encrypt_assertions' => true,
						'Case-sensitive login' => true,
						'Configure JIT provisioning' => true,
						'SP name ID format' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Group name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'User name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'User last name attribute'=> '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Enable SCIM provisioning' => true
					],
					'User group mapping' => [
						[
							'SAML group pattern' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
							'User groups' => 'Test timezone',
							'User role' => 'User role'
						]
					],
					'Media type mapping' => [
						[
							'Name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
							'Media type' => 'Discord',
							'Attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
						]
					],
					'db_check' => [
						'settings' => [
							'saml_auth_enabled' => 1,
							'saml_case_sensitive' => 1,
							'saml_jit_status' => 1
						],
						'userdirectory_saml' => [
							[
								'idp_entityid' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'sso_url' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'slo_url' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'username_attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'sp_entityid' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'sign_messages' => 1,
								'sign_assertions' => 1,
								'sign_authn_requests' => 1,
								'sign_logout_requests' => 1,
								'sign_logout_responses' => 1,
								'encrypt_nameid' => 1,
								'encrypt_assertions' => 1,
								'nameid_format' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'group_name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'user_username' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'user_lastname' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						],
						'userdirectory_media' => [
							[
								'name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'mediatypeid' => 71, // Discord
								'attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							]
						]
					]
				]
			],
			// #2 SAML settings with leading and trailing spaces.
			[
				[
					'trim' => true,
					'fields' => [
						'IdP entity ID' => '   leading.trailing   ',
						'SSO service URL' => '   leading.trailing   ',
						'SLO service URL' => '   leading.trailing   ',
						'Username attribute' => '   leading.trailing   ',
						'SP entity ID' => '   leading.trailing   ',
						'SP name ID format' => '   leading.trailing   ',
						'Configure JIT provisioning' => true,
						'Group name attribute' => '   leading.trailing   ',
						'User name attribute' => '   leading.trailing   ',
						'User last name attribute'=> '   leading.trailing   '
					],
					'User group mapping' => [
						[
							'SAML group pattern' => '   leading.trailing   ',
							'User groups' => 'Test timezone',
							'User role' => 'User role'
						]
					],
					'Media type mapping' => [
						[
							'Name' => '   leading.trailing   ',
							'Media type' => 'Discord',
							'Attribute' => 'leading.trailing'
						]
					],
					'db_check' => [
						'userdirectory_saml' => [
							[
								'idp_entityid' => 'leading.trailing',
								'sso_url' => 'leading.trailing',
								'slo_url' => 'leading.trailing',
								'username_attribute' => 'leading.trailing',
								'sp_entityid' => 'leading.trailing',
								'nameid_format' => 'leading.trailing',
								'group_name' => 'leading.trailing',
								'user_username' => 'leading.trailing',
								'user_lastname' => 'leading.trailing'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => 'leading.trailing',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						],
						'userdirectory_media' => [
							[
								'name' => 'leading.trailing',
								'mediatypeid' => 71, //Discord
								'attribute' => 'leading.trailing'
							]
						]
					]
				]
			],
			// #3 SAML settings with long values in fields.
			[
				[
					'trim' => true,
					'fields' => [
						'IdP entity ID' => STRING_1024,
						'SSO service URL' => STRING_2048,
						'SLO service URL' => STRING_2048,
						'Username attribute' => STRING_128,
						'SP entity ID' => STRING_1024,
						'SP name ID format' => STRING_2048,
						'Configure JIT provisioning' => true,
						'Group name attribute' => STRING_255,
						'User name attribute' => STRING_255,
						'User last name attribute'=> STRING_255
					],
					'User group mapping' => [
						[
							'SAML group pattern' => STRING_255,
							'User groups' => 'Test timezone',
							'User role' => 'User role'
						]
					],
					'Media type mapping' => [
						[
							// TODO: Change this to 255 long string, if ZBX-22236 is fixed.
							'Name' => '1ong_value_long_value_long_value_long_value_long_value_lon',
							'Media type' => 'Discord',
							'Attribute' => STRING_255
						]
					],
					'db_check' => [
						'userdirectory_saml' => [
							[
								'idp_entityid' => STRING_1024,
								'sso_url' => STRING_2048,
								'slo_url' => STRING_2048,
								'username_attribute' => STRING_128,
								'sp_entityid' => STRING_1024,
								'nameid_format' => STRING_2048,
								'group_name' => STRING_255,
								'user_username' => STRING_255,
								'user_lastname' => STRING_255
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => STRING_255,
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						],
						'userdirectory_media' => [
							[
								'name' => '1ong_value_long_value_long_value_long_value_long_value_lon',
								'mediatypeid' => 71, // Discord
								'attribute' => STRING_255
							]
						]
					]
				]
			],
			// #4 Configure SAML with all parameters, but no JIT configuration.
			[
				[
					'Deprovisioned users group' => 'Disabled',
					'fields' => [
						'Enable JIT provisioning' => true,
						'IdP entity ID' => 'IdP_saml_zabbix.com',
						'SSO service URL' => 'SSO_saml_zabbix.com',
						'SLO service URL' => 'SLO_saml_zabbix.com',
						'Username attribute' => 'Username attribute',
						'SP entity ID' => 'SP entity ID',
						'SP name ID format' => 'SP name ID format',
						// Sign.
						'id:sign_messages' => true,
						'id:sign_assertions' => true,
						'id:sign_authn_requests' => true,
						'id:sign_logout_requests' => true,
						'id:sign_logout_responses' => true,
						// Encrypt.
						'id:encrypt_nameid' => true,
						'id:encrypt_assertions' => true,
						'Case-sensitive login' => true
					],
					'db_check' => [
						'userdirectory_saml' => [
							[
								'idp_entityid' => 'IdP_saml_zabbix.com',
								'sso_url' => 'SSO_saml_zabbix.com',
								'slo_url' => 'SLO_saml_zabbix.com',
								'username_attribute' => 'Username attribute',
								'sp_entityid' => 'SP entity ID',
								'nameid_format' => 'SP name ID format',
								'sign_messages' => 1,
								'sign_assertions' => 1,
								'sign_authn_requests' => 1,
								'sign_logout_requests' => 1,
								'sign_logout_responses' => 1,
								'encrypt_nameid' => 1,
								'encrypt_assertions' => 1
							]
						]
					]
				]
			],
			// #5 Configure SAML SSO with IdP certificate field.
			[
				[
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => self::SSL_CERTIFICATE
					],
					'db_check' => [
						'userdirectory_saml' => [
							[
								'idp_entityid' => 'IdP',
								'sso_url' => 'SSO',
								'username_attribute' => 'UA',
								'sp_entityid' => 'SP',
								'idp_certificate' => self::SSL_CERTIFICATE
							]
						]
					]
				]
			],
			// #6 Configure SAML SSO with all certificates.
			[
				[
					'storage' => 'database',
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'IdP certificate' => self::SSL_CERTIFICATE,
						'SP private key' => self::SSL_PRIVATE_KEY,
						'SP certificate' => self::SSL_CERTIFICATE
					],
					'db_check' => [
						'userdirectory_saml' => [
							[
								'idp_entityid' => 'IdP',
								'sso_url' => 'SSO',
								'username_attribute' => 'UA',
								'sp_entityid' => 'SP',
								'idp_certificate' => self::SSL_CERTIFICATE,
								'sp_private_key' => '',
								'sp_certificate' => ''
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getConfigureValidationData
	 *
	 * @onAfter setSamlCertificatesStorage
	 */
	public function testUsersAuthenticationSaml_ConfigureValidation($data) {
		$this->testSamlConfiguration($data);
	}

	/**
	 * @backup settings
	 *
	 * @dataProvider getConfigureData
	 *
	 * @onAfter setSamlCertificatesStorage
	 */
	public function testUsersAuthenticationSaml_Configure($data) {
		$this->testSamlConfiguration($data);
	}

	private function testSamlConfiguration($data) {
		$old_hash = CDBHelper::getHash('SELECT * FROM settings');
		$this->page->login()->open('zabbix.php?action=authentication.edit');

		// Change storage in frontend configuration file.
		if (array_key_exists('storage', $data)) {
			$this->setSamlCertificatesStorage($data['storage']);
			$this->page->refresh()->waitUntilReady();
		}

		// Check that SAML settings are disabled by default and configure SAML authentication.
		$this->configureSamlAuthentication($data);

		// Check SAML settings update messages and, in case of successful update, check that field values were saved.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update authentication', $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM settings'));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			$form = $this->query('id:authentication-form')->asForm()->one();
			$form->selectTab('SAML settings');
			$this->assertTrue($form->getField('Enable SAML authentication')->isChecked());

			// Trim trailing and leading spaces in expected values before comparison.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data['fields'] = array_map('trim', $data['fields']);
			}

			if (array_key_exists('storage', $data)) {
				foreach (['IdP certificate', 'SP private key', 'SP certificate'] as $field) {
					$button = $form->query('button', 'Change '.$field)->one(false);

					if (array_key_exists($field, $data['fields'])) {
						$this->assertTrue($button->isClickable(), 'Button Change '.$field.' should be clickable.');
					}
					else {
						$this->assertFalse($button->isValid(), 'Button Change '.$field.' should not exists.');
					}
				}

				$sp_buttons = [
					'Change SP private key' => [
						'id' => 'sp_private_key',
						'label' => 'SP private key'
					],
					'Change SP certificate' => [
						'id' => 'sp_certificate',
						'label' => 'SP certificate'
					]
				];

				// Checks for SP buttons, fields behavior and DB content after pressing the buttons.
				foreach ($sp_buttons as $sp_button => $params) {

					// Change SP field only if the corresponding SP button exists.
					if ($form->query('button', $sp_button)->one(false)->isValid()) {
						$form->query('button', $sp_button)->one()->click();

						// Check for empty field after button was pressed.
						$this->assertEquals('', $form->getField('id:'.$params['id'])->getText());
						$form->submit();

						// Check for alert message containing right field name.
						$this->assertEquals('Current '.$params['label'].' will be deleted.', $this->page->getAlertText());
						$this->page->dismissAlert();

						// Check that certificate is not overwritten if alert is dismissed.
						$this->assertEquals($data['fields'][$params['label']],
								CDBHelper::getValue('SELECT '.$params['id'].' FROM userdirectory_saml')
						);
						$this->page->refresh()->waitUntilReady();
						$form->selectTab('SAML settings');
						$form->query('button', $sp_button)->one()->click();
						$form->submit();

						// Check that alert message still contains right field name.
						$this->assertEquals('Current '.$params['label'].' will be deleted.', $this->page->getAlertText());
						$this->page->acceptAlert();

						// Check for success updating message after submitting empty value.
						$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
						$this->page->refresh()->waitUntilReady();
						$form->selectTab('SAML settings');

						// Check that certificate is removed if alert is accepted.
						$this->assertEquals('', $form->getField('id:'.$params['id'])->getText());
					}
				}
			}
			else {
				$form->checkValue($data['fields']);
			}

			foreach ($data['db_check'] as $table => $rows) {
				if ($table === 'settings') {
					$this->assertEquals($rows, CTestDBSettingsHelper::getParameters(array_keys($rows)));
				}
				else {
					foreach ($rows as $i => $row) {
						if (CTestArrayHelper::get($data, 'trim', false)) {
							$rows = array_map('trim', $row);
						}

						$sql = 'SELECT '.implode(",", array_keys($row)).' FROM '.$table.' LIMIT 1 OFFSET '.$i;
						$this->assertEquals([$row], CDBHelper::getAll($sql));
					}
				}
			}
		}
	}

	public function testUsersAuthenticationSaml_CheckStatusChange() {
		$settings = [
			'fields' => [
				'IdP entity ID' => 'IdP',
				'SSO service URL' => 'SSO',
				'Username attribute' => 'UA',
				'SP entity ID' => 'SP'
			]
		];
		$this->page->login()->open('zabbix.php?action=authentication.edit');

		$this->configureSamlAuthentication($settings);

		// Logout and check that SAML authentication was enabled.
		$this->page->logout();
		$this->page->open('index.php')->waitUntilReady();
		$link = $this->query('link:Sign in with Single Sign-On (SAML)')->one()->waitUntilClickable();
		$this->assertStringContainsString('index_sso.php', $link->getAttribute('href'));

		// Login and disable SAML authentication.
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('SAML settings');
		$form->getField('Enable SAML authentication')->uncheck();
		$form->submit();

		// Logout and check that SAML authentication was disabled.
		$this->page->logout();
		$this->page->open('index.php')->waitUntilReady();
		$this->assertTrue($this->query('link:Sign in with Single Sign-On (SAML)')->count() === 0, 'Link must not exist.');
	}

	public function getAuthenticationDetails() {
		return [
			// #0 Login as zabbix super admin - case insensitive login.
			[
				[
					'username' => 'admin'
				]
			],
			// #1 Login as zabbix super admin - case sensitive login.
			[
				[
					'username' => 'Admin',
					'custom_settings' => [
						'Case-sensitive login' => true
					]
				]
			],
			// #2 Login as zabbix user.
			[
				[
					'username' => 'user-zabbix'
				]
			],
			// #3 Login as zabbix admin with custom url after login.
			[
				[
					'username' => 'admin-zabbix',
					'header' => 'Top 100 triggers'
				]
			],
			// #4 Login as zabbix admin with pre-defined login url (has higher priority then the configured url after login).
			[
				[
					'username' => 'admin-zabbix',
					'url' => 'zabbix.php?action=service.list',
					'header' => 'Services'
				]
			],
			// #5 Regular login.
			[
				[
					'username' => 'Admin',
					'regular_login' => true
				]
			],
			// #6 Incorrect IDP.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'admin',
					'custom_settings' => [
						'IdP entity ID' => 'metadata'
					],
					'error_title' => 'You are not logged in',
					'error_details' => 'Invalid issuer in the Assertion/Response'
				]
			],
			// #7 UID exists only on IDP side.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'Admin2',
					'error_title' => 'You are not logged in',
					'error_details' => 'Incorrect user name or password or account is temporarily blocked.'
				]
			],
			// #8 Login as Admin - case sensitive login - negative test.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'admin',
					'custom_settings' => [
						'Case-sensitive login' => true
					],
					'error_title' => 'You are not logged in',
					'error_details' => 'Incorrect user name or password or account is temporarily blocked.'
				]
			]
		];
	}

	/**
	 * @ignoreBrowserErrors
	 * This annotation is put here for avoiding the following errors:
	 * /favicon.ico - Failed to load resource: the server responded with a status of 404 (Not Found).
	 *
	 * @backup settings
	 *
	 * @dataProvider getAuthenticationDetails
	 */
	public function testUsersAuthenticationSaml_Authenticate($data) {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$settings = [
			'fields' => [
				'IdP entity ID' => PHPUNIT_IDP_ENTITY_ID,
				'SSO service URL' => PHPUNIT_SSO_SERVICE_URL,
				'SLO service URL' => PHPUNIT_SLO_SERVICE_URL,
				'Username attribute' => PHPUNIT_USERNAME_ATTRIBUTE,
				'SP entity ID' => PHPUNIT_SP_ENTITY_ID,
				'Case-sensitive login' => false
			]
		];

		// Override particular SAML settings with values from data provider.
		if (array_key_exists('custom_settings', $data)) {
			foreach ($data['custom_settings'] as $key => $value) {
				$settings['fields'][$key] = $value;
			}
		}

		$this->configureSamlAuthentication($settings);

		// Logout and check that SAML authentication was enabled.
		$this->page->logout();

		// Login to a particular url, if such is defined in data provider.
		if (array_key_exists('url', $data)) {
			$this->page->open($data['url'])->waitUntilReady();
			$this->query('button:Login')->one()->click();
			$this->page->waitUntilReady();
		}
		else {
			$this->page->open('index.php')->waitUntilReady();
		}

		// Login via regular Sing-in form or via SAML.
		if (CTestArrayHelper::get($data, 'regular_login', false)) {
			$this->query('id:name')->waitUntilVisible()->one()->fill($data['username']);
			$this->query('id:password')->one()->fill('zabbix');
			$this->query('button:Sign in')->one()->click();
		}
		else {
			$this->query('link:Sign in with Single Sign-On (SAML)')->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$this->query('id:username')->one()->waitUntilVisible()->fill($data['username']);
			$this->query('id:password')->one()->waitUntilVisible()->fill('zabbix');
			$this->query('button:Login')-> one()->click()->waitUntilStalled();
		}

		$this->page->waitUntilReady();

		// Check error message in case of negative test.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['error_title'], $data['error_details']);
			return;
		}
		// Check the header of the page that was displayed to the user after login.
		$header = CTestArrayHelper::get($data, 'header', 'Global view');
		$this->assertEquals($header, $this->query('tag:h1')->one()->getText());

		// Make sure that it is possible to log out.
		$this->query('link:Sign out')->one()->click();
		$this->page->waitUntilReady();
		$this->query('class:signin-logo')->waitUntilVisible()->one();
		$this->assertStringContainsString('index.php', $this->page->getCurrentUrl());
	}

	/**
	 * Function checks that SAML settings are disabled by default, if the corresponding flag is specified, enables and
	 * fills SAML settings, and submits the form.
	 *
	 * @param array    $data    data provider
	 */
	private function configureSamlAuthentication($data) {
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('SAML settings');
		$form->getField('Enable SAML authentication')->check();
		$form->fill($data['fields']);

		if (CTestArrayHelper::get($data['fields'], 'Configure JIT provisioning')) {
			$success = (array_key_exists('mapping_error', $data)) ? false : true;

			if (array_key_exists('User group mapping', $data)) {
				$this->setMapping($data['User group mapping'], $form, 'User group mapping', $success);
			}

			if (array_key_exists('Media type mapping', $data)) {
				$this->setMapping($data['Media type mapping'], $form, 'Media type mapping', $success);
			}
		}

		if (array_key_exists('Deprovisioned users group', $data)) {
			$form->selectTab('Authentication');
			$form->fill(['Deprovisioned users group' => 'Disabled']);
		}

		$form->submit();
		$this->page->waitUntilReady();
	}

	/**
	 * Set CERT_STORAGE variable to frontend configuration file.
	 *
	 * @param string $type	file or database
	 */
	public function setSamlCertificatesStorage($type = 'file') {
		file_put_contents(self::CONF_PATH, '$SSO[\'CERT_STORAGE\']	= \''.$type.'\';'."\n", FILE_APPEND);

		// Wait for frontend to get the new config from updated zabbix.conf.php file.
		sleep((int)ini_get('opcache.revalidate_freq') + 1);
	}

	/**
	 * After test, revert frontend configuration file to its original state.
	 */
	public static function revertConfFile() {
		file_put_contents(self::CONF_PATH, self::$conf_file_content);

		// Wait for frontend to get the new config from updated zabbix.conf.php file.
		sleep((int)ini_get('opcache.revalidate_freq') + 1);
	}
}
