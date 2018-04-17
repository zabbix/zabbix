<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testPageAdministrationGeneralIconMapping extends CWebTest {

	public function testPageAdministrationGeneralIconMapping_CheckLayout(){
		$this->zbxTestLogin('adm.gui.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$strings = [];

		foreach (DBdata('SELECT name FROM icon_map', false) as $iconname) {
			$strings[] = $iconname[0]['name'];
		}

		foreach (DBdata('SELECT expression FROM icon_mapping', false) as $expression) {
			$strings[] = $expression[0]['expression'];
		}

		$this->zbxTestTextPresent($strings);
	}
}
