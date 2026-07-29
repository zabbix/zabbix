<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup usrgrp
 */
class testAuditlogUserGroups extends testAuditlogCommon {

	/**
	 * Existing User ID.
	 */
	private const USERID = 2;

	/**
	 * Existing User group ID.
	 */
	private const USRGRPID = 12;

	/**
	 * Resource type User group
	 */
	const RESOURCE_TYPE_GROUP = 11;

	/**
	 * Resource type Proxy ID
	 */
	private const PROXYID = 1;

	/**
	 * Resource type Proxy group ID
	 */
	private const PROXY_GROUPID = 1;

	/**
	 * Resource type User group
	 */
	const RESOURCE_TYPE_USER = 0;

	public function testAuditlogUserGroups_Create() {
		$create = $this->call('usergroup.create', [
			[
				'name' => 'Audit user groups',
				'hostgroup_rights' => [
					'permission' => 0,
					'id' => 2
				],
				'users' => [
					'userid' => self::USERID
				],
				'proxy_mode' => 1,
				'proxies' => [
					'proxyid' => self::PROXYID
				],
				'proxy_groups' => [
					'proxy_groupid' => self::PROXY_GROUPID
				]
			]
		]);

		$resourceid = $create['result']['usrgrpids'][0];
		$rights = CDBHelper::getRow('SELECT rightid FROM rights WHERE groupid='.zbx_dbstr($resourceid));
		$proxy = CDBHelper::getRow('SELECT proxyid FROM usrgrp_proxy WHERE usrgrpid='.zbx_dbstr($resourceid));
		$usrgrp_proxy = CDBHelper::getRow('SELECT usrgrp_proxyid FROM usrgrp_proxy WHERE usrgrpid='.zbx_dbstr($resourceid));
		$proxy_group = CDBHelper::getRow('SELECT proxy_groupid FROM usrgrp_proxy_group WHERE usrgrpid='.zbx_dbstr($resourceid));
		$usrgrp_proxy_group = CDBHelper::getRow('SELECT usrgrp_proxy_groupid FROM usrgrp_proxy_group WHERE usrgrpid='.zbx_dbstr($resourceid));
		$id = CDBHelper::getRow('SELECT id FROM users_groups WHERE usrgrpid='.zbx_dbstr($resourceid));

		$this->assertNotFalse($id, 'User group record expected');

		$created = json_encode([
			'usergroup.name' => ['add', 'Audit user groups'],
			'usergroup.hostgroup_rights['.$rights['rightid'].']' => ['add'],
			'usergroup.hostgroup_rights['.$rights['rightid'].'].id' => ['add', '2'],
			'usergroup.hostgroup_rights['.$rights['rightid'].'].rightid' => ['add', $rights['rightid']],
			'usergroup.proxy_mode' => ['add', '1'],
			'usergroup.proxies['.$proxy['proxyid'].']' => ['add'],
			'usergroup.proxies['.$proxy['proxyid'].'].proxyid' => ['add', '1'],
			'usergroup.proxies['.$proxy['proxyid'].'].usrgrp_proxyid' => ['add', $usrgrp_proxy['usrgrp_proxyid']],
			'usergroup.proxy_groups['.$proxy_group['proxy_groupid'].']' => ['add'],
			'usergroup.proxy_groups['.$proxy_group['proxy_groupid'].'].proxy_groupid' => ['add', '1'],
			'usergroup.proxy_groups['.$proxy_group['proxy_groupid'].'].usrgrp_proxy_groupid' => ['add', $usrgrp_proxy_group['usrgrp_proxy_groupid']],
			'usergroup.usrgrpid' => ['add', $resourceid]
		]);

		$this->getAuditDetails('details', self::ACTION_ADD, $created, $resourceid, self::RESOURCE_TYPE_GROUP);

		$updated = json_encode([
			'user.usrgrps['.$id['id'].']' => ['add'],
			'user.usrgrps['.$id['id'].'].usrgrpid' => ['add', $resourceid],
			'user.usrgrps['.$id['id'].'].id' => ['add', $id['id']]
		]);

		$this->getAuditDetails('details', self::ACTION_UPDATE, $updated, self::USERID, self::RESOURCE_TYPE_USER);
	}

	public function testAuditlogUserGroups_Update() {
		$this->call('usergroup.update', [
			[
				'usrgrpid' => self::USRGRPID,
				'users_status' => 1,
				'debug_mode' => 1,
				'name' => 'Updated user group name',
				'proxy_group_mode' => 1
			]
		]);

		$updated = json_encode([
			'usergroup.users_status' => ['update', '1', '0'],
			'usergroup.debug_mode' => ['update', '1', '0'],
			'usergroup.name' => ['update', 'Updated user group name', 'No access to the frontend'],
			'usergroup.proxy_group_mode' => ['update', '1', '0']
		]);

		$this->getAuditDetails('details', self::ACTION_UPDATE, $updated, self::USRGRPID, self::RESOURCE_TYPE_GROUP);
	}

	public function testAuditlogUserGroups_Delete() {
		$this->call('usergroup.delete', [self::USRGRPID]);
		$this->getAuditDetails('resourcename', self::ACTION_DELETE, 'Updated user group name', self::USRGRPID,
				self::RESOURCE_TYPE_GROUP
		);
	}
}
