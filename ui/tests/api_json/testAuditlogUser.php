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
 * @backup users
 */
class testAuditlogUser extends testAuditlogCommon {
	private static $resourceid;
	private static $before_usrgroup;
	private static $before_media;

	public function testAuditlogUser_Create() {
		$create = $this->call('user.create', [
			[
				'username' => 'Audit',
				'passwd' => 'zabbixzabbix',
				'name' => 'Audit_name',
				'surname' => 'Audit_surname',
				'roleid' => 3,
				'usrgrps' => [
					[
						'usrgrpid' => 7
					]
				],
				'medias' => [
					[
						'mediatypeid' => 1,
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
		self::$resourceid = $create['result']['userids'][0];
		self::$before_usrgroup = CDBHelper::getRow('SELECT id FROM users_groups WHERE userid='.self::$resourceid);
		self::$before_media = CDBHelper::getRow('SELECT mediaid FROM media WHERE userid='.self::$resourceid);

		$created = "{\"user.username\":[\"add\",\"Audit\"],".
				"\"user.passwd\":[\"add\",\"******\"],".
				"\"user.name\":[\"add\",\"Audit_name\"],".
				"\"user.surname\":[\"add\",\"Audit_surname\"],".
				"\"user.roleid\":[\"add\",\"3\"],".
				"\"user.usrgrps[".self::$before_usrgroup['id']."]\":[\"add\"],".
				"\"user.usrgrps[".self::$before_usrgroup['id']."].usrgrpid\":[\"add\",\"7\"],".
				"\"user.usrgrps[".self::$before_usrgroup['id']."].id\":[\"add\",\"".self::$before_usrgroup['id']."\"],".
				"\"user.medias[".self::$before_media['mediaid']."]\":[\"add\"],".
				"\"user.medias[".self::$before_media['mediaid']."].mediatypeid\":[\"add\",\"1\"],".
				"\"user.medias[".self::$before_media['mediaid']."].sendto\":[\"add\",\"audit@audit.com\"],".
				"\"user.medias[".self::$before_media['mediaid']."].mediaid\":[\"add\",\"".self::$before_media['mediaid']."\"],".
				"\"user.userid\":[\"add\",\"".self::$resourceid."\"]}";

		$this->sendGetRequest('details', 0, $created, self::$resourceid);
	}

	/**
	 * @depends testAuditlogUser_Create
	 */
	public function testAuditlogUser_Login() {
		$this->authorize('Audit', 'zabbixzabbix');
		$this->sendGetRequest('username', 8, 'Audit', self::$resourceid);
	}

	/**
	 * @depends testAuditlogUser_Create
	 */
	public function testAuditlogUser_Logout() {
		$this->authorize('Audit', 'zabbixzabbix');
		$this->call('user.logout', []);
		$this->authorize('Admin', 'zabbix');
		$this->sendGetRequest('username', 4, 'Audit', self::$resourceid);
	}

	/**
	 * @depends testAuditlogUser_Create
	 */
	public function testAuditlogUser_FailedLogin() {
		$this->authorize('Audit', 'incorrect_pas');
		$this->authorize('Admin', 'zabbix');
		$this->sendGetRequest('username', 9, 'Audit', self::$resourceid);
	}

	/**
	 * @depends testAuditlogUser_Create
	 */
	public function testAuditlogUser_Update() {
		$this->authorize('Admin', 'zabbix');
		$this->call('user.update', [
			[
				'userid' => self::$resourceid,
				'username' => 'updated_Audit',
				'passwd' => 'updatezabbix',
				'name' => 'Updated_Audit_name',
				'surname' => 'Updated_Audit_surname',
				'usrgrps' => [
					[
						'usrgrpid' => 11
					]
				],
				'medias' => [
					[
						'mediatypeid' => 1,
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
		$after_usrgroup = CDBHelper::getRow('SELECT id FROM users_groups WHERE userid='.self::$resourceid);
		$after_media = CDBHelper::getRow('SELECT mediaid FROM media WHERE userid='.self::$resourceid);

		$updated = "{\"user.usrgrps[".self::$before_usrgroup['id']."]\":[\"delete\"],".
			"\"user.medias[".self::$before_media['mediaid']."]\":[\"delete\"],".
			"\"user.usrgrps[".$after_usrgroup['id']."]\":[\"add\"],".
			"\"user.medias[".$after_media['mediaid']."]\":[\"add\"],".
			"\"user.username\":[\"update\",\"updated_Audit\",\"Audit\"],".
			"\"user.passwd\":[\"update\",\"******\",\"******\"],".
			"\"user.name\":[\"update\",\"Updated_Audit_name\",\"Audit_name\"],".
			"\"user.surname\":[\"update\",\"Updated_Audit_surname\",\"Audit_surname\"],".
			"\"user.usrgrps[".$after_usrgroup['id']."].usrgrpid\":[\"add\",\"11\"],".
			"\"user.usrgrps[".$after_usrgroup['id']."].id\":[\"add\",\"".$after_usrgroup['id']."\"],".
			"\"user.medias[".$after_media['mediaid']."].mediatypeid\":[\"add\",\"1\"],".
			"\"user.medias[".$after_media['mediaid']."].sendto\":[\"add\",\"update_audit@audit.com\"],".
			"\"user.medias[".$after_media['mediaid']."].mediaid\":[\"add\",\"".$after_media['mediaid']."\"]}";

		$this->sendGetRequest('details', 1, $updated, self::$resourceid);
	}

	/**
	 * @depends testAuditlogUser_Create
	 */
	public function testAuditlogUser_Delete() {
		$this->call('user.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'updated_Audit', self::$resourceid);
	}
}
