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


require_once dirname(__FILE__).'/../include/CAPITest.php';

class testAPIInfo extends CAPITest {
	public function testAPIInfo_VersionWithAuth() {
		$result = $this->call('apiinfo.version', []);

		$this->assertSame('8.0.0', $result['result']);
	}

	public function testAPIInfo_VersionWithInvalidToken() {
		$request = '{"jsonrpc": "2.0", "method": "apiinfo.version", "params": {}, "id": 1}';

		$result = $this->callRaw($request, '12345');

		$this->assertArrayHasKey('error', $result);
		$this->assertSame('Session terminated, re-login, please.', $result['error']['data']);
	}

	public function testAPIInfo_VersionWithoutAuth() {
		$this->disableAuthorization();

		$result = $this->call('apiinfo.version', []);

		$this->assertSame('8.0.0', $result['result']);
	}
}
