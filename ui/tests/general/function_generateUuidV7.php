<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CTest.php';

class function_generateUuidV7 extends CTest {
	public function test_generateUuidV7_monotonicity() {
		$uuid_previous = generateUuidV7();

		for($i = 1; $i <= 100000; $i++) {
			$uuids_current = generateUuidV7();

			$this->assertTrue(strcmp($uuids_current, $uuid_previous) > 0);

			$uuid_previous = $uuids_current;
		}
	}
}
