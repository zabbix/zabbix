<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

	public function testAPIInfo_VersionWithAuth() {
		$result = $this->api_acall('apiinfo.version', array(), $debug);

		$this->assertTrue(isset($result['result']), $debug);
		$this->assertSame($result['result'], '2.3.4');
	}

	public function testAPIInfo_VersionWithoutAuth() {
		$result = $this->api_call('apiinfo.version', array(), $debug);

		$this->assertTrue(isset($result['result']), $debug);
		$this->assertSame($result['result'], '2.3.4');
	}

}
