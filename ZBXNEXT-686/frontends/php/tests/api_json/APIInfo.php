<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

class API_JSON_APIInfo extends CZabbixTest {

	public function testAPIInfo_VersionWithoutAuth() {
		$result = $this->api_call('apiinfo.version', [], $debug);

		$this->assertTrue(array_key_exists('result', $result));
		$this->assertFalse(array_key_exists('error', $result));
		$this->assertSame('3.0.4', $result['result']);
	}

	public function testAPIInfo_VersionWithAuth() {
		$result = $this->api_acall('apiinfo.version', [], $debug);

		$this->assertFalse(array_key_exists('result', $result));
		$this->assertTrue(array_key_exists('error', $result));
		$this->assertSame(
			[
				'code' => -32602,
				'message' => 'Invalid params.',
				'data' => 'The "apiinfo.version" method must be called without the "auth" parameter.'
			],
			$result['error']
		);
	}
}
