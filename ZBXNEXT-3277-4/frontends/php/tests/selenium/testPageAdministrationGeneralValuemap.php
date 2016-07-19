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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageAdministrationGeneralValuemap extends CWebTest {

	public static function allValuemaps() {
		return DBdata('select * from valuemaps');
	}

	/**
	* @dataProvider allValuemaps
	*/
	public function testPageAdministrationGeneralValuemap_CheckLayout($valuemap) {
		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestTextPresent(['Name', 'Value map']);
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestTextPresent($valuemap['name']);

		// checking that in the "Value map" column are correct values
		$sqlMappings = 'SELECT value,newvalue FROM mappings WHERE valuemapid='.$valuemap['valuemapid'];
		$result = DBselect($sqlMappings);
		while ($row = DBfetch($result)) {
			$this->zbxTestTextPresent($row['value'].' ⇒ '.$row['newvalue']);
		}
	}

	/**
	* @dataProvider allValuemaps
	*/
	public function testPageAdministrationGeneralValuemap_SimpleUpdate($valuemap) {
		$sqlValuemaps = 'select * from valuemaps order by valuemapid';
		$oldHashValuemap = DBhash($sqlValuemaps);

		$sqlMappings = 'select * from mappings order by mappingid';
		$oldHashMappings = DBhash($sqlMappings);

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickLinkText($valuemap['name']);
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Value map updated');

		$newHashValuemap = DBhash($sqlValuemaps);
		$this->assertEquals($oldHashValuemap, $newHashValuemap,
				"Chuck Norris: no-change valuemap update should not update data in table 'valuemaps'");

		$newHashMappings = DBhash($sqlMappings);
		$this->assertEquals($oldHashMappings, $newHashMappings,
				"Chuck Norris: no-change valuemap update should not update data in table 'mappings'");
	}
}
