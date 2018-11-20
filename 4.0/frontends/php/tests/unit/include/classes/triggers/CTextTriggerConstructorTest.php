<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CTextTriggerConstructorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CTextTriggerConstructor
	 */
	protected $constructor;

	public function setUp() {
		$this->constructor = new CTextTriggerConstructor(new CTriggerExpression());
	}

	public function testGetExpressionFromPartsValidProvider() {
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
				'(({host:item.regexp(test)})<>0)'
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
				'(({host:item.regexp(test)})=0)'
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
				'(({host:item.regexp(a)})<>0 and ({host:item.regexp(b)})<>0)'
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
				'(({host:item.regexp(a)})<>0 or ({host:item.regexp(b)})<>0)'
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
					],
				],
				'((({host:item.regexp(a)})<>0) or (({host:item.regexp(b)})<>0))'
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
					],
				],
				'(({host:item.regexp(a)})=0) and (({host:item.regexp(b)})=0)'
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
						'value' => 'regexp(с) or regexp(d)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					],
				],
				'((({host:item.regexp(a)})<>0 and ({host:item.regexp(b)})<>0) or (({host:item.regexp(с)})<>0 or ({host:item.regexp(d)})<>0))'
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
					],
				],
				'(({host:item.regexp(a)})=0 and ({host:item.regexp(b)})=0) and (({host:item.regexp(c)})=0 or ({host:item.regexp(d)})=0)'
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
				'(({host:item.iregexp(test)})<>0)'
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
				'(({host:item.iregexp(test)})=0)'
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
				'(({host:item.regexp(a)})=0)'
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
				'((not {host:item.regexp(test)})=0)'
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
				'((not {host:item.regexp(test)})=0)'
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
				'((not {host:item.regexp(a)})<>0 and (not {host:item.regexp(b)})<>0)'
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
				'((not {host:item.regexp(a)})<>0 or (not {host:item.regexp(b)})<>0)'
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
					],
				],
				'(((not {host:item.regexp(a)})<>0) or ((not {host:item.regexp(b)})<>0))'
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
				'((-{host:item.regexp(test)})=0)'
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
				'((-{host:item.regexp(test)})=0)'
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
				'((-{host:item.regexp(a)})<>0 and (-{host:item.regexp(b)})<>0)'
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
				'((-{host:item.regexp(a)})<>0 or (-{host:item.regexp(b)})<>0)'
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
					],
				],
				'(((-{host:item.regexp(a)})<>0) or ((-{host:item.regexp(b)})<>0))'
			],
		];
	}

	/**
	 * Test calling getExpressionFromParts() with valid parameters.
	 *
	 * @dataProvider testGetExpressionFromPartsValidProvider
	 *
	 * @param $host
	 * @param $item
	 * @param array $expressions
	 * @param $expectedExpressions
	 */
	public function testGetExpressionFromPartsValid($host, $item, array $expressions, $expectedExpressions) {
		$expression = $this->constructor->getExpressionFromParts($host, $item, $expressions);

		$this->assertEquals($expectedExpressions, $expression);
	}

	/**
	 * Test calling getExpressionFromParts() with invalid parameters.
	 */
	public function testGetExpressionFromPartsInvalid() {
		$this->markTestIncomplete();
	}

	public function testGetPartsFromExpressionProvider() {
		return [
			[
				'({Zabbix server:system.hostname.regexp(a)})=0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'({Zabbix server:system.hostname.regexp(a)})<>0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_MATCH
					]
				]
			],
			[
				'(({Zabbix server:system.hostname.regexp(a)})=0)',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'({Zabbix server:system.hostname.regexp(a)})=0 and ({Zabbix server:system.hostname.regexp(b)})=0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
				]
			],
			[
				'({Zabbix server:system.hostname.regexp(a)})=0 or ({Zabbix server:system.hostname.regexp(b)})=0',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
				]
			],
			[
				'(({Zabbix server:system.hostname.regexp(a)})=0 or ({Zabbix server:system.hostname.regexp(b)})=0) and ({Zabbix server:system.hostname.regexp(c)})=0',
				[
					[
						'value' => 'regexp(a) or regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(c)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
				]
			],
			[
				'({Zabbix server:system.hostname.regexp(a)})=0 or (({Zabbix server:system.hostname.regexp(b)})=0 and ({Zabbix server:system.hostname.regexp(c)})=0)',
				[
					[
						'value' => 'regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(b) and regexp(c)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
				]
			],
			[
				'({Zabbix server:system.hostname.regexp(a)})=0 or ({Zabbix server:system.hostname.regexp(b)})=0 and ({Zabbix server:system.hostname.regexp(c)})=0',
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
					],
				]
			],
			[
				'(({Zabbix server:system.hostname.regexp(a)})=0 and ({Zabbix server:system.hostname.regexp(b)})=0) or (({Zabbix server:system.hostname.regexp(c)})=0 or ({Zabbix server:system.hostname.regexp(d)})=0)',
				[
					[
						'value' => 'regexp(a) and regexp(b)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
					[
						'value' => 'regexp(c) or regexp(d)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					],
				]
			],
			[
				'((({Zabbix server:system.hostname.regexp(a)})=0) or (({Zabbix server:system.hostname.regexp(b)})=0)) and (({Zabbix server:system.hostname.regexp(c)})=0)',
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
					],
				]
			],

			// "not" cases
			[
				'(not {Zabbix server:system.hostname.regexp(a)})=0',
				[
					[
						'value' => 'not regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(not ({Zabbix server:system.hostname.regexp(a)})=0)',
				[
					[
						'value' => 'not regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'not (({Zabbix server:system.hostname.regexp(a)})=0)',
				[
					[
						'value' => 'not (regexp(a))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],

			// "-" cases
			[
				'(-{Zabbix server:system.hostname.regexp(a)})=0',
				[
					[
						'value' => '-regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(-({Zabbix server:system.hostname.regexp(a)})=0)',
				[
					[
						'value' => '-regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'-(({Zabbix server:system.hostname.regexp(a)})=0)',
				[
					[
						'value' => '-(regexp(a))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(- {Zabbix server:system.hostname.regexp(a)})=0',
				[
					[
						'value' => '-regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'(- ({Zabbix server:system.hostname.regexp(a)})=0)',
				[
					[
						'value' => '-regexp(a)',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
			[
				'- (({Zabbix server:system.hostname.regexp(a)})=0)',
				[
					[
						'value' => '-(regexp(a))',
						'type' => CTextTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					]
				]
			],
		];
	}

	/**
	 * @dataProvider testGetPartsFromExpressionProvider
	 *
	 * @param $expression
	 * @param array $expectedParts
	 */
	public function testGetPartsFromExpression($expression, array $expectedParts) {
		$parts = $this->constructor->getPartsFromExpression($expression);

		$this->assertEquals($expectedParts, $parts);
	}
}
