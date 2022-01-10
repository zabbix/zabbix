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

class GetExpressionTreeTest extends TestCase {
	private $expression_parser;

	protected function setUp(): void {
		$this->expression_parser = new CExpressionParser(['usermacros' => true]);
	}

	protected function tearDown(): void {
		$this->expression_parser = null;
	}

	public function provider() {
		// make sure logical operators aren't parsed within the context of user macros

		$quoted = '{$USER_MACRO: "Logical operators - and or - should not be parsed"}';
		$unquoted = '{$USER_MACRO: Logical operators - and or - should not be parsed}';

		return [
			// simple expressions
			[
				$quoted.'>0',
				'E'
			],
			[
				$unquoted.'>0',
				'E'
			],
			[
				$quoted.'>'.$unquoted,
				'E'
			],

			// expressions with some simple logic
			[
				$quoted.'>0 and '.$unquoted.'>0',
				'(E and E)'
			],
			[
				$quoted.'>0 or '.$unquoted.'>0',
				'(E or E)'
			],
			[
				$quoted.'>0 or '.$unquoted.'>0 or '.$quoted.'>'.$unquoted,
				'(E or E or E)'
			],
			[
				$quoted.'>0 and '.$unquoted.'>0 and '.$quoted.'>'.$unquoted,
				'(E and E and E)'
			],

			// expressions with some complex (nested) logic
			[
				$quoted.'>0 or '.$unquoted.'>0 and '.$quoted.'>'.$unquoted,
				'(E or (E and E))'
			],
			[
				$quoted.'>0 and '.$unquoted.'>0 or '.$quoted.'>'.$unquoted,
				'((E and E) or E)'
			],
			[
				$quoted.'>0 and '.$unquoted.'>0 or '.$quoted.'>'.$unquoted.' and '.$quoted.'>0 and '.$unquoted.'>0 or '.
					$quoted.'>'.$unquoted,
				'((E and E) or (E and E and E) or E)'
			],
			[
				$quoted.'>0 and '.$unquoted.'>0 or '.$quoted.'>'.$unquoted.' or '.$quoted.'>0 and '.$unquoted.'>0 or '.
					$quoted.'>'.$unquoted.' and '.$quoted.'>0',
				'((E and E) or E or (E and E) or (E and E))'
			]
		];
	}

	/**
	 * Testing if logical operators get parsed correctly by getExpressionTree in trigger expressions.
	 *
	 * @dataProvider provider
	 *
	 * @param $expression
	 * @param $expected_parsed
	 */
	public function test($expression, $expected_parsed) {
		$this->assertSame(CParser::PARSE_SUCCESS, $this->expression_parser->parse($expression));

		$result = getExpressionTree($this->expression_parser, 0, $this->expression_parser->getLength() - 1);

		if (!is_array($result)) {
			$this->fail('getExpressionTree did not return an array');
		}

		$result_parsed = self::parseResult($result);

		$this->assertSame($result_parsed, $expected_parsed);
	}

	/**
	 * Generates a one string logical representation of the result of getExpressionTree function.
	 */
	private static function parseResult(array $result) {
		if ($result['type'] === 'expression') {
			return 'E';
		}

		$subset = array_map(function(array $element) {
			return self::parseResult($element);
		}, $result['elements']);

		return '(' . implode(' ' . $result['operator'] . ' ', $subset) . ')';
	}
}
