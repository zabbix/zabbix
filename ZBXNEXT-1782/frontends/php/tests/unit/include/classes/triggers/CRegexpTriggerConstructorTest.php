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


class CRegexpTriggerConstructorTest extends PHPUnit_Framework_TestCase {

	public function testGetExpressionFromPartsValidProvider() {
		return array(
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'regexp(test)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_MATCH
					)
				),
				'(({host:item.regexp(test)})<>0)'
			),
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'regexp(test)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					)
				),
				'(({host:item.regexp(test)})=0)'
			),
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'regexp(a) and regexp(b)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_MATCH
					)
				),
				'(({host:item.regexp(a)})<>0 and ({host:item.regexp(b)})<>0)'
			),
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'regexp(a) or regexp(b)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_MATCH
					)
				),
				'(({host:item.regexp(a)})<>0 or ({host:item.regexp(b)})<>0)'
			),
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'regexp(a)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_MATCH
					),
					array(
						'value' => 'regexp(b)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_MATCH
					),
				),
				'((({host:item.regexp(a)})<>0) or (({host:item.regexp(b)})<>0))'
			),
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'regexp(a)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					),
					array(
						'value' => 'regexp(b)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					),
				),
				'(({host:item.regexp(a)})=0) and (({host:item.regexp(b)})=0)'
			),
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'regexp(a) and regexp(b)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_MATCH
					),
					array(
						'value' => 'regexp(с) or regexp(d)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_MATCH
					),
				),
				'((({host:item.regexp(a)})<>0 and ({host:item.regexp(b)})<>0) or (({host:item.regexp(с)})<>0 or ({host:item.regexp(d)})<>0))'
			),
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'regexp(a) and regexp(b)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					),
					array(
						'value' => 'regexp(c) or regexp(d)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					),
				),
				'(({host:item.regexp(a)})=0 and ({host:item.regexp(b)})=0) and (({host:item.regexp(c)})=0 or ({host:item.regexp(d)})=0)'
			),

			array(
				'host',
				'item',
				array(
					array(
						'value' => 'iregexp(test)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_MATCH
					)
				),
				'(({host:item.iregexp(test)})<>0)'
			),
			array(
				'host',
				'item',
				array(
					array(
						'value' => 'iregexp(test)',
						'type' => CRegexpTriggerConstructor::EXPRESSION_TYPE_NO_MATCH
					)
				),
				'(({host:item.iregexp(test)})=0)'
			),
		);
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
		$constructor = new CRegexpTriggerConstructor();
		$expression = $constructor->getExpressionFromParts($host, $item, $expressions);

		$this->assertEquals($expectedExpressions, $expression);
	}

	/**
	 * Test calling getExpressionFromParts() with invalid parameters.
	 */
	public function testGetExpressionFromPartsInvalid() {
		$this->markTestIncomplete();
	}
}
