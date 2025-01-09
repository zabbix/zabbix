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

class CGeomapCoordinatesParserTest extends TestCase {

	/**
	 * An array of geomap coordinates and parsed results.
	 */
	public function dataProvider() {
		return [
			// PARSE_SUCCESS
			['51.5285582,-0.2813,10', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'result' => [
					'latitude' => '51.5285582',
					'longitude' => '-0.2813',
					'zoom' => '10'
				]
			]],
			['51.5285582,-0.2416813', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'result' => [
					'latitude' => '51.5285582',
					'longitude' => '-0.2416813'
				]
			]],

			// PARSE_FAIL
			['91.5285582,-0.2416813,10', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			['-91.5285582,-0.2416813,10', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			['90,181,10', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			['90,-181,10', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			['90,100,29.9', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			['51.5285582,180,10,10', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			['51.5285582,,10', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			[',51.5285582,10', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			[',,', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]],
			['', 0, [
				'rc' => CParser::PARSE_FAIL,
				'result' => []
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $expected) {

		$geo_coordinates_parser = new CGeomapCoordinatesParser();

		$this->assertSame($expected, [
			'rc' => $geo_coordinates_parser->parse($source, $pos),
			'result' => $geo_coordinates_parser->result
		]);
	}
}
