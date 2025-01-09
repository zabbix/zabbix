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
 * @onBefore  prepareTestData
 *
 * @onAfter cleanTestData
 * @backup userdirectory
 */
class testUserDirectory extends CAPITest {

	public static $data = [
		'usrgrpid' => [],
		'userdirectoryid' => []
	];

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
			]
		];
	}

	/**
	 * @dataProvider updateInvalidDataProvider
	 * @dataProvider updateValidDataProvider
	 */
	public function testUpdate(array $userdirectories, $expected_error) {
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
}
