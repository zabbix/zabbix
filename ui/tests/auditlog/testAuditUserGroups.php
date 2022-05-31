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
 * @backup usrgrp, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditUserGroups extends testPageReportsAuditValues {

	/**
	 * Id of users group.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "usergroup.name: Audit user groups".
			"\nusergroup.rights[1]: Added".
			"\nusergroup.rights[1].id: 2".
			"\nusergroup.rights[1].rightid: 1".
			"\nusergroup.users[5]: Added".
			"\nusergroup.users[5].id: 5".
			"\nusergroup.users[5].userid: 2".
			"\nusergroup.usrgrpid: 13";

	public $updated = "usergroup.debug_mode: 0 => 1".
			"\nusergroup.name: Audit user groups => Updated user group name".
			"\nusergroup.users_status: 0 => 1";

	public $deleted = 'Description: Updated user group name';

	public $resource_name = 'User group';

	public function prepareCreateData() {
		$ids = CDataHelper::call('usergroup.create', [
			[
				'name' => 'Audit user groups',
				'rights' => [
					'permission' => 0,
					'id' => '2'
				],
				'users' => [
					'userid' => '2'
				]
			]
		]);
		$this->assertArrayHasKey('usrgrpids', $ids);
		self::$ids = $ids['usrgrpids'][0];
	}

	/**
	 * Check audit of created User group.
	 */
	public function testAuditUserGroups_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

	/**
	 * Check audit of User group.
	 */
	public function testAuditUserGroups_Update() {
		CDataHelper::call('usergroup.update', [
			[
				'usrgrpid' => self::$ids,
				'users_status' => 1,
				'debug_mode' => 1,
				'name' => 'Updated user group name'
			]
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of deleted User group.
	 */
	public function testAuditUserGroups_Delete() {
		CDataHelper::call('usergroup.delete', [self::$ids]);

		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
