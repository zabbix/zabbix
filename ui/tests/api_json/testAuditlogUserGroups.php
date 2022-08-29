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


require_once dirname(__FILE__).'/testAuditlogCommon.php';

/**
 * @backup usrgrp
 */
class testAuditlogUserGroups extends testAuditlogCommon {
	public function testAuditlogUserGroups_Create() {
		$create = $this->call('usergroup.create', [
			[
				'name' => 'Audit user groups',
				'rights' => [
					'permission' => 0,
					'id' => 2
				],
				'users' => [
					'userid' => 2
				]
			]
		]);
		$resourceid = $create['result']['usrgrpids'][0];
		$rights = CDBHelper::getRow('SELECT rightid FROM rights WHERE groupid='.$resourceid);
		$id = CDBHelper::getRow('SELECT id FROM users_groups WHERE usrgrpid='.$resourceid);

		$created = "{\"usergroup.name\":[\"add\",\"Audit user groups\"],".
				"\"usergroup.rights[".$rights['rightid']."]\":[\"add\"],".
				"\"usergroup.rights[".$rights['rightid']."].id\":[\"add\",\"2\"],".
				"\"usergroup.rights[".$rights['rightid']."].rightid\":[\"add\",\"".$rights['rightid']."\"],".
				"\"usergroup.users[".$id['id']."]\":[\"add\"],".
				"\"usergroup.users[".$id['id']."].userid\":[\"add\",\"2\"],".
				"\"usergroup.users[".$id['id']."].id\":[\"add\",\"".$id['id']."\"],".
				"\"usergroup.usrgrpid\":[\"add\",\"".$resourceid."\"]}";

		$this->sendGetRequest('details', 0, $created, $resourceid);
	}

	public function testAuditlogUserGroups_Update() {
		$this->call('usergroup.update', [
			[
				'usrgrpid' => 12,
				'users_status' => 1,
				'debug_mode' => 1,
				'name' => 'Updated user group name'
			]
		]);

		$updated = "{\"usergroup.users_status\":[\"update\",\"1\",\"0\"],".
				"\"usergroup.debug_mode\":[\"update\",\"1\",\"0\"],".
				"\"usergroup.name\":[\"update\",\"Updated user group name\",\"No access to the frontend\"]}";

		$this->sendGetRequest('details', 1, $updated, 12);
	}

	public function testAuditlogUserGroups_Delete() {
		$this->call('usergroup.delete', [12]);
		$this->sendGetRequest('resourcename', 2, 'Updated user group name', 12);
	}
}
