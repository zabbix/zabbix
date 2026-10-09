<?php declare(strict_types=0);
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


use PHPUnit\Framework\TestCase;

class CFrontendActionValidatorTest extends TestCase {

	public function dataProvider(): array {
		return [
			// Legacy action controllers.
			['browserwarning.php',																	null],
			['chart.php',																			null],
			['chart2.php',																			null],
			['chart3.php',																			null],
			['chart4.php',																			null],
			['chart6.php',																			null],
			['chart7.php',																			null],
			['history.php',																			null],
			['hostinventories.php',																	null],
			['hostinventoriesoverview.php',															null],
			['httpconf.php',																		null],
			['httpdetails.php',																		null],
			['image.php',																			null],
			['imgstore.php',																		null],
			['jsrpc.php',																			null],
			['map.php',																				null],
			['report4.php',																			null],
			['sysmap.php',																			null],
			['sysmaps.php',																			null],
			['tr_events.php',																		null],

			// URL parameters doesn't matter in legacy actions.
			['map.php?param=value',																	null],
			['map.php?action=host.list',															null],
			['map.php?action=invalid',																null],

			// MVC action controllers with correct actions.
			['zabbix.php?action=dashboard.view',													null],
			['zabbix.php?action=host.list',															null],
			['zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D=10084',	null],
			['zabbix.php?action=gui.edit',															null],

			// Registered actions are considered valid regardless of the layout.
			['zabbix.php?action=hostgroup.update',													null],
			['zabbix.php?action=host.massdelete',													null],

			// Invalid Frontend URLs.
			['https://www.zabbix.com',																'a relative URL to the frontend is expected'],
			['https://www.zabbix.com/',																'a relative URL to the frontend is expected'],
			['https://www.zabbix.com/ui/zabbix.php',												'a relative URL to the frontend is expected'],
			['https://www.zabbix.com/ui/zabbix.php?action=host.list',								'a relative URL to the frontend is expected'],
			['www.zabbix.com/ui/zabbix.php?action=host.list',										'a relative URL to the frontend is expected'],
			['/ui/zabbix.php?action=host.list',														'a relative URL to the frontend is expected'],
			['ui/zabbix.php?action=host.list',														'a relative URL to the frontend is expected'],
			['/zabbix.php?action=host.list',														'a relative URL to the frontend is expected'],
			['?action=host.list',																	'a relative URL to the frontend is expected'],
			['action=host.list',																	'a relative URL to the frontend is expected'],
			['host.list',																			'a relative URL to the frontend is expected'],
			['zabbix.php?action[]=invalid',															'a relative URL to the frontend is expected'],
			['zabbix.php?action[123]=invalid',														'a relative URL to the frontend is expected'],
			['zabbix.php?action[123][456]=invalid',													'a relative URL to the frontend is expected'],
			['zabbix.php?no_action=123',															'a relative URL to the frontend is expected'],
			['zabbix.php?action=invalid',															'invalid action in the frontend URL'],

			// Excluded legacy controllers.
			['index.php',																			'a relative URL to the frontend is expected'],
			['index_http.php',																		'a relative URL to the frontend is expected'],
			['index_mfa.php',																		'a relative URL to the frontend is expected'],
			['index_sso.php',																		'a relative URL to the frontend is expected']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testFrontendUrlValidator($url, $expected_error) {
		$validator = new CFrontendActionValidator();

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($url));
		$this->assertSame($expected_error, $validator->getError());
	}
}
