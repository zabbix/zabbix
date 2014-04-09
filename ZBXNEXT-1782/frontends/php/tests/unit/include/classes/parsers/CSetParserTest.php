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


class CSetParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CSetParser
	 */
	protected $parser;

	public function setUp() {
		$this->parser = new CSetParser(array('<', '>', '<>', 'and', 'or'));
	}

	public function validProvider() {
		return array(
			array('<', 0, '<', 1),
			array('<=', 0, '<', 1),
			array('>', 0, '>', 1),
			array('>=', 0, '>', 1),
			array('<>', 0, '<>', 2),
			array('<>=', 0, '<>', 2),
			array('and', 0, 'and', 3),
			array('and this', 0, 'and', 3),
			array('or', 0, 'or', 2),
			array('or this', 0, 'or', 2),

			array('prefix<', 6, '<', 7),
			array('prefix<=', 6, '<', 7),
			array('prefix>', 6, '>', 7),
			array('prefix>=', 6, '>', 7),
			array('prefix<>', 6, '<>', 8),
			array('prefix<>=', 6, '<>', 8),
			array('prefixand', 6, 'and', 9),
			array('prefixand this', 6, 'and', 9),
			array('prefixor', 6, 'or', 8),
			array('prefixor this', 6, 'or', 8),

			array('><', 0, '>', 1),
		);
	}

	/**
	 * @dataProvider validProvider
	 *
	 * @param $string
	 * @param $pos
	 * @param $expectedMatch
	 * @param $expectedEndPos
	 */
	public function testParseValid($string, $pos, $expectedMatch, $expectedEndPos) {
		$result = $this->parser->parse($string, $pos);

		$this->assertSame($expectedMatch, $result->match);
		$this->assertSame($expectedEndPos, $result->endPos);
		$this->assertSame($string, $result->source);
		$this->assertSame($pos, $result->startPos);
	}

	public function invalidProvider() {
		return array(
			array('', 0, 0),
			array('an', 0, 2),
			array('anor', 0, 4),
			array('+<', 0, 0),

			array('prefixand', 5, 5),
			array('prefixand', 7, 9),
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

		$this->assertSame(null, $result->match);
		$this->assertSame($expectedEndPos, $result->endPos);
		$this->assertSame($string, $result->source);
		$this->assertSame($pos, $result->startPos);
	}
}
