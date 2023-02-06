<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @onBefore  prepareTestData
 *
 * @onAfter cleanTestData
 */
class testUserDirectory extends CAPITest {

	public static function createValidDataProvider() {
		return [
			'Test create userdirectories' => [
				'userdirectory' => [
					['name' => 'LDAP #1', 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid'],
					['name' => 'LDAP #2', 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid']
				],
				'expected_error' => null
			]
		];
	}

	public static function createInvalidDataProvider() {
		return [
			'Test duplicate names in one request' => [
				'userdirectory' => [
					['name' => 'LDAP #1', 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid'],
					['name' => 'LDAP #1', 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid']
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(LDAP #1) already exists.'
			],
			'Test duplicate name' => [
				'userdirectory' => [
					['name' => 'LDAP #1', 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid']
				],
				'expected_error' => 'User directory "LDAP #1" already exists.'
			]
		];
	}

	/**
	 * @dataProvider createValidDataProvider
	 * @dataProvider createInvalidDataProvider
	 */
	public function testCreate($userdirectory, $expected_error) {
		$response = $this->call('userdirectory.create', $userdirectory, $expected_error);

		if ($expected_error === null) {
			self::$data['userdirectoryid'] += array_combine(array_column($userdirectory, 'name'),
				$response['result']['userdirectoryids']
			);
		}
	}

	public static function updateValidDataProvider() {
		return [
			'Test host update' => [
				'userdirectory' => [
					['userdirectoryid' => 'LDAP #1', 'host' => 'localhost']
				],
				'expected_error' => null
			]
		];
	}

	public static function updateInvalidDataProvider() {
		return [
			'Test duplicate name update' => [
				'userdirectory' => [
					['userdirectoryid' => 'LDAP #1', 'name' => 'LDAP #2']
				],
				'expected_error' => 'User directory "LDAP #2" already exists.'
			],
			'Test duplicate names cross name update' => [
				'userdirectory' => [
					['userdirectoryid' => 'LDAP #1', 'name' => 'LDAP #2'],
					['userdirectoryid' => 'LDAP #2', 'name' => 'LDAP #1']
				],
				'expected_error' => 'User directory "LDAP #1" already exists.'
			],
			'Test update not existing' => [
				'userdirectory' => [
					['userdirectoryid' => 1234, 'name' => 'LDAP #1234']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	 * @dataProvider updateValidDataProvider
	 * @dataProvider updateInvalidDataProvider
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
				'userdirectory' => ['API LDAP #1'],
				'expected_error' => 'Cannot delete user directory "API LDAP #1".'
			],
			'Test delete default userdirectory' => [
				'userdirectory' => ['API LDAP #2'],
				'expected_error' => 'Cannot delete default user directory.'
			],
			'Test delete id not exists' => [
				'userdirectory' => [1234],
				'expected_error' => 'No permissions to referred object or it does not exist!'
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
	 * Default userdirectory can be deleted only when there are no userdirectories and ldap_configured=0.
	 */
	public function testDeleteDefault() {
		// Delete user group to allow to delete userdirectory linked to user group.
		$this->call('usergroup.delete', [self::$data['usrgrpid']['Auth test #1']]);
		self::$data['usrgrpid'] = array_diff(self::$data['usrgrpid'], [self::$data['usrgrpid']['Auth test #1']]);

		$ids = self::$data['userdirectoryid'];
		unset($ids['API LDAP #2']);

		// Delete all usergroups except default usergroup.
		$this->call('userdirectory.delete', array_values($ids));
		self::$data['userdirectoryid'] = array_diff(self::$data['userdirectoryid'], $ids);

		$error = 'Cannot delete default user directory.';
		$this->call('userdirectory.delete', self::$data['userdirectoryid'], $error);

		// Disable ldap to be able to delete default userdirectory.
		$this->call('authentication.update', ['ldap_configured' => ZBX_AUTH_LDAP_DISABLED]);
		$this->call('userdirectory.delete', array_values(self::$data['userdirectoryid']));
	}

	public static $data = [
		'usrgrpid' => [],
		'userdirectoryid' => []
	];

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
		$response = CDataHelper::call('userdirectory.create', [
			['name' => 'API LDAP #1', 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid'],
			['name' => 'API LDAP #2', 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid']
		]);
		$this->assertArrayHasKey('userdirectoryids', $response);
		self::$data['userdirectoryid'] = array_combine(['API LDAP #1', 'API LDAP #2'], $response['userdirectoryids']);
		$userdirectoryid = self::$data['userdirectoryid']['API LDAP #1'];

		$response = CDataHelper::call('usergroup.create', [
			['name' => 'Auth test #1', 'gui_access' => GROUP_GUI_ACCESS_LDAP, 'userdirectoryid' => $userdirectoryid],
			['name' => 'Auth test #2', 'gui_access' => GROUP_GUI_ACCESS_LDAP]
		]);
		$this->assertArrayHasKey('usrgrpids', $response);
		self::$data['usrgrpid'] = array_combine(['Auth test #1', 'Auth test #2'], $response['usrgrpids']);

		CDataHelper::call('authentication.update', [
			'ldap_userdirectoryid' => self::$data['userdirectoryid']['API LDAP #2'],
			'ldap_configured' => ZBX_AUTH_LDAP_ENABLED
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
		CDataHelper::call('authentication.update', ['ldap_userdirectoryid' => 0]);

		foreach ($api_ids as $api => $ids) {
			CDataHelper::call($api, $ids);
		}
	}
}
