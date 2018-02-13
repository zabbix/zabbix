<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


/**
 * Class containing methods to test CIPRangeParser class functionality.
 */
class CStatusCodeParserTest extends PHPUnit_Framework_TestCase {

	public function testProvider() {
		return [
			[
				'{$MACRO}', ['usermacros' => true], true
			],
			[
				'{$MACRO}-{$MACRO}', ['usermacros' => true], true
			],
			[
				'200-{$MACRO}', ['usermacros' => true], true
			],
			[
				'{$MACRO}-200', ['usermacros' => true], true
			],
			[
				'200-400', [], true
			],
			[
				'200', [], true
			],
			[
				'200,301', [], true
			],
			[
				'{$MACRO}-{$MACRO},{$MACRO},{$MACRO}-200,200-{$MACRO},200-301', ['usermacros' => true], true
			],
			[
				'{$MACRO}', [], false
			],
			[
				'200-{$MACRO}', ['usermacros' => false], false
			]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param array  $options
	 * @param array  $expected
	*/
	public function testParse($source, $options, $expected) {
		$parser = new CStatusCodesParser($options);

		$this->assertSame($expected, $parser->parse($source));
	}
}
