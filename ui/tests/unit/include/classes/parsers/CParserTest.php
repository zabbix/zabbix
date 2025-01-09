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

abstract class CParserTest extends TestCase {

	abstract public function dataProvider();

	/**
	 * Return an instance of the tested parser.
	 *
	 * @return CParser
	 */
	abstract protected function getParser();

	/**
	 * @dataProvider dataProvider
	 *
	 * @param $string
	 * @param $pos
	 * @param $expected_rc
	 * @param $expected_match
	 */
	public function testParseValid($string, $pos, $expected_rc, $expected_match) {
		$parser = $this->getParser();

		$this->assertSame(
			[
				'rc' => $expected_rc,
				'match' => $expected_match,
				'length' => strlen($expected_match)
			],
			[
				'rc' => $parser->parse($string, $pos),
				'match' => $parser->getMatch(),
				'length' => $parser->getLength()
			]
		);
	}
}
