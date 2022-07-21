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
			"\nuser.name: Audit_name".
			"\nuser.passwd: ******".
			"\nuser.roleid: 3".
			"\nuser.surname: Audit_surname".
			"\nuser.userid: 3".
			"\nuser.username: Audit".
			"\nuser.usrgrps[5]: Added".
			"\nuser.usrgrps[5].id: 5".
			"\nuser.usrgrps[5].usrgrpid: 7";

	public $updated = "user.medias[1]: Deleted".
			"\nuser.medias[2]: Added".
			"\nuser.medias[2].mediaid: 2".
			"\nuser.medias[2].mediatypeid: 1".
			"\nuser.medias[2].sendto: update_audit@audit.com".
			"\nuser.name: Audit_name => Updated_Audit_name".
			"\nuser.passwd: ****** => ******".
			"\nuser.surname: Audit_surname => Updated_Audit_surname".
			"\nuser.username: Audit => updated_Audit".
			"\nuser.usrgrps[5]: Deleted".
			"\nuser.usrgrps[6]: Added".
			"\nuser.usrgrps[6].id: 6".
			"\nuser.usrgrps[6].usrgrpid: 11";

	public $deleted = 'Description: updated_Audit';

	public $resource_name = 'User';

	public $login = '';

	public $logout = '';

	public $failed_login = '';

	public function prepareCreateData() {
		$ids = CDataHelper::call('user.create', [
			[
				'username' => 'Audit',
				'passwd' => 'zabbixzabbix',
				'name' => 'Audit_name',
				'surname' => 'Audit_surname',
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
	 * Check audit of User logout.
	 */
	public function testAuditUser_Logout() {
		CDataHelper::call('user.logout', []);

		$this->checkAuditValues(self::$ids, 'Logout');
	}

	/**
	 * Check audit of User failed login.
	 */
	public function testAuditUser_FailedLogin() {
		CAPIHelper::authorize('Audit', 'incorrect_pas');

		$this->checkAuditValues(self::$ids, 'Failed login');
	}

	/**
	 * Check audit of User update.
	 */
	public function testAuditUser_Update() {
		CAPIHelper::authorize('Admin', 'zabbix');
		CDataHelper::call('user.update', [
			[
				'userid' => self::$ids,
				'username' => 'updated_Audit',
				'passwd' => 'updatezabbix',
				'name' => 'Updated_Audit_name',
				'surname' => 'Updated_Audit_surname',
				'usrgrps' => [
					[
						'usrgrpid' => '11'
					]
				],
				'medias' => [
					[
						'mediatypeid' => '1',
						'sendto' => [
							'update_audit@audit.com'
						],
						'active' => 0,
						'severity' => 63,
						'period' => '1-7,00:00-24:00'
					]
				]
			]
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of User delete.
	 */
	public function testAuditUser_Delete() {
		CDataHelper::call('user.delete', [self::$ids]);

		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
