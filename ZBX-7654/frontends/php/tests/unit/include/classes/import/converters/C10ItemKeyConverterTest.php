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


class C10ItemKeyConverterTest extends PHPUnit_Framework_TestCase {

	public function dataProvider() {
		$keys = array(
			'tcp',
			'ftp',
			'http',
			'imap',
			'ldap',
			'nntp',
			'ntp',
			'pop',
			'smtp',
			'ssh'
		);

		$data = array();
		foreach ($keys as $key) {
			$data[] = array($key, 'net.tcp.service['.$key.',,]');
			$data[] = array($key.',1', 'net.tcp.service['.$key.',,1]');
			$data[] = array($key.'_perf', 'net.tcp.service.perf['.$key.',,]');
			$data[] = array($key.'_perf,1', 'net.tcp.service.perf['.$key.',,1]');
		}

		// keys that shouldn't be converted
		$data[] = array('myitem', 'myitem');

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
