<?php
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

class CRelativeTimeParserTest extends TestCase {

	/**
	 * An array of relative times and parsed results.
	 */
	public static function dataProvider() {
		return [
			[
				'now', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [],
					'match' => 'now'
				]
			],
			[
				'now/y', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'y']
					],
					'match' => 'now/y'
				]
			],
			[
				'now/M', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M']
					],
					'match' => 'now/M'
				]
			],
			[
				'now/w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w']
					],
					'match' => 'now/w'
				]
			],
			[
				'now/d', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'd']
					],
					'match' => 'now/d'
				]
			],
			[
				'now/h', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'h']
					],
					'match' => 'now/h'
				]
			],
			[
				'now/m', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'm']
					],
					'match' => 'now/m'
				]
			],
			[
				'now/s', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [],
					'match' => 'now'
				]
			],
			[
				'now-1y', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'y']
					],
					'match' => 'now-1y'
				]
			],
			[
				'now-1M', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'M']
					],
					'match' => 'now-1M'
				]
			],
			[
				'now-1w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'w']
					],
					'match' => 'now-1w'
				]
			],
			[
				'now-1h', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'h']
					],
					'match' => 'now-1h'
				]
			],
			[
				'now-1m', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'm']
					],
					'match' => 'now-1m'
				]
			],
			[
				'now-1s', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 's']
					],
					'match' => 'now-1s'
				]
			],
			[
				'now-1x', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 's']
					],
					'match' => 'now-1'
				]
			],
			[
				'now/M-1y', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'y']
					],
					'match' => 'now/M-1y'
				]
			],
			[
				'now/M-1M', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'M']
					],
					'match' => 'now/M-1M'
				]
			],
			[
				'now/M-1w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'w']
					],
					'match' => 'now/M-1w'
				]
			],
			[
				'now/M-1h', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'h']
					],
					'match' => 'now/M-1h'
				]
			],
			[
				'now/M-1m', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'm']
					],
					'match' => 'now/M-1m'
				]
			],
			[
				'now/M-1s', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 's']
					],
					'match' => 'now/M-1s'
				]
			],
			[
				'now/M-1x', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 's']
					],
					'match' => 'now/M-1'
				]
			],
			[
				'now/M-1y/w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w']
					],
					'match' => 'now/M-1y/w'
				]
			],
			[
				'now/M-1M/w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w']
					],
					'match' => 'now/M-1M/w'
				]
			],
			[
				'now/M-1w/w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'w'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w']
					],
					'match' => 'now/M-1w/w'
				]
			],
			[
				'now/M-1h/w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'h'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w']
					],
					'match' => 'now/M-1h/w'
				]
			],
			[
				'now/M-1m/w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'm'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w']
					],
					'match' => 'now/M-1m/w'
				]
			],
			[
				'now/M-1s/w', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 's'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w']
					],
					'match' => 'now/M-1s/w'
				]
			],
			[
				'now/M-1s/x', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 's']
					],
					'match' => 'now/M-1s'
				]
			],
			[
				'now-300', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '300', 'suffix' => 's']
					],
					'match' => 'now-300'
				]
			],
			[
				'now-2147483647', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '2147483647', 'suffix' => 's']
					],
					'match' => 'now-2147483647'
				]
			],
			[
				'now-2147483648', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [],
					'match' => 'now'
				]
			],
			[
				'now-300s', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '300', 'suffix' => 's']
					],
					'match' => 'now-300s'
				]
			],
			[
				'now-777/d', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '777', 'suffix' => 's'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'd']
					],
					'match' => 'now-777/d'
				]
			],
			[
				'now/M-300', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '300', 'suffix' => 's']
					],
					'match' => 'now/M-300'
				]
			],
			[
				'now/M-300s', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '300', 'suffix' => 's']
					],
					'match' => 'now/M-300s'
				]
			],
			[
				'now/M-777/d', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '777', 'suffix' => 's'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'd']
					],
					'match' => 'now/M-777/d'
				]
			],
			[
				'now/y/M/w/d/h/m-1y+2M-4w-3d+2h+15m-25s-7/d', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'd'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'h'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'm'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '+', 'value' => '2', 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '4', 'suffix' => 'w'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '3', 'suffix' => 'd'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '+', 'value' => '2', 'suffix' => 'h'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '+', 'value' => '15', 'suffix' => 'm'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '25', 'suffix' => 's'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '7', 'suffix' => 's'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'd']
					],
					'match' => 'now/y/M/w/d/h/m-1y+2M-4w-3d+2h+15m-25s-7/d'
				]
			],
			[
				'now/y/M/w/d/h/m-1y+2?-4w-3d+2h+15m-25s-7/d', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'd'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'h'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'm'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '+', 'value' => '2', 'suffix' => 's']
					],
					'match' => 'now/y/M/w/d/h/m-1y+2'
				]
			],
			[
				'now-300sTEXT', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '300', 'suffix' => 's']
					],
					'match' => 'now-300s'
				]
			],
			[
				'{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [],
					'match' => '{$M}'
				]
			],
			[
				'{{$M}.regsub("^([0-9]+)", "{$M}: \1")}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [],
					'match' => '{{$M}.regsub("^([0-9]+)", "{$M}: \1")}'
				]
			],
			[
				'{$M: context}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [],
					'match' => '{$M: context}'
				]
			],
			[
				'{{$M: context}.regsub("^([0-9]+)", "{$M}: \1")}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [],
					'match' => '{{$M: context}.regsub("^([0-9]+)", "{$M}: \1")}'
				]
			],
			[
				'{$M}TEXT', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [],
					'match' => '{$M}'
				]
			],
			[
				'{{$M}.regsub("^([0-9]+)", "{$M}: \1")}TEXT', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [],
					'match' => '{{$M}.regsub("^([0-9]+)", "{$M}: \1")}'
				]
			],
			[
				'{$M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'tokens' => [],
					'match' => ''
				]
			],
			[
				'{#M}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [],
					'match' => '{#M}'
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'tokens' => [],
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}'
				]
			],
			[
				'{#M}TEXT', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [],
					'match' => '{#M}'
				]
			],
			[
				'{#M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'tokens' => [],
					'match' => ''
				]
			],
			[
				'now/y/M/w/d/h/m/{#OFFSET}-1y+2-{#ONE_DAY}?-4w-3d+2h+15m-25s-7/d', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'd'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'h'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'm'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '+', 'value' => '2', 'suffix' => 's']
					],
					'match' => 'now/y/M/w/d/h/m/{#OFFSET}-1y+2-{#ONE_DAY}'
				]
			],
			[
				'now/y/M/w/d/h/m/{$OFFSET}-1y+2-{$ONE_DAY}?-4w-3d+2h+15m-25s-7/d', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'tokens' => [
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'M'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'w'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'd'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'h'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_PRECISION, 'suffix' => 'm'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '-', 'value' => '1', 'suffix' => 'y'],
						['type' => CRelativeTimeParser::ZBX_TOKEN_OFFSET, 'sign' => '+', 'value' => '2', 'suffix' => 's']
					],
					'match' => 'now/y/M/w/d/h/m/{$OFFSET}-1y+2-{$ONE_DAY}'
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $options, $expected) {
		$parser = new CRelativeTimeParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'tokens' => $parser->getTokens(),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
