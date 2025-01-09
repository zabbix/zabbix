<?php declare(strict_types = 0);
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


use PHPUnit\Framework\TestCase;

class C20ItemKeyConverterTest extends TestCase {

	public function dataProvider() {
		$data = [
			// correct keys
			['agent.ping', 'agent.ping'],
			['net.tcp.service', 'net.tcp.service'],
			['log["C:\errors.log"]', 'log["C:\errors.log"]'],
			['net.tcp.service[tcp]', 'net.tcp.service[tcp]'],
			['net.tcp.service["tcp"]', 'net.tcp.service["tcp"]'],
			['net.tcp.service.perf[tcp, 127.0.0.1, 10050]', 'net.tcp.service.perf[tcp, 127.0.0.1, 10050]'],
			['net.tcp.service.perf["tcp", "127.0.0.1", "10050"]', 'net.tcp.service.perf["tcp", "127.0.0.1", "10050"]'],
			['net.tcp.service[ntp]', 'net.udp.service[ntp]'],
			['net.tcp.service["ntp"]', 'net.udp.service["ntp"]'],
			['net.tcp.service[ntp, localhost, 123]', 'net.udp.service[ntp, localhost, 123]'],
			['net.tcp.service["ntp", "localhost", "123"]', 'net.udp.service["ntp", "localhost", "123"]'],
			['net.tcp.service.perf[ntp]', 'net.udp.service.perf[ntp]'],
			['net.tcp.service.perf["ntp"]', 'net.udp.service.perf["ntp"]'],
			['net.tcp.service.perf[ntp, localhost, 123]', 'net.udp.service.perf[ntp, localhost, 123]'],
			['net.tcp.service.perf["ntp", "localhost", "123"]', 'net.udp.service.perf["ntp", "localhost", "123"]'],
			// incorrect keys
			['key["]', 'key["]']
		];

		return $data;
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param $key
	 * @param $expected
	 */
	public function testConvert($key, $expected) {
		$converter = new C20ItemKeyConverter();
		$result = $converter->convert($key);

		$this->assertEquals($expected, $result);
	}

}
