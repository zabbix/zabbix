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


class C10TriggerConverterTest extends PHPUnit_Framework_TestCase {

	public function dataProvider() {
		return array(
			array('{h1:item.last(0)}=0', '{h1:item.last(0)}=0'),
			array('{h1:ftp.item.last(0)}=0', '{h1:ftp.item.last(0)}=0'),
			array('{h1:ftp,1.last(0)}=0', '{h1:net.tcp.service[ftp,,1].last(0)}=0'),
			array('{h1:ftp,1.last(0)}=0&{h1:ftp,1.last(0)}=0', '{h1:net.tcp.service[ftp,,1].last(0)}=0&{h1:net.tcp.service[ftp,,1].last(0)}=0'),

			// these test cases are incorrect but are added to preserve the historical behavior
			array('{h1:ftp.last(0)}=0', '{h1:ftp.last(0)}=0'),
			array('{h1:ftp,1.last(0)}=0&{h1:ftp,2.last(0)}=0', '{h1:net.tcp.service[ftp,,1].last(0)}=0&{h1:ftp,2.last(0)}=0'),
		);
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param $expression
	 * @param $expectedResult
	 */
	public function testConvert($expression, $expectedResult) {
		$converter = new C10TriggerConverter(new C10ItemKeyConverter());
		$result = $converter->convert($expression);

		$this->assertEquals($expectedResult, $result);
	}

}
