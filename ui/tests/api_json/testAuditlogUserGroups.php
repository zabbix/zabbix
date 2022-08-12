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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup usrgrp, ids
 */
class testAuditlogUserGroups extends CAPITest {

	protected static $resourceid;

	public function testAuditlogUserGroups_Create() {
		$created = "{\"usergroup.name\":[\"add\",\"Audit user groups\"],\"usergroup.rights[90004]\":[\"add\"],".
				"\"usergroup.rights[90004].id\":[\"add\",\"2\"],\"usergroup.rights[90004].rightid\":[\"add\",".
				"\"90004\"],\"usergroup.users[90021]\":[\"add\"],\"usergroup.users[90021].userid\":[\"add\",\"2\"],".
				"\"usergroup.users[90021].id\":[\"add\",\"90021\"],\"usergroup.usrgrpid\":[\"add\",\"90001\"]}";

		$create = $this->call('usergroup.create', [
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

		self::$resourceid = $create['result']['usrgrpids'][0];
		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogUserGroups_Update() {
		$updated = "{\"usergroup.users_status\":[\"update\",\"1\",\"0\"],\"usergroup.debug_mode\":[\"update\",".
				"\"1\",\"0\"],\"usergroup.name\":[\"update\",\"Updated user group name\",\"Audit user groups\"]}";

		$this->call('usergroup.update', [
			[
				'usrgrpid' => self::$resourceid,
				'users_status' => 1,
				'debug_mode' => 1,
				'name' => 'Updated user group name'
			]
		]);

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogUserGroups_Delete() {
		$this->call('usergroup.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'Updated user group name');
	}

	private function sendGetRequest($output, $action, $result) {
		$get = $this->call('auditlog.get', [
			'output' => [$output],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'filter' => [
				'resourceid' => self::$resourceid,
				'action' => $action
			]
		]);

		$this->assertEquals($result, $get['result'][0][$output]);
	}
}
