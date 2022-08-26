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
 * @backup token, ids
 */
class testAuditlogToken extends testAuditlogCommon {
	public function testAuditlogToken_Create() {
		$create = $this->call('token.create', [
			[
				'name' => 'Audit token',
				'userid' => 1,
				'expires_at' => 1611238072,
				'description' => 'Audit description',
				'status' => 1
			]
		]);
		$resourceid = $create['result']['tokenids'][0];

		$created = "{\"token.name\":[\"add\",\"Audit token\"],".
				"\"token.userid\":[\"add\",\"1\"],".
				"\"token.expires_at\":[\"add\",\"1611238072\"],".
				"\"token.description\":[\"add\",\"Audit description\"],".
				"\"token.status\":[\"add\",\"1\"],".
				"\"token.tokenid\":[\"add\",\"".$resourceid."\"]}";

		$this->sendGetRequest('details', 0, $created, $resourceid);
	}

	public function testAuditlogToken_Update() {
		$this->call('token.update', [
			[
				'tokenid' => 11,
				'name' => 'Updated audit token',
				'expires_at' => 1611238090,
				'description' => 'Updated description',
				'status' => 1
			]
		]);

		$updated = "{\"token.name\":[\"update\",\"Updated audit token\",\"test-token\"],".
				"\"token.expires_at\":[\"update\",\"1611238090\",\"0\"],".
				"\"token.description\":[\"update\",\"Updated description\",\"\"],".
				"\"token.status\":[\"update\",\"1\",\"0\"]}";

		$this->sendGetRequest('details', 1, $updated, 11);
	}

	public function testAuditlogToken_Delete() {
		$this->call('token.delete', [11]);
		$this->sendGetRequest('resourcename', 2, 'Updated audit token', 11);
	}
}
