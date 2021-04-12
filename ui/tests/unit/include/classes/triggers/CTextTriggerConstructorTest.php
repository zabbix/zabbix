<?php declare(strict_types=1);
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


use PHPUnit\Framework\TestCase;

class CTextTriggerConstructorTest extends TestCase {

	/**
	 * @var CTextTriggerConstructor
	 */
	protected $constructor;

	protected function setUp(): void {
		$this->constructor = new CTextTriggerConstructor(new CTriggerExpression());
	}

	public function dataProviderGetExpressionFromPartsValid() {
		return [
			[
				[
					[
						'value' => 'find(/host/item,,"regexp","test")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((find(/host/item,,"regexp","test"))<>0)'
			],
			[
				[
					[
						'value' => 'find(/host/item,,"regexp","test")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"regexp","test"))=0)'
			],
			[
				[
					[
						'value' => 'find(/host/item,,"regexp","a") and find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))<>0 and (find(/host/item,,"regexp","b"))<>0)'
			],
			[
				[
					[
						'value' => 'find(/host/item,,"regexp","a") or find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))<>0 or (find(/host/item,,"regexp","b"))<>0)'
			],
			[
				[
					[
						'value' => 'find(/host/item,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
					[
						'value' => 'find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'(((find(/host/item,,"regexp","a"))<>0) or ((find(/host/item,,"regexp","b"))<>0))'
			],
			[
				[
					[
						'value' => 'find(/host/item,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))=0) and ((find(/host/item,,"regexp","b"))=0)'
			],
			[
				[
					[
						'value' => 'find(/host/item,,"regexp","a") and find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
					[
						'value' => 'find(/host/item,,"regexp","c") or find(/host/item,,"regexp","d")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'(((find(/host/item,,"regexp","a"))<>0 and (find(/host/item,,"regexp","b"))<>0) or ((find(/host/item,,"regexp","c"))<>0 or (find(/host/item,,"regexp","d"))<>0))'
			],
			[
				[
					[
						'value' => 'find(/host/item,,"regexp","a") and find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/host/item,,"regexp","c") or find(/host/item,,"regexp","d")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))=0 and (find(/host/item,,"regexp","b"))=0) and ((find(/host/item,,"regexp","c"))=0 or (find(/host/item,,"regexp","d"))=0)'
			],

			[
				[
					[
						'value' => 'find(/host/item,,"iregexp","test")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((find(/host/item,,"iregexp","test"))<>0)'
			],
			[
				[
					[
						'value' => 'find(/host/item,,"iregexp","test")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"iregexp","test"))=0)'
			],
			[
				[
					[
						'value' => '(find(/host/item,,"regexp","a"))>0',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))=0)'
			],

			// "not" cases
			[
				[
					[
						'value' => 'not find(/host/item,,"regexp","test")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((not find(/host/item,,"regexp","test"))=0)'
			],
			[
				[
					[
						'value' => 'not (find(/host/item,,"regexp","test"))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((not find(/host/item,,"regexp","test"))=0)'
			],
			[
				[
					[
						'value' => 'not find(/host/item,,"regexp","a") and not find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((not find(/host/item,,"regexp","a"))<>0 and (not find(/host/item,,"regexp","b"))<>0)'
			],
			[
				[
					[
						'value' => 'not find(/host/item,,"regexp","a") or not find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((not find(/host/item,,"regexp","a"))<>0 or (not find(/host/item,,"regexp","b"))<>0)'
			],
			[
				[
					[
						'value' => 'not find(/host/item,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
					[
						'value' => 'not find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'(((not find(/host/item,,"regexp","a"))<>0) or ((not find(/host/item,,"regexp","b"))<>0))'
			],

			// "-" cases
			[
				[
					[
						'value' => '- find(/host/item,,"regexp","test")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((-find(/host/item,,"regexp","test"))=0)'
			],
			[
				[
					[
						'value' => '- (find(/host/item,,"regexp","test"))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((-find(/host/item,,"regexp","test"))=0)'
			],
			[
				[
					[
						'value' => '- find(/host/item,,"regexp","a") and - find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((-find(/host/item,,"regexp","a"))<>0 and (-find(/host/item,,"regexp","b"))<>0)'
			],
			[
				[
					[
						'value' => '- find(/host/item,,"regexp","a") or - find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((-find(/host/item,,"regexp","a"))<>0 or (-find(/host/item,,"regexp","b"))<>0)'
			],
			[
				[
					[
						'value' => '- find(/host/item,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
					[
						'value' => '- find(/host/item,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'(((-find(/host/item,,"regexp","a"))<>0) or ((-find(/host/item,,"regexp","b"))<>0))'
			]
		];
	}

	/**
	 * Test calling getExpressionFromParts() with valid parameters.
	 *
	 * @dataProvider dataProviderGetExpressionFromPartsValid
	 *
	 * @param array  $expressions
	 * @param string $expected_expressions
	 */
	public function testGetExpressionFromPartsValid(array $expressions, string $expected_expressions) {
		$expression = $this->constructor->getExpressionFromParts('', '', $expressions);

		$this->assertSame($expected_expressions, $expression);
	}

	public function dataProviderGetPartsFromExpression() {
		return [
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))<>0',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				]
			],
			[
				'((find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0 and (find(/Zabbix server/system.hostname,,"regexp","b"))=0',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0 or (find(/Zabbix server/system.hostname,,"regexp","b"))=0',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'((find(/Zabbix server/system.hostname,,"regexp","a"))=0 or (find(/Zabbix server/system.hostname,,"regexp","b"))=0) and (find(/Zabbix server/system.hostname,,"regexp","c"))=0',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a") or find(/Zabbix server/system.hostname,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","c")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0 or ((find(/Zabbix server/system.hostname,,"regexp","b"))=0 and (find(/Zabbix server/system.hostname,,"regexp","c"))=0)',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","b") and find(/Zabbix server/system.hostname,,"regexp","c")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0 or (find(/Zabbix server/system.hostname,,"regexp","b"))=0 and (find(/Zabbix server/system.hostname,,"regexp","c"))=0',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","c")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'((find(/Zabbix server/system.hostname,,"regexp","a"))=0 and (find(/Zabbix server/system.hostname,,"regexp","b"))=0) or ((find(/Zabbix server/system.hostname,,"regexp","c"))=0 or (find(/Zabbix server/system.hostname,,"regexp","d"))=0)',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a") and find(/Zabbix server/system.hostname,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","c") or find(/Zabbix server/system.hostname,,"regexp","d")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(((find(/Zabbix server/system.hostname,,"regexp","a"))=0) or ((find(/Zabbix server/system.hostname,,"regexp","b"))=0)) and ((find(/Zabbix server/system.hostname,,"regexp","c"))=0)',
				[
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","b")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'find(/Zabbix server/system.hostname,,"regexp","c")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],

			// "not" cases
			[
				'(not find(/Zabbix server/system.hostname,,"regexp","a"))=0',
				[
					[
						'value' => 'not find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(not (find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => 'not find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'not ((find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => 'not (find(/Zabbix server/system.hostname,,"regexp","a"))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],

			// "-" cases
			[
				'(-find(/Zabbix server/system.hostname,,"regexp","a"))=0',
				[
					[
						'value' => '-find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(-(find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => '-find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'-((find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => '-(find(/Zabbix server/system.hostname,,"regexp","a"))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(- find(/Zabbix server/system.hostname,,"regexp","a"))=0',
				[
					[
						'value' => '-find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(- (find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => '-find(/Zabbix server/system.hostname,,"regexp","a")',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'- ((find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => '-(find(/Zabbix server/system.hostname,,"regexp","a"))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderGetPartsFromExpression
	 *
	 * @param string $expression
	 * @param array  $expected_parts
	 */
	public function testGetPartsFromExpression(string $expression, array $expected_parts) {
		$parts = $this->constructor->getPartsFromExpression($expression);

		$this->assertIsArray($parts);
		unset($parts[0]['details']);

		$this->assertSame($expected_parts, $parts);
	}
}
