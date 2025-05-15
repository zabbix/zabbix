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

require_once dirname(__FILE__) . '/../include/CAPITest.php';

/**
 * @onBefore  prepareTestData
 *
 * @onAfter cleanTestData
 * @backup userdirectory
 */
class testUserDirectorySamlCertificates extends CAPITest {

	private static array $data = [
		'userdirectoryid' => []
	];

	private static array $hashed_fields = [
		'idp_certificate' => 'idp_certificate_hash',
		'sp_certificate' => 'sp_certificate_hash',
		'sp_private_key' => 'sp_private_key_hash'
	];

	public static function updateValidDataProvider() {
		$certificates = self::rawCertificates();
		$hashed_certificates = self::hashedCertificates();

		return [
			'idp_certificate set empty value' => [
				'key' => 'idp_certificate', 'value' => '', 'expected result' => '', 'expected_error' => null
			],
			'sp_certificate set empty value' => [
				'key' => 'sp_certificate', 'value' => '', 'expected result' => '', 'expected_error' => null
			],
			'sp_private key set empty value' => [
				'key' => 'sp_private_key', 'value' => '', 'expected result' => '', 'expected_error' => null
			],
			'idp_certificate set valid format' => [
				'key' => 'idp_certificate',
				'value' => $certificates['idp_certificate'],
				'expected result' => $hashed_certificates['idp_certificate_hash'],
				'expected_error' => null
			],
			'sp_certificate set valid format' => [
				'key' => 'sp_certificate',
				'value' => $certificates['sp_certificate'],
				'expected result' => $hashed_certificates['sp_certificate_hash'],
				'expected_error' => null
			],
			'sp_private_key set valid format' => [
				'key' => 'sp_private_key',
				'value' => $certificates['sp_private_key'],
				'expected result' => $hashed_certificates['sp_private_key_hash'],
				'expected_error' => null
			]
		];
	}

	public static function updateInvalidDataProvider() {
		return [
			'idp_certificate set invalid format' => [
				'key' => 'idp_certificate',
				'value' => 'lorem ipsum dolor sit amet, consectetur adipiscing elit 1',
				'expected result' => null,
				'expected_error' => 'Invalid parameter "/1/idp_certificate": value is not PEM encoded certificate.'
			],
			'sp_certificate set invalid format' => [
				'key' => 'sp_certificate',
				'value' => 'lorem ipsum dolor sit amet, consectetur adipiscing elit 2',
				'expected result' => null,
				'expected_error' => 'Invalid parameter "/1/sp_certificate": value is not PEM encoded certificate.'
			],
			'sp_private_key set invalid format' => [
				'key' => 'sp_private_key',
				'value' => 'lorem ipsum dolor sit amet, consectetur adipiscing elit 3',
				'expected result' => null,
				'expected_error' => 'Invalid parameter "/1/sp_private_key": value is not PEM encoded private key.'
			],
			'idp_certificate set invalid length' => [
				'key' => 'idp_certificate',
				'value' => str_repeat('abc123 ', 1429),
				'expected result' => null,
				'expected_error' => 'Invalid parameter "/1/idp_certificate": value is too long.'
			],
			'sp_certificate set invalid length' => [
				'key' => 'sp_certificate',
				'value' => str_repeat('abc345 ', 1429),
				'expected result' => null,
				'expected_error' => 'Invalid parameter "/1/sp_certificate": value is too long.'
			],
			'sp_private_key set invalid length' => [
				'key' => 'sp_private_key',
				'value' => str_repeat('abc567 ', 1429),
				'expected result' => null,
				'expected_error' => 'Invalid parameter "/1/sp_private_key": value is too long.'
			]
		];
	}

	public function testOpenSslExtensionIsInstalled(): void {
		$this->assertTrue(extension_loaded('openssl'), 'PHP OpenSSL extension missing.');
	}

	public function testGetCertificatesHashedValues() {
		$hashed_certificates = self::hashedCertificates();
		$userdirectoryid = self::$data['userdirectoryid']['API SAML'];

		$db_userdirectory = $this->call('userdirectory.get', [
			'output' => self::$hashed_fields,
			'filter' => ['idp_type' => IDP_TYPE_SAML],
			'userdirectoryids' => [$userdirectoryid]
		]);

		$db_userdirectory = reset($db_userdirectory['result']);
		$this->assertSame($db_userdirectory, $hashed_certificates);
	}

