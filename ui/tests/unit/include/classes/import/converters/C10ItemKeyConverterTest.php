<?php declare(strict_types = 0);
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


use PHPUnit\Framework\TestCase;

class C10ItemKeyConverterTest extends TestCase {

	public function dataProvider() {
		$keys = ['tcp', 'ftp', 'http', 'imap', 'ldap', 'nntp', 'ntp', 'pop', 'smtp', 'ssh'];

		$data = [];
		foreach ($keys as $key) {
			$data[] = [$key, 'net.tcp.service['.$key.']'];
			$data[] = [$key.',', 'net.tcp.service['.$key.']'];
			$data[] = [$key.',1', 'net.tcp.service['.$key.',,1]'];
			$data[] = [$key.',{$PORT}', 'net.tcp.service['.$key.',,{$PORT}]'];
			$data[] = [$key.'_perf', 'net.tcp.service.perf['.$key.']'];
			$data[] = [$key.'_perf,', 'net.tcp.service.perf['.$key.']'];
			$data[] = [$key.'_perf,1', 'net.tcp.service.perf['.$key.',,1]'];
			$data[] = [$key.'_perf,{$PORT}', 'net.tcp.service.perf['.$key.',,{$PORT}]'];
		}

		// keys that shouldn't be converted
		$data[] = ['myitem', 'myitem'];
		$data[] = ['ftp,10,20', 'ftp,10,20'];
		$data[] = ['vfs.fs.size["/var/tmp", pfree]', 'vfs.fs.size["/var/tmp", pfree]'];

		return $data;
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param $key
	 * @param $expectedResult
	 */
	public function testConvert($key, $expectedResult) {
		$converter = new C10ItemKeyConverter();
		$result = $converter->convert($key);

		$this->assertEquals($expectedResult, $result);
	}

}
