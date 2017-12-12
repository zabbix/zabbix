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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormLoginWithRequest extends CWebTest {
	// Returns layout data
	public static function provider() {
		return [
			[
				[
					'request' => 'index.php?request=hosts.php',
					'header' => 'Configuration of hosts'
				],
				[
					'request' => 'index.php?request=zabbix.php%3Faction%3Dproxy.list',
					'header' => 'Proxies'
				]
			]
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function testFormLoginWithRequest_test($data) {
		// Log in.
		$this->zbxTestLogin($data['request']);

		// Test page title.
		$this->zbxTestCheckHeader($data['header']);
	}
}
