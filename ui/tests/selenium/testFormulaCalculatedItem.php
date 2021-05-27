<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testCalculatedFormula.php';

/**
 * @backup items
 */
class testFormulaCalculatedItem extends testCalculatedFormula {

	public $url = 'items.php?form=create&hostid=40001&context=host';

	/**
	 * Test for checking formula field in calculated item.
	 *
	 * @dataProvider getCommonValidationData
	 */
	public function testFormulaCalculatedItem_Validation($data) {
		$this->executeValidation($data);
	}
}
