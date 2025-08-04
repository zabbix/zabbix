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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @onBefore getConfFileContent, setSamlCertificatesStorage, prepareTestData
 *
 * @onAfter cleanTestData, revertConfFile
 * @backup userdirectory
 */
class testUserDirectory extends CAPITest {

	const CONF_PATH = __DIR__.'/../../conf/zabbix.conf.php';
	protected static $conf_file_content;

	const SSL_CERTIFICATE = '-----BEGIN CERTIFICATE-----
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
-----END CERTIFICATE-----';

	const SSL_PRIVATE_KEY = '-----BEGIN PRIVATE KEY-----
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
-----END PRIVATE KEY-----';

	public static $data = [
		'usrgrpid' => [],
		'userdirectoryid' => []
	];

	/**
	 * The original contents of frontend configuration file before test.
	 */
	public function getConfFileContent() {
		self::$conf_file_content = file_get_contents(self::CONF_PATH);
	}

	public static function createValidDataProvider() {
		return [
			'Create LDAP userdirectories' => [
				'userdirectories' => [
					['name' => 'LDAP #1', 'idp_type' => IDP_TYPE_LDAP, 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid'],
					['name' => 'LDAP #2', 'idp_type' => IDP_TYPE_LDAP, 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid']
				],
				'expected_error' => null
			],
			'Create LDAP userdirectories with provisioning groups and media' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [
						['name' => 'zabbix-devs', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]],
						['name' => 'zabbix-marketing', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]],
						['name' => 'zabbix-qa', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]],
						['name' => 'zabbix-sales', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]]
					],
					'provision_media' => [
						['name' => 'SMS', 'mediatypeid' => 1, 'attribute' => 'attr_sms'],
						['name' => 'Email', 'mediatypeid' => 1, 'attribute' => 'attr_email']
					]
				]],
				'expected_error' => null
			],
			'Create LDAP userdirectories with provisioning groups and media with additional fields' => [
				'userdirectories' => [[
					'name' => 'LDAP #4',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [
						['name' => 'zabbix-devs', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]]
					],
					'provision_media' => [
						['name' => 'Media #1', 'mediatypeid' => 1, 'attribute' => 'active, severity, period set default'],
						['name' => 'Media #2', 'mediatypeid' => 1, 'attribute' => 'attr_media2', 'active' => MEDIA_STATUS_ACTIVE],
						['name' => 'Media #3', 'mediatypeid' => 1, 'attribute' => 'attr_media3', 'severity' => 3],
						['name' => 'Media #4', 'mediatypeid' => 1, 'attribute' => 'attr_media4', 'period' => '2-5,09:00-15:00'],
						['name' => 'Media #5', 'mediatypeid' => 1, 'attribute' => 'attr_media5', 'period' => '{$MACRO}'],
						['name' => 'Media #6', 'mediatypeid' => 1, 'attribute' => 'attr_media6', 'period' => '{$MACRO:A}'],
						['name' => 'Media #7', 'mediatypeid' => 1, 'attribute' => 'attr_media7', 'period' => '{{$MACRO}.func()}']
					]
				]],
				'expected_error' => null
			]
		];
	}

	public static function createInvalidDataProvider() {
		return [
			'Test duplicate names in one request' => [
				'userdirectories' => [
					['name' => 'LDAP #1', 'idp_type' => IDP_TYPE_LDAP, 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid'],
					['name' => 'LDAP #1', 'idp_type' => IDP_TYPE_LDAP, 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid']
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(LDAP #1) already exists.'
			],
			'Test duplicate name' => [
				'userdirectories' => [
					['name' => 'LDAP #1', 'idp_type' => IDP_TYPE_LDAP, 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid']
				],
				'expected_error' => 'User directory "LDAP #1" already exists.'
			],
			'Test missing idp_type' => [
				'userdirectories' => [
					['name' => 'LDAP #3']
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "idp_type" is missing.'
			],
			'Test provision groups' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => []
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
			],
			'Test missing provision group name' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 1]]
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1": the parameter "name" is missing.'
			],
			'Test empty provision group name' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => '',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 1]]
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1/name": cannot be empty.'
			],
			'Test non-string provision group name' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => [],
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 1]]
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1/name": a character string is expected.'
			],
			'Test non-existing provision group roleid' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'user_groups' => [['usrgrpid' => 1]]
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1": the parameter "roleid" is missing.'
			],
			'Test invalid provision group roleid' => [
				'userdirectories' => [[
					'name' => 'LDAP NEW',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 0,
						'user_groups' => [['usrgrpid' => 1]]
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1/roleid": object does not exist.'
			],
			'Test non-existing provision group user groups' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1": the parameter "user_groups" is missing.'
			],
			'Test empty provision group user groups' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => []
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1/user_groups": cannot be empty.'
			],
			'Test invalid provision group user groups' => [
				'userdirectories' => [[
					'name' => 'LDAP NEW',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 0]]
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1/user_groups/1": object does not exist.'
			],
			'Test non-unique provision group user groups' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 7], ['usrgrpid' => 7]]
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups/1/user_groups/2": value (usrgrpid)=(7) already exists.'
			],
			'Test multiple SAML user directories' => [
				'userdirectories' => [
					['name' => 'SAML #1', 'idp_type' => IDP_TYPE_SAML],
					['name' => 'SAML #2', 'idp_type' => IDP_TYPE_SAML]
				],
				'expected_error' => 'Only one user directory of type "2" can exist.'
			],
			'Test missing provision media details' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 7]]
					]],
					'provision_media' => [[]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/1": the parameter "name" is missing.'
			],
			'Test missing provision media mediatypeid' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 7]]
					]],
					'provision_media' => [[
						'name' => 'name',
						'attribute' => 'attr'
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/1": the parameter "mediatypeid" is missing.'
			],
			'Test invalid provision media mediatypeid' => [
				'userdirectories' => [[
					'name' => 'LDAP NEW',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 7]]
					]],
					'provision_media' => [[
						'name' => 'name',
						'mediatypeid' => 0,
						'attribute' => 'attr'
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/mediatypeid": object does not exist.'
			],
			'Test invalid provision media active' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 7]]
					]],
					'provision_media' => [[
						'name' => 'name',
						'mediatypeid' => 0,
						'attribute' => 'attr',
						'active' => 2
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/active": value must be one of 0, 1.'
			],
			'Test invalid provision media severity' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 7]]
					]],
					'provision_media' => [[
						'name' => 'name',
						'mediatypeid' => 0,
						'attribute' => 'attr',
						'severity' => 64
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/severity": value must be one of 0-63.'
			],
			'Test invalid provision media period' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 7]]
					]],
					'provision_media' => [[
						'name' => 'name',
						'mediatypeid' => 0,
						'attribute' => 'attr',
						'period' => 'malformed period'
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/period": a time period is expected.'
			],
			'Test invalid provision media empty period' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [[
						'name' => 'provision group pattern',
						'roleid' => 1,
						'user_groups' => [['usrgrpid' => 7]]
					]],
					'provision_media' => [[
						'name' => 'name',
						'mediatypeid' => 0,
						'attribute' => 'attr',
						'period' => ''
					]]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/period": cannot be empty.'
			],
			'Test invalid provision media userdirectory_mediaid=0' => [
				'userdirectories' => [[
					'name' => 'LDAP #3',
					'idp_type' => IDP_TYPE_LDAP,
					'host' => 'ldap.forumsys.com',
					'port' => 389,
					'base_dn' => 'dc=example,dc=com',
					'search_attribute' => 'uid',
					'provision_status' => JIT_PROVISIONING_ENABLED,
					'provision_groups' => [
						['name' => 'zabbix-devs', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]]
					],
					'provision_media' => [
						['userdirecotry_mediaid' => 0, 'name' => 'Media #2', 'mediatypeid' => 1, 'attribute' => 'attr_media2', 'active' => MEDIA_STATUS_ACTIVE]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/1": unexpected parameter "userdirecotry_mediaid".'
			]
		];
	}

	/**
	 * @dataProvider createValidDataProvider
	 * @dataProvider createInvalidDataProvider
	 */
	public function testCreate($userdirectories, $expected_error) {
		$response = $this->call('userdirectory.create', $userdirectories, $expected_error);

		if ($expected_error === null) {
			self::$data['userdirectoryid'] += array_combine(array_column($userdirectories, 'name'),
				$response['result']['userdirectoryids']
			);
		}
	}

	public static function updateValidDataProvider() {
		return [
			'Test host update' => [
				'userdirectories' => [
					['userdirectoryid' => 'LDAP #1', 'host' => 'localhost']
				],
				'expected_error' => null
			],
			'Test valid SAML Sign messages' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sign_messages' => 1]
				],
				'expected_error' => null
			],
			'Test valid SAML Sign assertions' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sign_assertions' => 1]
				],
				'expected_error' => null
			],
			'Test valid SAML Sign authN requests' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sign_authn_requests' => 1]
				],
				'expected_error' => null
			],
			'Test valid SAML Sign logout requests' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sign_logout_requests' => 1]
				],
				'expected_error' => null
			],
			'Test valid SAML Sign logout responses' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sign_logout_responses' => 1]
				],
				'expected_error' => null
			],
			'Test valid SAML Encrypt name ID' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'encrypt_nameid' => 1]
				],
				'expected_error' => null
			],
			'Test valid SAML Encrypt assertions' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'encrypt_assertions' => 1]
				],
				'expected_error' => null
			],
			'Test valid SAML SP name ID format' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'nameid_format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient']
				],
				'expected_error' => null
			],
			'Test valid SAML IdP entity ID' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'idp_entityid' => 'saml.idp.entity.id']
				],
				'expected_error' => null
			],
			'Test valid SAML SSO service URL' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sso_url' => 'saml.sso.url']
				],
				'expected_error' => null
			],
			'Test valid SAML SLO service URL' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'slo_url' => 'saml.slo.url']
				],
				'expected_error' => null
			],
			'Test valid SAML Username attribute' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'username_attribute' => 'saml.username.attribute']
				],
				'expected_error' => null
			],
			'Test valid SAML SP entity ID' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sp_entityid' => 'saml.sp.entityid']
				],
				'expected_error' => null
			],
			'Test valid SAML IdP certificate' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'idp_certificate' => self::SSL_CERTIFICATE]
				],
				'expected_error' => null
			],
			'Test valid SAML SP certificate' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sp_certificate' => self::SSL_CERTIFICATE]
				],
				'expected_error' => null
			],
			'Test valid SAML SP private key' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sp_private_key' => self::SSL_PRIVATE_KEY]
				],
				'expected_error' => null
			]
		];
	}

	public static function updateInvalidDataProvider() {
		return [
			'Test duplicate name update' => [
				'userdirectories' => [
					['userdirectoryid' => 'LDAP #1', 'name' => 'LDAP #2']
				],
				'expected_error' => 'User directory "LDAP #2" already exists.'
			],
			'Test duplicate names cross name update' => [
				'userdirectories' => [
					['userdirectoryid' => 'LDAP #1', 'name' => 'LDAP #2'],
					['userdirectoryid' => 'LDAP #2', 'name' => 'LDAP #1']
				],
				'expected_error' => 'User directory "LDAP #1" already exists.'
			],
			'Test update not existing' => [
				'userdirectories' => [
					['userdirectoryid' => 1234, 'name' => 'LDAP #1234']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test host changes requires bind_password when db bind_password is not empty' => [
				'userdirectories' => [
					['userdirectoryid' => 'API LDAP #4', 'host' => 'test.host.com']
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "bind_password" is missing.'
			],
			'Test idp_type change' => [
				'userdirectories' => [
					['userdirectoryid' => 'LDAP #1', 'idp_type' => IDP_TYPE_SAML]
				],
				'expected_error' => 'Invalid parameter "/1/idp_type": cannot be changed.'
			],
			'Check of provision groups can be removed' => [
				'userdirectories' => [
					['userdirectoryid' => 'LDAP #3', 'provision_groups' => []]
				],
				'expected_error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
			],
			'Set SAML specific field to LDAP user directory' => [
				'userdirectories' => [
					['userdirectoryid' => 'LDAP #1', 'idp_entityid' => 'zabbix']
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "idp_entityid".'
			],
			'Set provision groups without enabling provisioning' => [
				'userdirectories' => [[
					'userdirectoryid' => 'LDAP #1',
					'provision_groups' => [
						['name' => 'zabbix-devs', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups": should be empty.'
			],
			'Enable provisioning without giving provision groups' => [
				'userdirectories' => [[
					'userdirectoryid' => 'LDAP #1',
					'provision_status' => JIT_PROVISIONING_ENABLED
				]],
				'expected_error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
			],
			'Set non-existing mediaid to provision media' => [
				'userdirectories' => [[
					'userdirectoryid' => 'LDAP #3',
					'provision_media' => [
						['name' => 'SMS', 'mediatypeid' => 1, 'attribute' => 'attr_sms'],
						['name' => 'Email', 'mediatypeid' => 100000, 'attribute' => 'attr_email']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/provision_media/2/mediatypeid": object does not exist.'
			],
			'Set incorrect provision media active' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'LDAP #3',
						'provision_media' => [
							['name' => 'Media #1', 'mediatypeid' => 1, 'attribute' => 'attr_media1', 'active' => 7]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/active": value must be one of 0, 1.'
			],
			'Set incorrect provision media severity' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'LDAP #3',
						'provision_media' => [
							['name' => 'Media #2', 'mediatypeid' => 1, 'attribute' => 'attr_media2', 'severity' => 64]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/severity": value must be one of 0-63.'
			],
			'Set incorrect provision media period' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'LDAP #3',
						'provision_media' => [
							['name' => 'Media #3', 'mediatypeid' => 1, 'attribute' => 'attr_media3', 'period' => 'malformed period']
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/period": a time period is expected.'
			],
			'Set incorrect provision media userdirectory_mediaid=0' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'LDAP #3',
						'provision_media' => [
							['userdirectory_mediaid' => 0, 'name' => 'Media #1', 'mediatypeid' => 1, 'attribute' => 'attr_media1']
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/provision_media/1/userdirectory_mediaid": object does not exist or belongs to another object.'
			],
			'Test invalid SAML Encrypt assertions' => [
				'userdirectories' => [[
					'userdirectoryid' => 'API SAML',
					'encrypt_assertions' => 999
				]],
				'expected_error' => 'Invalid parameter "/1/encrypt_assertions": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Encrypt name ID' => [
				'userdirectories' => [[
					'userdirectoryid' => 'API SAML',
					'encrypt_nameid' => 999
				]],
				'expected_error' => 'Invalid parameter "/1/encrypt_nameid": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign logout responses' => [
				'userdirectories' => [[
					'userdirectoryid' => 'API SAML',
					'sign_logout_responses' => 999
				]],
				'expected_error' => 'Invalid parameter "/1/sign_logout_responses": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign authN requests' => [
				'userdirectories' => [[
					'userdirectoryid' => 'API SAML',
					'sign_authn_requests' => 999
				]],
				'expected_error' => 'Invalid parameter "/1/sign_authn_requests": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign logout requests' => [
				'userdirectories' => [[
					'userdirectoryid' => 'API SAML',
					'sign_logout_requests' => 999
				]],
				'expected_error' => 'Invalid parameter "/1/sign_logout_requests": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign assertions' => [
				'userdirectories' => [[
					'userdirectoryid' => 'API SAML',
					'sign_assertions' => 999
				]],
				'expected_error' => 'Invalid parameter "/1/sign_assertions": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign messages' => [
				'userdirectories' => [[
					'userdirectoryid' => 'API SAML',
					'sign_messages' => 999
				]],
				'expected_error' => 'Invalid parameter "/1/sign_messages": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML SP name ID format' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'nameid_format' => 1]
				],
				'expected_error' => 'Invalid parameter "/1/nameid_format": a character string is expected.'
			],
			'Test SAML IdP certificate is required' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'idp_certificate' => '']
				],
				'expected_error' => 'Invalid parameter "/1/idp_certificate": cannot be empty.'
			],
			'Test invalid SAML IdP certificate format' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'API SAML',
						'idp_certificate' => 'lorem ipsum dolor sit amet, consectetur adipiscing elit'
					]
				],
				'expected_error' => 'Invalid parameter "/1/idp_certificate": a PEM-encoded certificate is expected.'
			],
			'Test SAML IdP certificate string format' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'idp_certificate' => 123]
				],
				'expected_error' => 'Invalid parameter "/1/idp_certificate": a character string is expected.'
			],
			'Test invalid SAML IdP certificate length' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'idp_certificate' => str_repeat('abc123 ', 1429)]
				],
				'expected_error' => 'Invalid parameter "/1/idp_certificate": value is too long.'
			],
			'Test SAML SP certificate is required when sign_* options are enabled' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'API SAML',
						'sp_certificate' => '',
						'sign_assertions' => 1,
						'sign_authn_requests' => 1
					]
				],
				'expected_error' => 'Invalid parameter "/1/sp_certificate": cannot be empty.'
			],
			'Test SAML SP certificate is required when one of encrypt_* options is enabled' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'API SAML',
						'sp_certificate' => '',
						'encrypt_nameid' => 1
					]
				],
				'expected_error' => 'Invalid parameter "/1/sp_certificate": cannot be empty.'
			],
			'Test invalid SAML SP certificate format' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'API SAML',
						'sp_certificate' => 'lorem ipsum dolor sit amet, consectetur adipiscing elit'
					]
				],
				'expected_error' => 'Invalid parameter "/1/sp_certificate": a PEM-encoded certificate is expected.'
			],
			'Test SAML SP certificate string format' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sp_certificate' => 123]
				],
				'expected_error' => 'Invalid parameter "/1/sp_certificate": a character string is expected.'
			],
			'Test invalid SAML SP certificate length' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sp_certificate' => str_repeat('abc123 ', 1429)]
				],
				'expected_error' => 'Invalid parameter "/1/sp_certificate": value is too long.'
			],
			'Test SAML SP private key is required when sign_* options are enabled' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'API SAML',
						'sp_private_key' => '',
						'sign_assertions' => 1,
						'sign_authn_requests' => 1
					]
				],
				'expected_error' => 'Invalid parameter "/1/sp_private_key": cannot be empty.'
			],
			'SAML SP private key is required when one of encrypt_* options is enabled' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'API SAML',
						'sp_private_key' => '',
						'encrypt_nameid' => 1
					]
				],
				'expected_error' => 'Invalid parameter "/1/sp_private_key": cannot be empty.'
			],
			'Test invalid SAML SP private key format' => [
				'userdirectories' => [
					[
						'userdirectoryid' => 'API SAML',
						'sp_private_key' => 'lorem ipsum dolor sit amet, consectetur adipiscing elit'
					]
				],
				'expected_error' => 'Invalid parameter "/1/sp_private_key": a PEM-encoded private key is expected.'
			],
			'Test SAML SP private key string format' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sp_private_key' => 123]
				],
				'expected_error' => 'Invalid parameter "/1/sp_private_key": a character string is expected.'
			],
			'Test invalid SAML SP private key length' => [
				'userdirectories' => [
					['userdirectoryid' => 'API SAML', 'sp_private_key' => str_repeat('abc123 ', 1429)]
				],
				'expected_error' => 'Invalid parameter "/1/sp_private_key": value is too long.'
			]
		];
	}

	/**
	 * @dataProvider updateInvalidDataProvider
	 * @dataProvider updateValidDataProvider
	 */
	public function testUpdate(array $userdirectories, $expected_error) {
		static $samlStorageInitialized = false;

		if (!$samlStorageInitialized) {
			$this->setSamlCertificatesStorage('database');
			$samlStorageInitialized = true;
		}

		$userdirectories = self::resolveIds($userdirectories);
		$this->call('userdirectory.update', $userdirectories, $expected_error);

		if ($expected_error === null) {
			foreach ($userdirectories as $userdirectory) {
				if (array_key_exists('name', $userdirectory)) {
					self::$data['userdirectoryid'][$userdirectory['name']] = $userdirectory['userdirectoryid'];
				}
			}
		}
	}

	/**
	 * Test userdirectory provision_media userdirectory_mediaid field changes.
	 * For userdirectory.update operation value of provisioned media userdirectory_mediaid should stay unchanged.
	 */
	public function testProvisionMediaUpdateFieldUserdirectoryMediaId() {
		$userdirectory = [
			'name' => 'Validate provision media mapping update',
			'idp_type' => IDP_TYPE_LDAP,
			'host' => 'ldap.forumsys.com',
			'port' => 389,
			'base_dn' => 'dc=example,dc=com',
			'search_attribute' => 'uid',
			'provision_status' => JIT_PROVISIONING_ENABLED,
			'provision_groups' => [
				['name' => 'zabbix-devs', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]]
			],
			'provision_media' => [
				['name' => 'Media #1', 'mediatypeid' => 1, 'attribute' => 'attr_media1', 'active' => 1, 'severity' => 1, 'period' => '{$A}'],
				['name' => 'Media #2', 'mediatypeid' => 1, 'attribute' => 'attr_media2', 'active' => 1, 'severity' => 1, 'period' => '{$A}']
			]
		];
		$input = self::resolveIds([$userdirectory]);

		// Create test userdirectory.
		['result' => $result] = $this->call('userdirectory.create', $input);
		$userdirectoryid = reset($result['userdirectoryids']);

		// Get 'userdirectory_mediaid' of created media before update operation.
		$db_userdirectory = $this->call('userdirectory.get', [
			'output' => [],
			'selectProvisionMedia' => API_OUTPUT_EXTEND,
			'userdirectoryids' => [$userdirectoryid]
		]);
		$db_userdirectory = reset($db_userdirectory['result']);
		$db_media = array_column($db_userdirectory['provision_media'], 'userdirectory_mediaid', 'name');

		$this->call('userdirectory.update', [
			'userdirectoryid' => $userdirectoryid,
			'provision_media' => [
				['userdirectory_mediaid' => $db_media['Media #1'], 'name' => 'Media #1', 'mediatypeid' => 1, 'attribute' => 'attr_media1', 'active' => 0],
				['name' => 'Media #2', 'mediatypeid' => 1, 'attribute' => 'attr_media3', 'active' => 0]
			]
		]);

		// Get 'userdirectory_mediaid' of created media after update operation.
		$db_userdirectory = $this->call('userdirectory.get', [
			'output' => [],
			'selectProvisionMedia' => API_OUTPUT_EXTEND,
			'userdirectoryids' => [$userdirectoryid]
		]);
		$db_userdirectory = reset($db_userdirectory['result']);
		$db_media_updated = array_column($db_userdirectory['provision_media'], 'userdirectory_mediaid', 'name');

		// Deleting test data.
		$this->call('userdirectory.delete', [$userdirectoryid]);

		$this->assertTrue($db_media['Media #1'] === $db_media_updated['Media #1'], 'Property userdirectory_mediaid should not change after update operation if where passed');
		$this->assertTrue($db_media['Media #2'] !== $db_media_updated['Media #2'], 'Property userdirectory_mediaid should change after update operation if where not passed');
	}

	public function testSamlCertificatesReturnInHashedFormat() {
		$hashed_certificates = [
			'idp_certificate_hash' => '37a33c1994ef4e6326ee2a5dd0856ce0',
			'sp_certificate_hash' => '37a33c1994ef4e6326ee2a5dd0856ce0',
			'sp_private_key_hash' => '3376914a2891a33f6dc9cb3d7fb518cd'
		];

		$userdirectoryid = self::$data['userdirectoryid']['API SAML'];

		$db_userdirectory = $this->call('userdirectory.get', [
			'output' => array_keys($hashed_certificates),
			'filter' => ['idp_type' => IDP_TYPE_SAML],
			'userdirectoryids' => [$userdirectoryid]
		]);

		$db_userdirectory = reset($db_userdirectory['result']);
		$this->assertSame($db_userdirectory, $hashed_certificates);
	}

	/**
	 * Test provision media update only sent fields when userdirectory_mediaid is sent.
	 */
	public function testProvisionMediaFieldsUpdate() {
		$userdirectory = [
			'name' => 'Validate provision media mapping update',
			'idp_type' => IDP_TYPE_LDAP,
			'host' => 'ldap.forumsys.com',
			'port' => 389,
			'base_dn' => 'dc=example,dc=com',
			'search_attribute' => 'uid',
			'provision_status' => JIT_PROVISIONING_ENABLED,
			'provision_groups' => [
				['name' => 'zabbix-devs', 'roleid' => 1, 'user_groups' => [['usrgrpid' => 7]]]
			],
			'provision_media' => [
				['name' => 'Media #1', 'mediatypeid' => 1, 'attribute' => 'attr_media1', 'active' => 0],
				['name' => 'Media #2', 'mediatypeid' => 1, 'attribute' => 'attr_media2', 'period' => '{$A}']
			]
		];
		$input = self::resolveIds([$userdirectory]);

		// Create test userdirectory.
		['result' => $result] = $this->call('userdirectory.create', $input);
		$userdirectoryid = reset($result['userdirectoryids']);

		// Get created provision media before update operation.
		$db_userdirectory = $this->call('userdirectory.get', [
			'output' => [],
			'selectProvisionMedia' => API_OUTPUT_EXTEND,
			'userdirectoryids' => [$userdirectoryid]
		]);
		$db_userdirectory = reset($db_userdirectory['result']);
		$provision_medias = [];
		$db_provision_medias = $db_userdirectory['provision_media'];

		foreach ($db_provision_medias as $db_provision_media) {
			$provision_medias[] = ['userdirectory_mediaid' => $db_provision_media['userdirectory_mediaid']];
		}

		// Update using only userdirectory_mediaid, fields should not be changed
		$this->call('userdirectory.update', [
			'userdirectoryid' => $userdirectoryid,
			'provision_media' => $provision_medias
		]);
		$db_userdirectory = $this->call('userdirectory.get', [
			'output' => [],
			'selectProvisionMedia' => API_OUTPUT_EXTEND,
			'userdirectoryids' => [$userdirectoryid]
		]);
		$db_userdirectory = reset($db_userdirectory['result']);

		// Deleting test data.
		$this->call('userdirectory.delete', [$userdirectoryid]);

		$this->assertSame($db_provision_medias, $db_userdirectory['provision_media']);
	}

	public static function deleteValidDataProvider() {
		return [
			'Test delete userdirectory' => [
				'userdirectory' => ['LDAP #1'],
				'expected_error' => null
			]
		];
	}

	public static function deleteInvalidDataProvider() {
		return [
			'Test delete userdirectory with user group' => [
				'userdirectoryids' => ['API LDAP #1'],
				'expected_error' => 'Cannot delete user directory "API LDAP #1".'
			],
			'Test delete default userdirectory' => [
				'userdirectoryids' => ['API LDAP #2'],
				'expected_error' => 'Cannot delete default user directory.'
			],
			'Test delete id not exists' => [
				'userdirectoryids' => [1234],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test delete SAML fail when saml_auth_enabled is enabled' => [
				'userdirectoryids' => ['API SAML'],
				'expected_error' => 'Cannot delete default user directory.'
			]
		];
	}

	/**
	 * @dataProvider deleteInvalidDataProvider
	 * @dataProvider deleteValidDataProvider
	 */
	public function testDelete(array $userdirectoryids, $expected_error) {
		$ids = [];
		foreach ($userdirectoryids as $userdirectoryid) {
			if (array_key_exists($userdirectoryid, self::$data['userdirectoryid'])) {
				$ids[] = self::$data['userdirectoryid'][$userdirectoryid];
			}
			elseif (is_numeric($userdirectoryid)) {
				$ids[] = (string) $userdirectoryid;
			}
		}

		$this->assertNotEmpty($ids, 'No user directories to test delete');
		$this->call('userdirectory.delete', $ids, $expected_error);

		if ($expected_error === null) {
			self::$data['userdirectoryid'] = array_diff(self::$data['userdirectoryid'], $ids);
		}
	}

	/**
	 * Default userdirectory can be deleted only when there are no userdirectories and ldap_auth_enabled=0.
	 */
	public function testDeleteDefault() {
		// Delete user group to allow to delete userdirectory linked to user group.
		$this->call('usergroup.delete', [self::$data['usrgrpid']['Auth test #1']]);
		self::$data['usrgrpid'] = array_diff(self::$data['usrgrpid'], [self::$data['usrgrpid']['Auth test #1']]);

		$samlids = array_intersect_key(self::$data['userdirectoryid'], array_flip(['API SAML']));
		$ldapids = array_diff_key(self::$data['userdirectoryid'], $samlids);
		$ldap_defaultid = $ldapids['API LDAP #2'];

		// Delete LDAP userdirectories except default.
		$this->call('userdirectory.delete', array_values(array_diff($ldapids, [$ldap_defaultid])));

		$error = 'Cannot delete default user directory.';
		$this->call('userdirectory.delete', [$ldap_defaultid], $error);

		// Disable LDAP authentication to be able to delete default LDAP userdirectory.
		$this->call('authentication.update', ['ldap_auth_enabled' => ZBX_AUTH_LDAP_DISABLED]);
		$this->call('userdirectory.delete', [$ldap_defaultid]);

		$error = 'Cannot delete default user directory.';
		$this->call('userdirectory.delete', array_values($samlids), $error);

		// Disable SAML authentication to be able to delete default SAML userdirectory.
		$this->call('authentication.update', ['saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED]);
		$this->call('userdirectory.delete', array_values($samlids));

		// Unset deleted userdirectory ids.
		self::$data['userdirectoryid'] = array_diff(self::$data['userdirectoryid'], $ldapids, $samlids);
	}

	/**
	 * Replace name by value for property names in self::$data.
	 *
	 * @param array $rows
	 */
	public static function resolveIds(array $rows): array {
		$result = [];

		foreach ($rows as $row) {
			foreach (array_intersect_key(self::$data, $row) as $key => $ids) {
				if (array_key_exists($row[$key], $ids)) {
					$row[$key] = $ids[$row[$key]];
				}
			}

			$result[] = $row;
		}

		return $result;
	}

	/**
	 * Create data to be used in tests.
	 */
	public function prepareTestData() {
		$data = [
			[
				'name' => 'API LDAP #1',
				'idp_type' => IDP_TYPE_LDAP,
				'host' => 'ldap.forumsys.com',
				'port' => 389,
				'base_dn' => 'dc=example,dc=com',
				'search_attribute' => 'uid'
			],
			[
				'name' => 'API LDAP #2',
				'idp_type' => IDP_TYPE_LDAP,
				'host' => 'ldap.forumsys.com',
				'port' => 389,
				'base_dn' => 'dc=example,dc=com',
				'search_attribute' => 'uid'
			],
			[
				'name' => 'API LDAP #3',
				'idp_type' => IDP_TYPE_LDAP,
				'host' => 'ldap.forumsys.com',
				'port' => 389,
				'base_dn' => 'dc=example,dc=com',
				'search_attribute' => 'uid',
				'provision_status' => JIT_PROVISIONING_ENABLED,
				'group_basedn' => 'dc=example,dc=com',
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
				]
			],
			[
				'name' => 'API LDAP #4',
				'idp_type' => IDP_TYPE_LDAP,
				'host' => 'ldap.forumsys.com',
				'port' => 389,
				'base_dn' => 'dc=example,dc=com',
				'search_attribute' => 'uid',
				'bind_password' => 'test_password'
			],
			[
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
			]
		];
		$response = CDataHelper::call('userdirectory.create', $data);

		$this->assertArrayHasKey('userdirectoryids', $response);
		self::$data['userdirectoryid'] = array_combine(array_column($data, 'name'), $response['userdirectoryids']);

		$userdirectoryid = self::$data['userdirectoryid']['API LDAP #1'];

		$response = CDataHelper::call('usergroup.create', [
			['name' => 'Auth test #1', 'gui_access' => GROUP_GUI_ACCESS_LDAP, 'userdirectoryid' => $userdirectoryid],
			['name' => 'Auth test #2', 'gui_access' => GROUP_GUI_ACCESS_LDAP]
		]);
		$this->assertArrayHasKey('usrgrpids', $response);
		self::$data['usrgrpid'] = array_combine(['Auth test #1', 'Auth test #2'], $response['usrgrpids']);

		CDataHelper::call('authentication.update', [
			'ldap_userdirectoryid' => self::$data['userdirectoryid']['API LDAP #2'],
			'ldap_auth_enabled' => ZBX_AUTH_LDAP_ENABLED,
			'saml_auth_enabled' => ZBX_AUTH_SAML_ENABLED
		]);
	}

	/**
	 * Remove data created for tests.
	 */
	public static function cleanTestData() {
		$api_ids = array_filter([
			'usergroup.delete' => array_values(self::$data['usrgrpid']),
			'userdirectory.delete' => array_values(self::$data['userdirectoryid'])
		]);
		CDataHelper::call('authentication.update', [
			'ldap_userdirectoryid' => 0,
			'ldap_auth_enabled' => ZBX_AUTH_LDAP_DISABLED,
			'saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED
		]);

		foreach ($api_ids as $api => $ids) {
			CDataHelper::call($api, $ids);
		}
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