	/**
	 * @dataProvider updateValidDataProvider
	 * @dataProvider updateInvalidDataProvider
	 */
	public function testUpdateCertificates(string $key, string $value, ?string $expected_result, ?string $expected_error) {
		$userdirectoryid = self::$data['userdirectoryid']['API SAML'];
		$this->call('userdirectory.update',['userdirectoryid' => $userdirectoryid, $key => $value], $expected_error);

		if ($expected_error === null)  {
			$db_userdirectory = $this->call('userdirectory.get', [
				'output' => [self::$hashed_fields[$key]],
				'filter' => ['idp_type' => IDP_TYPE_SAML],
				'userdirectoryids' => [$userdirectoryid]
			]);

			$db_userdirectory = reset($db_userdirectory['result']);
			$this->assertSame($db_userdirectory[self::$hashed_fields[$key]], $expected_result);
		}
	}

	public function prepareTestData() {
		$data = [
			'name' => 'API SAML',
			'idp_type' => IDP_TYPE_SAML,
			'group_name' => 'Groups',
			'idp_entityid' => 'http://www.okta.com/abcdef',
			'sso_url' => 'https://www.okta.com/ghijkl',
			'username_attribute' => 'usrEmail',
			'provision_status' => JIT_PROVISIONING_ENABLED,
			'sp_entityid' => '',
			'provision_media' => [
				[
					'name' => 'SMS',
					'mediatypeid' => '1',
					'attribute' => 'mobile_phone'
				]
			],
			'provision_groups' => [
				[
					'name' => 'group name',
					'roleid' => 1,
					'user_groups' => [
						['usrgrpid' => 7]
					]
				]
			],
			'scim_status' => 1
		] + self::rawCertificates();

		$response = $this->call('userdirectory.create', $data);
		self::$data['userdirectoryid'][$data['name']] = $response['result']['userdirectoryids'][0];
	}

