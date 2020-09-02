<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageAdministrationGeneralValuemap extends CLegacyWebTest {

	public function testPageAdministrationGeneralValuemap_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=valuemap.list');
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestTextPresent(['Name', 'Value map']);
		$this->zbxTestAssertElementPresentXpath('//button[text()="Create value map"]');
		$this->zbxTestAssertElementPresentXpath('//button[text()="Import"]');

		$strings = [];

		foreach (CDBHelper::getAll('SELECT name FROM valuemaps ORDER BY name', 100) as $valuemap) {
			$strings[] = $valuemap['name'];
		}

		foreach (CDBHelper::getAll('SELECT value,newvalue FROM mappings m'.
				' INNER JOIN (SELECT valuemapid FROM valuemaps ORDER BY name LIMIT 100) v'.
				' ON m.valuemapid=v.valuemapid') as $mapping) {
			$strings[] = $mapping['value'].' ⇒ '.$mapping['newvalue'];
		}

		$this->zbxTestTextPresent($strings);
	}

	public function testPageAdministrationGeneralValuemap_SimpleUpdate() {
		$sqlValuemaps = 'select * from valuemaps order by valuemapid';
		$oldHashValuemap = CDBHelper::getHash($sqlValuemaps);

		$sqlMappings = 'select * from mappings order by mappingid';
		$oldHashMappings = CDBHelper::getHash($sqlMappings);

		$this->zbxTestLogin('zabbix.php?action=valuemap.list');

		// There is no need to check simple update of every valuemap.
		foreach (CDBHelper::getAll('SELECT name FROM valuemaps ORDER BY name', 10) as $valuemap) {
			$this->zbxTestClickLinkText($valuemap['name']);
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestClickWait('update');
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestTextPresent('Value map updated');
		}

		$newHashValuemap = CDBHelper::getHash($sqlValuemaps);
		$this->assertEquals($oldHashValuemap, $newHashValuemap);

		$newHashMappings = CDBHelper::getHash($sqlMappings);
		$this->assertEquals($oldHashMappings, $newHashMappings);
	}
}
