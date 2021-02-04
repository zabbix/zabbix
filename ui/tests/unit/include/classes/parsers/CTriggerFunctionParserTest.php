<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CTriggerFunctionParserTest extends PHPUnit_Framework_TestCase {

	public static function testProvider() {
		return [
			[
				'func(/host/item)', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'func(/host/item)',
					'host' => 'host',
					'item' => 'item',
					'function' => 'func',
					'parameters' => '/host/item',
					'params_raw' => [
						'type' => CFunctionParser::PARAM_ARRAY,
						'raw' => '(/host/item)',
						'pos' => 4,
						'parameters' => [
							0 => [
								'type' => CFunctionParser::PARAM_UNQUOTED,
								'raw' => '/host/item',
								'pos' => 1
							]
						]
					]
				],
				['/host/item']
			],
			[
				'func(/host/item', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'host' => '',
					'item' => '',
					'function' => '',
					'parameters' => '',
					'params_raw' => []
				],
				[]
			]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 * @param array  $unquoted_params
	 */
	public function testParse(string $source, int $pos, array $expected, array $unquoted_params) {
		static $function_parser = null;

		if ($function_parser === null) {
			$function_parser = new CTriggerFunctionParser();
		}

		$this->assertSame($expected, [
			'rc' => $function_parser->parse($source, $pos),
			'match' => $function_parser->getMatch(),
//			'host' => $function_parser->getHost(),
//			'item' => $function_parser->getItem(),
			'function' => $function_parser->getFunction(),
			'parameters' => $function_parser->getParameters(),
			'params_raw' => $function_parser->getParamsRaw()
		]);
		$this->assertSame(strlen($expected['match']), $function_parser->getLength());
		$this->assertSame(count($unquoted_params), $function_parser->getParamsNum());

		for ($n = 0, $count = $function_parser->getParamsNum(); $n < $count; $n++) {
			$this->assertSame($unquoted_params[$n], $function_parser->getParam($n));
		}
	}
}
