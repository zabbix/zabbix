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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../../include/classes/helpers/CArrayHelper.php';

/**
 * @backup hosts, hstgrp
 */
class testHostImport extends CAPITest {

	public function testDiscoveredHostGroupsAfterImportParentHost() {
		$source = file_get_contents(__DIR__.'/xml/testDiscoveredHostGroupsAfterImportParentHost.xml');

		$rules = [
			'host_groups' => [
				'createMissing' => true
			],
			'hosts' => [
				'updateExisting' => true,
				'createMissing' => true
			],
			'items' => [
				'updateExisting' => true,
				'createMissing' => true
			],
			'discoveryRules' => [
				'updateExisting' => true,
				'createMissing' => true
			]
		];

		$this->call('configuration.import', [
			'format' => 'xml',
			'source' => $source,
			'rules' => $rules
		], null);

		$this->assertEquals(2, CDBHelper::getCount(
			'SELECT NULL'.
			' FROM hstgrp'.
			' WHERE name IN (\'Master group\', \'12345\')'
		));

		$this->assertEquals(2, CDBHelper::getCount(
			'SELECT NULL'.
			' FROM hosts'.
			' WHERE host IN (\'Host having discovered hosts\', \'12345\')'
		));
	}

	public function testHostWithConditionalDefaults() {
		$source = file_get_contents(__DIR__.'/xml/testHostWithConditionalDefaults.xml');

		$rules = [
			'host_groups' => [
				'createMissing' => true
			],
			'hosts' => [
				'updateExisting' => true,
				'createMissing' => true
			],
			'items' => [
				'updateExisting' => true,
				'createMissing' => true
			],
			'discoveryRules' => [
				'updateExisting' => true,
				'createMissing' => true
			]
		];

		$this->call('configuration.import', [
			'format' => 'xml',
			'source' => $source,
			'rules' => $rules
		], null);

		$hosts = $this->call('host.get', [
			'output' => ['host'],
			'selectItems' => ['type', 'key_', 'value_type'],
			'filter' => [
				'host' => 'Host for testing defaults on conditional fields'
			]
		]);
		$this->assertArrayHasKey('result', $hosts);
		$host = $hosts['result'][0];
		unset($host['hostid']);

		$this->assertArrayHasKey('items', $host);
		CArrayHelper::sort($host['items'], ['key_']);
		$host['items'] = array_values($host['items']);

		$this->assertEquals($host, [
			'host' => 'Host for testing defaults on conditional fields',
			'items' => [
				[
					'type' => '18',
					'key_' => 'binary',
					'value_type' => '5'
				],
				[
					'type' => '22',
					'key_' => 'browser-item',
					'value_type' => '3'
				],
				[
					'type' => '0',
					'key_' => 'master-item',
					'value_type' => '4'
				]
			]
		]);
	}
}
