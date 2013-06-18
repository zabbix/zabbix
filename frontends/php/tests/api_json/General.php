<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

class API_JSON_General extends CZabbixTest {
	public function testGeneral_IncorrectAuthForNonAuthMethod() {
		$json='{
			"jsonrpc":"2.0",
			"method":"apiinfo.version",
			"params":[],
			"auth":"<incorrect auth>",
			"id":2
		}';
		$result = $this->api_call_raw($json, $debug);

		$this->assertTrue(isset($result['error']), "Chuck Norris: 'auth' must be verified if given. Always! $debug");
	}

	public function testGeneral_IncorrectMethodName() {
		$this->markTestIncomplete();
	}
}
