<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup scripts
 */
class testAuditlogScript extends testAuditlogCommon {

	/**
	 * Created script id
	 */
	protected static $resourceid;

	public function testAuditlogScript_Create() {
		$create = $this->call('script.create', [
			[
				'name' => 'Created script',
				'command' => 'Created command to run',
				'type' => 2,
				'scope' => 2,
				'menu_path' => 'created/menu/path',
				'authtype' => 1,
				'username' => 'created_user',
				'password' => 'created_password',
				'publickey' => 'created_publick_key',
				'privatekey' => 'created_private_key',
				'port' => '12345',
				'host_access' => 3,
				'confirmation' => 'created_confirmation',
				'description' => 'created description'
			]
		]);

		self::$resourceid = $create['result']['scriptids'][0];

		$created = json_encode([
			'script.name' => ['add', 'Created script'],
			'script.command' => ['add', 'Created command to run'],
			'script.type' => ['add', '2'],
			'script.scope' => ['add', '2'],
			'script.menu_path' => ['add', 'created/menu/path'],
			'script.authtype' => ['add', '1'],
			'script.username' => ['add', 'created_user'],
			'script.password' => ['add', '******'],
			'script.publickey' => ['add', 'created_publick_key'],
			'script.privatekey' => ['add', 'created_private_key'],
			'script.port' => ['add', '12345'],
			'script.host_access' => ['add', '3'],
			'script.confirmation' => ['add', 'created_confirmation'],
			'script.description' => ['add', 'created description'],
			'script.scriptid' => ['add', self::$resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid);
	}

	/**
	 * @depends testAuditlogScript_Create
	 */
	public function testAuditlogScript_Update() {
		$this->call('script.update', [
			[
				'scriptid' => self::$resourceid,
				'name' => 'Updated script',
				'command' => 'Updated command to run',
				'type' => 3,
				'scope' => 4,
				'menu_path' => 'updated/menu/path',
				'username' => 'updated_user',
				'password' => 'updated_password',
				'port' => '65535',
				'groupid' => '4',
				'usrgrpid' => '7',
				'host_access' => 2,
				'confirmation' => 'updated_confirmation',
				'description' => 'updated description'
			]
		]);

		$updated = json_encode([
			'script.name' => ['update', 'Updated script', 'Created script'],
			'script.command' => ['update', 'Updated command to run', 'Created command to run'],
			'script.type' => ['update', '3', '2'],
			'script.scope' => ['update', '4', '2'],
			'script.menu_path' => ['update', 'updated/menu/path', 'created/menu/path'],
			'script.username' => ['update', 'updated_user', 'created_user'],
			'script.password' => ['update', '******', '******'],
			'script.port' => ['update', '65535', '12345'],
			'script.groupid' => ['update', '4', '0'],
			'script.usrgrpid' => ['update', '7', '0'],
			'script.host_access' => ['update', '2', '3'],
			'script.confirmation' => ['update', 'updated_confirmation', 'created_confirmation'],
			'script.description' => ['update', 'updated description', 'created description'],
			'script.authtype' => ['update', '0', '1'],
			'script.publickey' => ['update', '', 'created_publick_key'],
			'script.privatekey' => ['update', '', 'created_private_key']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid);
	}

	/**
	 * @depends testAuditlogScript_Create
	 */
	public function testAuditlogScript_Delete() {
		$this->call('script.delete', [self::$resourceid]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated script', self::$resourceid);
	}
}
