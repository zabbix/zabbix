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

class CXmlExportWriterTest extends TestCase {

	public function dataProvider() {
		return [
			[
				[
					'root' => [
						'string' => 'string',
						'spaces' => '  ',
						'lr_spaces' => ' string ',
						'null' => null,
						'empty' => '',
						'array' => [
							'string' => 'string'
						]
					]
				],
				'<'.'?xml version="1.0" encoding="UTF-8"?'.'>'."\n".
				'<root>'."\n".
				'    <string>string</string>'."\n".
				'    <spaces><![CDATA[  ]]></spaces>'."\n".
				'    <lr_spaces> string </lr_spaces>'."\n".
				'    <null/>'."\n".
				'    <empty/>'."\n".
				'    <array>'."\n".
				'        <string>string</string>'."\n".
				'    </array>'."\n".
				'</root>'."\n"
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param array $array
	 * @param mixed $expected
	 */
	public function test_writeXml(array $array, $expected) {
		$writer = new CXmlExportWriter();
		$xml = $writer->write($array);

		$this->assertEquals($xml, $expected);
	}
}
