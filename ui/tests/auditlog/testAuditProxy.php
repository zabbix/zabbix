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

require_once dirname(__FILE__).'/testPageReportsAuditValues.php';

/**
 * @backup hosts, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditProxy extends testPageReportsAuditValues {

	/**
	 * Id of proxy.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "proxy.description: Proxy audit description".
			"\nproxy.host: Audit proxy".
			"\nproxy.proxy_address: localhost".
			"\nproxy.proxyid: 10519".
			"\nproxy.status: 5".
			"\nproxy.tls_accept: 2".
			"\nproxy.tls_psk: ******".
			"\nproxy.tls_psk_identity: ******";

	public $updated = "proxy.description: Proxy audit description => Update proxy audit description".
			"\nproxy.host: Audit proxy => Updated Audit proxy".
			"\nproxy.proxy_address: localhost => updated_address".
			"\nproxy.tls_psk: ****** => ******".
			"\nproxy.tls_psk_identity: ****** => ******";

	public $deleted = 'Description: Updated Audit proxy';

	public $resource_name = 'Proxy';

	public $config_refresh = 'Description: Updated Audit proxy';

	public function prepareCreateData() {
		$ids = CDataHelper::call('proxy.create', [
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
		$this->assertArrayHasKey('proxyids', $ids);
		self::$ids = $ids['proxyids'][0];
	}

	/**
	 * Check audit of created Proxy.
	 */
	public function testAuditProxy_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

//	This function is only for Master.
//	/**
//	 * Check audit of refreshed Proxy.
//	 */
//	public function testAuditProxy_ConfigRefresh() {
//		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
//		$this->selectTableRows();
//		$this->query('button:Refresh configuration')->one()->click();
//		$this->page->acceptAlert();
//		$this->page->waitUntilReady();
//
//		$this->checkAuditValues(self::$ids, 'Configuration refresh');
//	}

	/**
	 * Check audit of updated Proxy.
	 */
	public function testAuditProxy_Update() {
		CDataHelper::call('proxy.update', [
			[
				'proxyid' => self::$ids,
				'host' => 'Updated Audit proxy',
				'status' => '5',
				'description' => 'Update proxy audit description',
				'tls_accept' => '2',
				'proxy_address' => 'updated_address',
				'tls_psk_identity' => 'Updated_TSK',
				'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193111'
			]
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of deleted Proxy.
	 */
	public function testAuditProxy_Delete() {
		CDataHelper::call('proxy.delete', [self::$ids]);

		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
