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

/**
 * @backup hosts, hstgrp
 */
class testHostImport extends CAPITest {

	public function testDiscoveredHostGroupsAfterImportParentHost() {
		$source = file_get_contents(dirname(__FILE__).'/xml/testDiscoveredHostGroupsAfterImportParentHost.xml');

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
}
