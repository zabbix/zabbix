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

class CTextTriggerConstructorTest extends TestCase {

	/**
	 * @var CTextTriggerConstructor
	 */
	protected $constructor;

	protected function setUp(): void {
		$this->constructor = new CTextTriggerConstructor(new CExpressionParser());
	}

	public function dataProviderGetExpressionFromPartsValid() {
		return [
			[
				'host',
				'item',
				[
					[
						'value' => 'regexp(test)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((find(/host/item,,"regexp","test"))<>0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'regexp(test)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"regexp","test"))=0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'regexp(a) and regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))<>0 and (find(/host/item,,"regexp","b"))<>0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'regexp(a) or regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))<>0 or (find(/host/item,,"regexp","b"))<>0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
					[
						'value' => 'regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'(((find(/host/item,,"regexp","a"))<>0) or ((find(/host/item,,"regexp","b"))<>0))'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))=0) and ((find(/host/item,,"regexp","b"))=0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'regexp(a) and regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
					[
						'value' => 'regexp(c) or regexp(d)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'(((find(/host/item,,"regexp","a"))<>0 and (find(/host/item,,"regexp","b"))<>0) or ((find(/host/item,,"regexp","c"))<>0 or (find(/host/item,,"regexp","d"))<>0))'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'regexp(a) and regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(c) or regexp(d)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))=0 and (find(/host/item,,"regexp","b"))=0) and ((find(/host/item,,"regexp","c"))=0 or (find(/host/item,,"regexp","d"))=0)'
			],

			[
				'host',
				'item',
				[
					[
						'value' => 'iregexp(test)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((find(/host/item,,"iregexp","test"))<>0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'iregexp(test)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"iregexp","test"))=0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => '(regexp(a))>0',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((find(/host/item,,"regexp","a"))=0)'
			],

			// "not" cases
			[
				'host',
				'item',
				[
					[
						'value' => 'not regexp(test)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((not find(/host/item,,"regexp","test"))=0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'not (regexp(test))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((not find(/host/item,,"regexp","test"))=0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'not regexp(a) and not regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((not find(/host/item,,"regexp","a"))<>0 and (not find(/host/item,,"regexp","b"))<>0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'not regexp(a) or not regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((not find(/host/item,,"regexp","a"))<>0 or (not find(/host/item,,"regexp","b"))<>0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => 'not regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
					[
						'value' => 'not regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'(((not find(/host/item,,"regexp","a"))<>0) or ((not find(/host/item,,"regexp","b"))<>0))'
			],

			// "-" cases
			[
				'host',
				'item',
				[
					[
						'value' => '- regexp(test)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((-find(/host/item,,"regexp","test"))=0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => '- (regexp(test))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				],
				'((-find(/host/item,,"regexp","test"))=0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => '- regexp(a) and - regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((-find(/host/item,,"regexp","a"))<>0 and (-find(/host/item,,"regexp","b"))<>0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => '- regexp(a) or - regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				],
				'((-find(/host/item,,"regexp","a"))<>0 or (-find(/host/item,,"regexp","b"))<>0)'
			],
			[
				'host',
				'item',
				[
					[
						'value' => '- regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
					[
						'value' => '- regexp(b)',
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
	 * @param string $host
	 * @param string $item_key
	 * @param array  $expressions
	 * @param string $expected
	 */
	public function testGetExpressionFromPartsValid(string $host, string $item_key, array $expressions,
			string $expected) {
		$this->assertSame($expected, $this->constructor->getExpressionFromParts($host, $item_key, $expressions));
	}

	public function dataProviderGetPartsFromExpression() {
		return [
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))<>0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				]
			],
			[
				'((find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0 and (find(/Zabbix server/system.hostname,,"regexp","b"))=0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0 or (find(/Zabbix server/system.hostname,,"regexp","b"))=0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'((find(/Zabbix server/system.hostname,,"regexp","a"))=0 or (find(/Zabbix server/system.hostname,,"regexp","b"))=0) and (find(/Zabbix server/system.hostname,,"regexp","c"))=0',
				[
					[
						'value' => 'regexp(a) or regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(c)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0 or ((find(/Zabbix server/system.hostname,,"regexp","b"))=0 and (find(/Zabbix server/system.hostname,,"regexp","c"))=0)',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b) and regexp(c)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(find(/Zabbix server/system.hostname,,"regexp","a"))=0 or (find(/Zabbix server/system.hostname,,"regexp","b"))=0 and (find(/Zabbix server/system.hostname,,"regexp","c"))=0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(c)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'((find(/Zabbix server/system.hostname,,"regexp","a"))=0 and (find(/Zabbix server/system.hostname,,"regexp","b"))=0) or ((find(/Zabbix server/system.hostname,,"regexp","c"))=0 or (find(/Zabbix server/system.hostname,,"regexp","d"))=0)',
				[
					[
						'value' => 'regexp(a) and regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(c) or regexp(d)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(((find(/Zabbix server/system.hostname,,"regexp","a"))=0) or ((find(/Zabbix server/system.hostname,,"regexp","b"))=0)) and ((find(/Zabbix server/system.hostname,,"regexp","c"))=0)',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(c)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],

			// "not" cases
			[
				'(not find(/Zabbix server/system.hostname,,"regexp","a"))=0',
				[
					[
						'value' => 'not regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(not (find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => 'not regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'not ((find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => 'not (regexp(a))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],

			// "-" cases
			[
				'(-find(/Zabbix server/system.hostname,,"regexp","a"))=0',
				[
					[
						'value' => '-regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(-(find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => '-regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'-((find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => '-(regexp(a))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(- find(/Zabbix server/system.hostname,,"regexp","a"))=0',
				[
					[
						'value' => '-regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(- (find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => '-regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'- ((find(/Zabbix server/system.hostname,,"regexp","a"))=0)',
				[
					[
						'value' => '-(regexp(a))',
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
		$this->assertSame($expected_parts, $this->constructor->getPartsFromExpression($expression));
	}
}
