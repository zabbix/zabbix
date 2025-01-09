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

require_once dirname(__FILE__).'/general/GeneralTests.php';
require_once dirname(__FILE__).'/selenium/SeleniumTests.php';

use PHPUnit\Framework\TestSuite;

class All {
	public static function suite() {
		$suite = new TestSuite('Project');

		$suite->addTest(GeneralTests::suite());
		$suite->addTest(SeleniumTests::suite());

		return $suite;
	}
}
