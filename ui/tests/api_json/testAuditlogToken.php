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
 * @backup token, ids
 */
class testAuditlogToken extends CAPITest {

	protected static $resourceid;

	public function testAuditlogToken_Create() {
		$created = "{\"token.name\":[\"add\",\"Audit token\"],\"token.userid\":[\"add\",\"54\"],\"token.tokenid".
				"\":[\"add\",\"24\"]}";

		$create = $this->call('token.create', [
			[
				'name' => 'Audit token',
				'userid' => 54
			]
		]);

		self::$resourceid = $create['result']['tokenids'][0];
		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogToken_Update() {
		$updated = "{\"token.name\":[\"update\",\"Updated token\",\"Audit token\"],\"token.status\":[".
				"\"update\",\"1\",\"0\"]}";

		$this->call('token.update', [
			[
				'tokenid' => self::$resourceid,
				'name' => 'Updated token',
				'status' => 1
			]
		]);

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogToken_Delete() {
		$this->call('token.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'Updated token');
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
