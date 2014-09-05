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


abstract class CParserTest extends PHPUnit_Framework_TestCase {

	protected $resultClassName = 'CParserResult';

	abstract public function validProvider();
	abstract public function invalidProvider();

	/**
	 * Return an instance of the tested parser.
	 *
	 * @return CParser
	 */
	abstract protected function getParser();

	/**
	 * @dataProvider validProvider
	 *
	 * @param $string
	 * @param $pos
	 * @param $expectedMatch
	 * @param $expectedLength
	 */
	public function testParseValid($string, $pos, $expectedMatch, $expectedLength) {
		$result = $this->getParser()->parse($string, $pos);

		$this->assertEquals($this->resultClassName, get_class($result));
		$this->assertSame($expectedMatch, $result->match);
		$this->assertSame($expectedLength, $result->length);
		$this->assertSame($string, $result->source);
		$this->assertSame($pos, $result->pos);
	}

	/**
	 * @dataProvider invalidProvider
	 *
	 * @param $string
	 * @param $pos
	 * @param $expectedEndPos
	 */
	public function testParseInvalid($string, $pos, $expectedEndPos) {
		$parser = $this->getParser();

		$result = $parser->parse($string, $pos);

		$this->assertSame(false, $result);
		$this->assertSame($expectedEndPos, $parser->getPos());
	}
}
