<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * @backup token
 */
class testAuditlogToken extends testAuditlogCommon {

	/**
	 * Existing Token ID.
	 */
	private const TOKENID = 11;

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

		$created = json_encode([
			'token.name' => ['add', 'Audit token'],
			'token.userid' => ['add', '1'],
			'token.expires_at' => ['add', '1611238072'],
			'token.description' => ['add', 'Audit description'],
			'token.status' => ['add', '1'],
			'token.tokenid' => ['add', $resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, $resourceid);
	}

	public function testAuditlogToken_Update() {
		$this->call('token.update', [
			[
				'tokenid' => self::TOKENID,
				'name' => 'Updated audit token',
				'expires_at' => 1611238090,
				'description' => 'Updated description',
				'status' => 1
			]
		]);

		$updated = json_encode([
			'token.name' => ['update', 'Updated audit token', 'test-token'],
			'token.expires_at' => ['update', '1611238090', '0'],
			'token.description' => ['update', 'Updated description', ''],
			'token.status' => ['update', '1', '0']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::TOKENID);
	}

	public function testAuditlogToken_Delete() {
		$this->call('token.delete', [self::TOKENID]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated audit token', self::TOKENID);
	}
}
