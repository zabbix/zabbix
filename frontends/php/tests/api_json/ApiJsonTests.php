<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

require_once dirname(__FILE__).'/APIInfo.php';
require_once dirname(__FILE__).'/General.php';
require_once dirname(__FILE__).'/User.php';
require_once dirname(__FILE__).'/CHost.php';
require_once dirname(__FILE__).'/CItem.php';

class ApiJsonTests {
	public static function suite() {
		$suite = new PHPUnit_Framework_TestSuite('API_JSON');

		$suite->addTestSuite('API_JSON_APIInfo');
		$suite->addTestSuite('API_JSON_General');
		$suite->addTestSuite('API_JSON_User');
		$suite->addTestSuite('API_JSON_Host');
		$suite->addTestSuite('API_JSON_Item');

		return $suite;
	}
}
