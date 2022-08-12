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
 * @backup hosts, ids
 */
class testAuditlogProxy extends CAPITest {

	protected static $resourceid;

	public function testAuditlogProxy_Create() {
		$created = "{\"proxy.host\":[\"add\",\"Audit proxy\"],\"proxy.status\":[\"add\",\"5\"],\"proxy.description".
				"\":[\"add\",\"Proxy audit description\"],\"proxy.tls_accept\":[\"add\",\"2\"],\"proxy.proxy_address".
				"\":[\"add\",\"localhost\"],\"proxy.tls_psk_identity\":[\"add\",\"******\"],\"proxy.tls_psk\":[".
				"\"add\",\"******\"],\"proxy.proxyid\":[\"add\",\"131004\"]}";

		$create = $this->call('proxy.create', [
			[
				'host' => 'Audit proxy',
				'status' => '5',
				'description' => 'Proxy audit description',
				'tls_accept' => '2',
				'proxy_address' => 'localhost',
				'tls_psk_identity' => 'Audit',
				'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193478'
			]
		]);

		self::$resourceid = $create['result']['proxyids'][0];
		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogProxy_Update() {
		$updated = "{\"proxy.host\":[\"update\",\"Updated Audit proxy\",\"Audit proxy\"],\"proxy.description\":[".
				"\"update\",\"Update proxy audit description\",\"Proxy audit description\"],\"proxy.proxy_address".
				"\":[\"update\",\"updated_address\",\"localhost\"],\"proxy.tls_psk_identity\":[\"update\",\"******\",".
				"\"******\"],\"proxy.tls_psk\":[\"update\",\"******\",\"******\"]}";

		$this->call('proxy.update', [
			[
				'proxyid' => self::$resourceid,
				'host' => 'Updated Audit proxy',
				'status' => '5',
				'description' => 'Update proxy audit description',
				'tls_accept' => '2',
				'proxy_address' => 'updated_address',
				'tls_psk_identity' => 'Updated_TSK',
				'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193111'
			]
		]);

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogProxy_Delete() {
		$this->call('proxy.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'Updated Audit proxy');
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
