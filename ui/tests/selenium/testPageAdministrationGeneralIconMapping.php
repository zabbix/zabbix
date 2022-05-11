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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageAdministrationGeneralIconMapping extends CLegacyWebTest {

	public function testPageAdministrationGeneralIconMapping_CheckLayout(){
		$this->zbxTestLogin('zabbix.php?action=gui.edit');
		$this->query('id:page-title-general')->asPopupButton()->one()->select('Icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$strings = [];

		foreach (CDBHelper::getAll('SELECT name FROM icon_map') as $iconname) {
			$strings[] = $iconname['name'];
		}

		foreach (CDBHelper::getAll('SELECT expression FROM icon_mapping') as $expression) {
			$strings[] = $expression['expression'];
		}

		$this->zbxTestTextPresent($strings);
	}
}
