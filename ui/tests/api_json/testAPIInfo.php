<?php
/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

class testAPIInfo extends CAPITest {
	public function testAPIInfo_VersionWithAuth() {
		$error = [
			'code' => -32602,
			'message' => 'Invalid params.',
			'data' => 'The "apiinfo.version" method must be called without the "auth" parameter.'
		];

		$this->call('apiinfo.version', [], $error);
	}

	public function testAPIInfo_VersionWithoutAuth() {
		$this->disableAuthorization();
		$result = $this->call('apiinfo.version', []);

		$this->assertSame('6.0.42', $result['result']);
	}
}
