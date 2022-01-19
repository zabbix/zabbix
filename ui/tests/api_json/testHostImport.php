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

class testHostImport extends CAPITest {

	public function testDiscoveredHostGroupsAfterImportParentHost() {
		$source = file_get_contents(dirname(__FILE__).'/xml/testDiscoveredHostGroupsAfterImportParentHost.xml');

		$rules = [
			'groups' => [
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

		$query = 'SELECT * FROM hstgrp WHERE name='.zbx_dbstr('host group discovered').' AND groupid='.zbx_dbstr(50026);
		$this->assertEquals(1, CDBHelper::getCount($query));

		$query = 'SELECT * FROM hosts_groups WHERE hostgroupid='.zbx_dbstr(50022).' AND hostid='.zbx_dbstr(99012).
			' AND groupid='.zbx_dbstr(50026);
		$this->assertEquals(1, CDBHelper::getCount($query));
	}
}
