<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
		$error = [
			'code' => -32602,
			'message' => 'Invalid params.',
			'data' => 'The "apiinfo.version" method must be called without authorization header.'
		];

		$this->call('apiinfo.version', [], $error);
	}

	public function testAPIInfo_VersionWithoutAuth() {
		$this->disableAuthorization();
		$result = $this->call('apiinfo.version', []);

		$this->assertSame('7.0.0', $result['result']);
	}
}
