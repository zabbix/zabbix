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
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testAuditlogProxy extends testAuditlogCommon {

	public static function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'proxies' => [
				[
					'name' => 'proxy.for.create.audit',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'description' => 'Proxy audit description',
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'allowed_addresses' => 'localhost',
					'tls_psk_identity' => 'Audit',
					'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193478'
				],
				[
					'name' => 'proxy.for.update.audit'
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public function testAuditlogProxy_Create() {
		$proxyid = CTestDataHelper::getConvertedValueReference(':proxy:proxy.for.create.audit');
		$expected_details = json_encode([
			'proxy.name' => ['add', 'proxy.for.create.audit'],
			'proxy.description' => ['add', 'Proxy audit description'],
			'proxy.tls_accept' => ['add', (string) HOST_ENCRYPTION_PSK],
			'proxy.allowed_addresses' => ['add', 'localhost'],
			'proxy.tls_psk_identity' => ['add', '******'],
			'proxy.tls_psk' => ['add', '******'],
			'proxy.proxyid' => ['add', $proxyid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $expected_details, $proxyid);
	}

	public function testAuditlogProxy_Update() {
		$proxyid = CTestDataHelper::getConvertedValueReference(':proxy:proxy.for.update.audit');
		$request = [[
			'proxyid' => $proxyid,
			'name' => 'Updated Audit proxy',
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'description' => 'Update proxy audit description',
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'allowed_addresses' => 'updated_address',
			'tls_psk_identity' => 'Updated_TSK',
			'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193111'
		]];
		$expected_details = json_encode([
			'proxy.name' => ['update', 'Updated Audit proxy', 'proxy.for.update.audit'],
			'proxy.description' => ['update', 'Update proxy audit description', ''],
			'proxy.tls_accept' => ['update', (string) HOST_ENCRYPTION_PSK, (string) HOST_ENCRYPTION_NONE],
			'proxy.allowed_addresses' => ['update', 'updated_address', ''],
			'proxy.tls_psk_identity' => ['update', '******', '******'],
			'proxy.tls_psk' => ['update', '******', '******']
		]);

		$this->call('proxy.update', $request);

		$this->getAuditDetails('details', $this->update_actionid, $expected_details, $proxyid);
	}

	/**
	 * @depends testAuditlogProxy_Create
	 */
	public function testAuditlogProxy_Delete() {
		$proxyid = CTestDataHelper::getConvertedValueReference(':proxy:proxy.for.create.audit');

		$this->call('proxy.delete', [$proxyid]);
		CTestDataHelper::unsetDeletedObjectIds([':proxy:proxy.for.create.audit']);

		$this->getAuditDetails('resourcename', $this->delete_actionid, 'proxy.for.create.audit', $proxyid);
	}
}
