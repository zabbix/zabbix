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


require_once dirname(__FILE__).'/../../include/CAPITest.php';

class testAuditlogCommon extends CAPITest {

	/**
	 * Audit log Add action id.
	 */
	public $add_actionid = 0;

	/**
	 * Audit log Update action id.
	 */
	public $update_actionid = 1;

	/**
	 * Audit log Delete action id.
	 */
	public $delete_actionid = 2;

	/**
	 * Audit log Logout action id.
	 */
	public $logout_actionid = 4;

	/**
	 * Audit log Login action id.
	 */
	public $login_actionid = 8;

	/**
	 * Audit log Failed Login action id.
	 */
	public $failedlogin_actionid = 9;

	/**
	 * Send auditlog.get request and check returned values.
	 *
	 * @param string $parameter 	what parameter need to be checked in audit
	 * @param integer $actionid 	action id
	 * @param string $expected 		what should be returned in request
	 * @param integer $resourceid 	resource id
	 */
	public function getAuditDetails($parameter, $actionid, $expected, $resourceid) {
		$get = $this->call('auditlog.get', [
			'output' => [$parameter],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'filter' => [
				'resourceid' => $resourceid,
				'action' => $actionid
			]
		]);

		$this->assertEquals($expected, $get['result'][0][$parameter]);
	}
}
