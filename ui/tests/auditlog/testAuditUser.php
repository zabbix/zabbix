<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/testPageReportsAuditValues.php';

/**
 * @backup users, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditUser extends testPageReportsAuditValues {

	/**
	 * Id of user.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "user.medias[1]: Added".
			"\nuser.medias[1].mediaid: 1".
			"\nuser.medias[1].mediatypeid: 1".
			"\nuser.medias[1].sendto: audit@audit.com".
			"\nuser.passwd: ******".
			"\nuser.roleid: 3\nuser.userid: 3".
			"\nuser.username: Audit".
			"\nuser.usrgrps[5]: Added".
			"\nuser.usrgrps[5].id: 5".
			"\nuser.usrgrps[5].usrgrpid: 7";

	public $updated = "usergroup.debug_mode: 0 => 1".
			"\nusergroup.name: Audit user groups => Updated user group name".
			"\nusergroup.users_status: 0 => 1";

	public $deleted = 'Description: Updated user group name';

	public $resource_name = 'User';

	public $login = '';

	public function prepareCreateData() {
		$ids = CDataHelper::call('user.create', [
			[
				'username' => 'Audit',
				'passwd' => 'zabbixzabbix',
				'roleid' => '3',
				'usrgrps' => [
					[
						'usrgrpid' => '7'
					]
				],
				'medias' => [
					[
						'mediatypeid' => '1',
						'sendto' => [
							'audit@audit.com'
						],
						'active' => 0,
						'severity' => 63,
						'period' => '1-7,00:00-24:00'
					]
				]
			]
		]);
		$this->assertArrayHasKey('userids', $ids);
		self::$ids = $ids['userids'][0];
	}

	/**
	 * Check audit of created User.
	 */
	public function testAuditUser_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

	/**
	 * Check audit of User login.
	 */
	public function testAuditUser_Login() {
		CAPIHelper::authorize('Audit', 'zabbixzabbix');

		$this->checkAuditValues(self::$ids, 'Login');
	}

	/**
	 * Check audit of User group.
	 */
//	public function testAuditUser_Update() {
//		CDataHelper::call('usergroup.update', [
//			[
//				'usrgrpid' => self::$ids,
//				'users_status' => 1,
//				'debug_mode' => 1,
//				'name' => 'Updated user group name'
//			]
//		]);
//
//		$this->checkAuditValues(self::$ids, 'Update');
//	}
//
//	/**
//	 * Check audit of deleted User group.
//	 */
//	public function testAuditUser_Delete() {
//		CDataHelper::call('usergroup.delete', [self::$ids]);
//
//		$this->checkAuditValues(self::$ids, 'Delete');
//	}
}
