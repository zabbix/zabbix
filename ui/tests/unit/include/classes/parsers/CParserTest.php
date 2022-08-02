<?php declare(strict_types=1);
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
