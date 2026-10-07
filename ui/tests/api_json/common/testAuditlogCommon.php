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


require_once dirname(__FILE__).'/../../include/CAPITest.php';

class testAuditlogCommon extends CAPITest {

	/**
	 * Audit log Add action.
	 */
	const ACTION_ADD = 0;

	/**
	 * Audit log Update action.
	 */
	const ACTION_UPDATE = 1;

	/**
	 * Audit log Delete action.
	 */
	const ACTION_DELETE = 2;

	/**
	 * Audit log Logout action.
	 */
	const ACTION_LOGOUT = 4;

	/**
	 * Audit log Login action.
	 */
	const ACTION_LOGIN = 8;

	/**
	 * Audit log Failed Login action.
	 */
	const ACTION_FAILED_LOGIN = 9;

	/**
	 * Send auditlog.get request and check returned values.
	 *
	 * @param string       $parameter      What parameter need to be checked in audit.
	 * @param integer      $action         Action for which the auditlog is retrieved.
	 * @param string       $expected       What should be returned in request.
	 * @param integer|null $resourceid     Resource ID.
	 * @param integer      $resourcetype  For what type of resource should the record be retrieved
	 */
	public function getAuditDetails($parameter, $action, $expected, $resourceid, $resourcetype) {
		$get = $this->call('auditlog.get', [
			'output' => [$parameter],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'filter' => [
				'resourceid' => $resourceid,
				'resourcetype' => $resourcetype,
				'action' => $action
			],
			'limit' => 1
		]);

		$this->assertEquals($expected, $get['result'][0][$parameter]);
	}
}
