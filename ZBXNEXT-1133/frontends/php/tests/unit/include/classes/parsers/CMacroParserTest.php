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


class CMacroParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CSetParser
	 */
	protected $parser;

	public function setUp() {
		$this->parser = new CMacroParser('#');
	}

	public function validProvider() {
		return array(
			array('{#M}', 0, '{#M}', 4),
			array('{#MACRO12.A_Z}', 0, '{#MACRO12.A_Z}', 14),
			array('{#MACRO} = 0', 0, '{#MACRO}', 8),
			array('not {#MACRO} = 0', 4, '{#MACRO}', 8),
		);
	}

	/**
	 * @dataProvider validProvider
	 *
	 * @param $string
	 * @param $pos
	 * @param $expectedMatch
	 * @param $expectedLength
	 */
	public function testParseValid($string, $pos, $expectedMatch, $expectedLength) {
		$result = $this->parser->parse($string, $pos);

		$this->assertTrue($result instanceof CParserResult);
		$this->assertSame($expectedMatch, $result->match);
		$this->assertSame($expectedLength, $result->length);
		$this->assertSame($string, $result->source);
		$this->assertSame($pos, $result->pos);
	}

	public function invalidProvider() {
		return array(
			array('', 0, 0),
			array('A', 0, 0),
			array('{A', 0, 1),
			array('{#', 0, 2),
			array('{#}', 0, 2),
			array('{#A', 0, 3),
			array('{#a}', 0, 2),
			array('{#+}', 0, 2),
		);
	}

	/**
	 * @dataProvider invalidProvider
	 *
	 * @param $string
	 * @param $pos
	 * @param $expectedEndPos
	 */
	public function testParseInvalid($string, $pos, $expectedEndPos) {
		$result = $this->parser->parse($string, $pos);

		$this->assertSame(false, $result);
		$this->assertSame($expectedEndPos, $this->parser->getPos());
	}
}
