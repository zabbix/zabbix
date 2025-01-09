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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

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