	public static function cleanTestData() {
		$userdirectoryid = self::$data['userdirectoryid']['API SAML'];
		CDataHelper::call('authentication.update', ['saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED]);
		CDataHelper::call('userdirectory.delete',[$userdirectoryid]);
	}

	private static function hashedCertificates(): array
	{
		return [
			'idp_certificate_hash' => '37a33c1994ef4e6326ee2a5dd0856ce0',
			'sp_certificate_hash' => '37a33c1994ef4e6326ee2a5dd0856ce0',
			'sp_private_key_hash' => '3376914a2891a33f6dc9cb3d7fb518cd'
		];
	}

	private static function rawCertificates(): array
	{
		return [
			'idp_certificate' => '-----BEGIN CERTIFICATE-----
MIID+TCCAuGgAwIBAgIUSpDnLjL2DVS0YTRGOQh+MMoUtDowDQYJKoZIhvcNAQEL
BQAwgYsxCzAJBgNVBAYTAlBMMQ0wCwYDVQQIDARXcm9jMRAwDgYDVQQHDAdXcm9j
bGF3MQ8wDQYDVQQKDAZaYWJiaXgxCzAJBgNVBAsMAklUMRUwEwYDVQQDDAxJcnlu
YSBTaGFyaGExJjAkBgkqhkiG9w0BCQEWF2lyeW5hLnNoYXJoYUB6YWJiaXguY29t
MB4XDTI1MDQyNDE2NDE0M1oXDTI2MDQyNDE2NDE0M1owgYsxCzAJBgNVBAYTAlBM
MQ0wCwYDVQQIDARXcm9jMRAwDgYDVQQHDAdXcm9jbGF3MQ8wDQYDVQQKDAZaYWJi
aXgxCzAJBgNVBAsMAklUMRUwEwYDVQQDDAxJcnluYSBTaGFyaGExJjAkBgkqhkiG
9w0BCQEWF2lyeW5hLnNoYXJoYUB6YWJiaXguY29tMIIBIjANBgkqhkiG9w0BAQEF
AAOCAQ8AMIIBCgKCAQEAvHwPw8t5wB6e73ciAJ6LrHFSKRjgMQlGP31Sku/g8pTA
8dFbblBj/yXKPkyqrnO1EvBoZB330HqRnlarXsstCFcC8ESQ+EzlB0737dc0jDdy
WD3MsN2+YZRisKtaFwdswnYd23D4A6ymEYtjCAgKcpPJ4ciX+aZUkjS6BkMqyeGq
zm0ig9GYwC8OsfG0ZxWV0s8m8MwC0DDPGnTSeFuCRwVftwqIjZOPocm2xpuWXQzF
e2k4C5GofJ8BW0hNYeyzxnI+eOJHpgamtNlA5MeIcSTrtpGrqmm3XGz1H8F27kVi
rrLVdfLcy1BYxb7I0eca4YjByvqqWrWukFq4Xs+/cQIDAQABo1MwUTAdBgNVHQ4E
FgQUd9PRJ5ORONgZzkgVUE9SpCpeW9IwHwYDVR0jBBgwFoAUd9PRJ5ORONgZzkgV
UE9SpCpeW9IwDwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0BAQsFAAOCAQEAV8dE
Al+/w/8eOEqAlSeS9g2+g+4hvAAkTtp8HfWzMiqy56ZoAtzMOY4A/1QINZn4gvUk
VW+SCr2X/AAqW7rIXFEbng7LyfwPUYJ++L1/aRqlEIuSvCmwa3Ypj6PqtN1RepEL
jSXIQ/c5h+R+e4MUGU+mhS0evonfGaklB9xVz+amOMhU0Ag04Sp3HH+MayqWrkJP
ntEXfn6G1X/mRSefF2k3UC5gZRYsWRybYtmtddrZcSApMedpx6YjtpFAd6+Z2UlL
XlyFUVaZU/mT+orYNshgWEjBR2Mra1m0MKC1yWLG/eS7OUdzYLTyl0rClB5M0YFe
pYDypczpOVk8mPLcZg==
-----END CERTIFICATE-----',
			'sp_certificate' => '-----BEGIN CERTIFICATE-----
MIID+TCCAuGgAwIBAgIUSpDnLjL2DVS0YTRGOQh+MMoUtDowDQYJKoZIhvcNAQEL
BQAwgYsxCzAJBgNVBAYTAlBMMQ0wCwYDVQQIDARXcm9jMRAwDgYDVQQHDAdXcm9j
bGF3MQ8wDQYDVQQKDAZaYWJiaXgxCzAJBgNVBAsMAklUMRUwEwYDVQQDDAxJcnlu
YSBTaGFyaGExJjAkBgkqhkiG9w0BCQEWF2lyeW5hLnNoYXJoYUB6YWJiaXguY29t
MB4XDTI1MDQyNDE2NDE0M1oXDTI2MDQyNDE2NDE0M1owgYsxCzAJBgNVBAYTAlBM
MQ0wCwYDVQQIDARXcm9jMRAwDgYDVQQHDAdXcm9jbGF3MQ8wDQYDVQQKDAZaYWJi
aXgxCzAJBgNVBAsMAklUMRUwEwYDVQQDDAxJcnluYSBTaGFyaGExJjAkBgkqhkiG
9w0BCQEWF2lyeW5hLnNoYXJoYUB6YWJiaXguY29tMIIBIjANBgkqhkiG9w0BAQEF
AAOCAQ8AMIIBCgKCAQEAvHwPw8t5wB6e73ciAJ6LrHFSKRjgMQlGP31Sku/g8pTA
8dFbblBj/yXKPkyqrnO1EvBoZB330HqRnlarXsstCFcC8ESQ+EzlB0737dc0jDdy
WD3MsN2+YZRisKtaFwdswnYd23D4A6ymEYtjCAgKcpPJ4ciX+aZUkjS6BkMqyeGq
zm0ig9GYwC8OsfG0ZxWV0s8m8MwC0DDPGnTSeFuCRwVftwqIjZOPocm2xpuWXQzF
e2k4C5GofJ8BW0hNYeyzxnI+eOJHpgamtNlA5MeIcSTrtpGrqmm3XGz1H8F27kVi
rrLVdfLcy1BYxb7I0eca4YjByvqqWrWukFq4Xs+/cQIDAQABo1MwUTAdBgNVHQ4E
FgQUd9PRJ5ORONgZzkgVUE9SpCpeW9IwHwYDVR0jBBgwFoAUd9PRJ5ORONgZzkgV
UE9SpCpeW9IwDwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0BAQsFAAOCAQEAV8dE
Al+/w/8eOEqAlSeS9g2+g+4hvAAkTtp8HfWzMiqy56ZoAtzMOY4A/1QINZn4gvUk
VW+SCr2X/AAqW7rIXFEbng7LyfwPUYJ++L1/aRqlEIuSvCmwa3Ypj6PqtN1RepEL
jSXIQ/c5h+R+e4MUGU+mhS0evonfGaklB9xVz+amOMhU0Ag04Sp3HH+MayqWrkJP
ntEXfn6G1X/mRSefF2k3UC5gZRYsWRybYtmtddrZcSApMedpx6YjtpFAd6+Z2UlL
XlyFUVaZU/mT+orYNshgWEjBR2Mra1m0MKC1yWLG/eS7OUdzYLTyl0rClB5M0YFe
pYDypczpOVk8mPLcZg==
-----END CERTIFICATE-----',
			'sp_private_key' => '-----BEGIN PRIVATE KEY-----
MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQC8fA/Dy3nAHp7v
dyIAnouscVIpGOAxCUY/fVKS7+DylMDx0VtuUGP/Jco+TKquc7US8GhkHffQepGe
Vqteyy0IVwLwRJD4TOUHTvft1zSMN3JYPcyw3b5hlGKwq1oXB2zCdh3bcPgDrKYR
i2MICApyk8nhyJf5plSSNLoGQyrJ4arObSKD0ZjALw6x8bRnFZXSzybwzALQMM8a
dNJ4W4JHBV+3CoiNk4+hybbGm5ZdDMV7aTgLkah8nwFbSE1h7LPGcj544kemBqa0
2UDkx4hxJOu2kauqabdcbPUfwXbuRWKustV18tzLUFjFvsjR5xrhiMHK+qpata6Q
Wrhez79xAgMBAAECggEAANK9KIGvl4t57hWbTIex8amdpKrczfY2co+SMAgVtpe8
UGfmgcOGMwLIweu1Tqb3p7QJTL7UigiM2bVWKe/Y9iVKsj1jcGouo7N5+zzTc7Je
tclteBvu7j0j3g+l+DcIZWtIT+0EsUyO/8Fc/PEYTXGI9Kef86FEACIrN2DROnn2
Ek4R1Lg/92dq10bpnYTMT8txctREQ2gQTXRuNUGIUACUzdoXARNqGth6+k9+gWYh
IpXiCoBz5Sh8bS/QveehEZtdB/j+/iCcVBjuxwsxNcN7Iq9TKuPoC5/pOa+KoxXC
kss1mU5A+boJMSL0lZHsFaaGJINliNBPqGZRkU2xxwKBgQD5XtqpYrWFRiYrX1YM
nFfgIzl69h9EufI/DRHwFObFz7gvaDyQf0HMPT52YwEJiZSwaES+E4fFrmPxg53V
VbdkBL3LsAXGZqJqQjHuWw0Lx756jd7mABAF4CIR2d9hk3YAA1+d8djsSLqN2xl4
ptQoxehZLAQzCQkiMC0KxFSnowKBgQDBftXDnZWGUTlezunur/HBymiHdMzUTHsk
7CgUEOEfaA6eu1yA7udyVsSbhss0AFZgqCevb90J8iBnLnQNqte+gY3qglY1L3od
9Yv4kvDTGgdAesafiivo+TY3g6JD14M/2LbUutN6kWywLeJGSwgnJwAWSjznA6VM
TW5+WOu92wKBgQC8//ZMYSLg0u0E/GnUfv5fQ3NCTZ4fUatXvEk3JDBQBoI7dA5L
Ghg9esGHqrvThbHrDevkABtsaSMYnj+WvDOVm75ZzZxi5dD9JhR/6gR2RDqK2lHx
EmUSfvBzhSS36LKLigMDS5S0aN7zuvaQKiksierzAthf8d45SjgpK+pZbwKBgQCT
GPctNPldGRaCKs7Qc9VYO6XnhDXLFzFuylFVn9dk5thmd41FP1mYJLpmeby1FaSU
6oDw8Bub2gQkLL5xPXWyEA9xPhCHckZlzCvSlvKZqWnl7PBejM4A2KQM4/dRl97h
hMDJTBZFUZTNArTIN3ZFPXLlfx55iN36+cqMJtFgjQKBgQDcffg1rc/ayzuJd7Ym
OzQ7joemEK5DIDRxryFxWnDXLrAZA1V+iUiKESIX1E8TGSAMymwUW2nWWCuhUps6
pFw9z8Z3AaerRZA5fl655v500jUqziwBfifSimNL0hzmZfG6XUt6F7y4rxa2HFuu
uwMrOBKatg7CZ1Uenv1K3ioD5w==
-----END PRIVATE KEY-----'
		];
	}
}
