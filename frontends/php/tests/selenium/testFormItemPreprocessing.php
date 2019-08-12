<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';
require_once dirname(__FILE__).'/traits/PreprocessingTrait.php';

/**
 * @backup items
 */
class testFormItemPreprocessing extends CWebTest {
	const HOST_ID = 40001;		//'Simple form test host'

	use PreprocessingTrait;

	public static function getCreateAllStepsData() {
		return [
			// Custom multiplier.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item empty multiplier',
						'Key' => 'item-empty-multiplier'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item string multiplier',
						'Key' => 'item-string-multiplier'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => 'abc']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item multiplier comma',
						'Key' => 'item-comma-multiplier'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '0,0']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item multiplier symbol',
						'Key' => 'item-symbol-multiplier'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '1a!@#$%^&*()-=']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			// Empty trim.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item right trim',
						'Key' => 'item-empty-right-trim'
					],
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item left trim',
						'Key' => 'item-empty-left-trim'
					],
					'preprocessing' => [
						['type' => 'Left trim', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item trim',
						'Key' => 'item-empty-trim'
					],
					'preprocessing' => [
						['type' => 'Trim', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Structured data.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item XML XPath',
						'Key' => 'item-empty-xpath'
					],
					'preprocessing' => [
						['type' => 'XML XPath', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item JSONPath',
						'Key' => 'item-empty-jsonpath'
					],
					'preprocessing' => [
						['type' => 'JSONPath', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Regular expression.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item empty regular expression',
						'Key' => 'item-empty-both-parameters'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item empty regular expression',
						'Key' => 'item-empty-first-parameter'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => 'test output']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item empty regular expression',
						'Key' => 'item-empty-second-parameter'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": second parameter is expected.'
				]
			],
			// Change.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item two delta',
						'Key' => 'item-two-delta'
					],
					'preprocessing' => [
						['type' => 'Simple change'],
						['type' => 'Simple change']
					],
					'error' => 'Only one change step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item two delta per second',
						'Key' => 'item-two-delta-per-second'
					],
					'preprocessing' => [
						['type' => 'Change per second'],
						['type' => 'Change per second']
					],
					'error' => 'Only one change step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item two different delta',
						'Key' => 'item-two-different-delta'
					],
					'preprocessing' => [
						['type' => 'Simple change'],
						['type' => 'Change per second']
					],
					'error' => 'Only one change step is allowed.'
				]
			],
			// Custom scripts. JavaScript.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item empty JavaScript',
						'Key' => 'item-empty-javascript'
					],
					'preprocessing' => [
						['type' => 'JavaScript']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Validation. In range.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range empty',
						'Key' => 'in-range-empty'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range letters string',
						'Key' => 'in-range-letters-string'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => 'abc', 'parameter_2' => 'def']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range symbols',
						'Key' => 'in-range-symbols'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '1a!@#$%^&*()-=', 'parameter_2' => '2b!@#$%^&*()-=']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range comma',
						'Key' => 'in-range-comma'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '1,5', 'parameter_2' => '-3,5']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range wrong interval',
						'Key' => 'in-range-wrong-interval'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '8', 'parameter_2' => '-8']
					],
					'error' => 'Incorrect value for field "params": "min" value must be less than or equal to "max" value.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'In range negative float',
						'Key' => 'in-range-negative-float'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '-3.5', 'parameter_2' => '-1.5']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'In range zero',
						'Key' => 'in-range-zero'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '0', 'parameter_2' => '0']
					]
				]
			],
			// Validation. Regular expressions matching.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Matches regular expression empty',
						'Key' => 'matches-regular-expression-empty'
					],
					'preprocessing' => [
						['type' => 'Matches regular expression', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Does not match regular expression empty',
						'Key' => 'does-not-match-regular-expression-empty'
					],
					'preprocessing' => [
						['type' => 'Does not match regular expression', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Validation. Error in JSON and XML.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item error JSON empty',
						'Key' => 'item-error-json-empty'
					],
					'preprocessing' => [
						['type' => 'Check for error in JSON', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item error XML empty',
						'Key' => 'item-error-xml-empty'
					],
					'preprocessing' => [
						['type' => 'Check for error in XML', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Validation. Check error using REGEXP.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item error REGEXP both params empty',
						'Key' => 'item-error-regexp-both-empty'
					],
					'preprocessing' => [
						['type' => 'Check for error using regular expression', 'parameter_1' => '', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item error REGEXP first parameter empty',
						'Key' => 'item-error-regexp-first-empty'
					],
					'preprocessing' => [
						['type' => 'Check for error using regular expression', 'parameter_1' => '', 'parameter_2' => 'test']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item error REGEXP second parameter empty',
						'Key' => 'item-error-regexp-second-empty'
					],
					'preprocessing' => [
						['type' => 'Check for error using regular expression', 'parameter_1' => 'test', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": second parameter is expected.'
				]
			],
			// Validation. Throttling.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item two discard uncahnged',
						'Key' => 'item-two-discard-uncahnged'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged']
					],
					'error' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item two different throttlings',
						'Key' => 'item-two-different-throttlings'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'error' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item two equal discard unchanged with heartbeat',
						'Key' => 'item-two-equal-discard-uncahnged-with-heartbeat'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'error' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item two different discard unchanged with heartbeat',
						'Key' => 'item-two-different-discard-uncahnged-with-heartbeat'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '2']
					],
					'error' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat empty',
						'Key' => 'discard-uncahnged-with-heartbeat-empty'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '']
					],
					'error' => 'Invalid parameter "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat symbols',
						'Key' => 'discard-uncahnged-with-heartbeat-symbols'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '3g!@#$%^&*()-=']
					],
					'error' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat sletters string',
						'Key' => 'discard-uncahnged-with-heartbeat-letters-string'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => 'abc']
					],
					'error' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat comma',
						'Key' => 'discard-uncahnged-with-heartbeat-comma'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1,5']
					],
					'error' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat dot',
						'Key' => 'discard-uncahnged-with-heartbeat-dot'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1.5']
					],
					'error' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat negative',
						'Key' => 'discard-uncahnged-with-heartbeat-negative'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '-3']
					],
					'error' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat zero',
						'Key' => 'discard-uncahnged-with-heartbeat-zero'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '0']
					],
					'error' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat maximum',
						'Key' => 'discard-uncahnged-with-heartbeat-max'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '788400001']
					],
					'error' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Add JavaScript multiline preprocessing',
						'Key' => 'item.javascript.multiline.preprocessing'
					],
					'preprocessing' => [
						['type' => 'JavaScript', 'parameter_1' => "  Test line 1\nTest line 2\nTest line 3  "]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Add all preprocessing',
						'Key' => 'item.all.preprocessing'
					],
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Simple change'],
						['type' => 'In range', 'parameter_1' => '-5', 'parameter_2' => '9.5'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '5'],
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => 'label_name' ]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Add symblos preprocessing',
						'Key' => 'item.symbols.preprocessing'
					],
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => '1a!@#$%^&*()-='],
						['type' => 'Left trim', 'parameter_1' => '2b!@#$%^&*()-='],
						['type' => 'Trim', 'parameter_1' => '3c!@#$%^&*()-='],
						['type' => 'XML XPath', 'parameter_1' => '3c!@#$%^&*()-='],
						['type' => 'JSONPath', 'parameter_1' => '3c!@#$%^&*()-='],
						['type' => 'Custom multiplier', 'parameter_1' => '4e+10'],
						['type' => 'Regular expression', 'parameter_1' => '5d!@#$%^&*()-=', 'parameter_2' => '6e!@#$%^&*()-='],
						['type' => 'JavaScript', 'parameter_1' => '5d!@#$%^&*()-='],
						['type' => 'Matches regular expression', 'parameter_1' => '7f!@#$%^&*()-='],
						['type' => 'Does not match regular expression', 'parameter_1' => '8g!@#$%^&*()-='],
						['type' => 'Check for error in JSON', 'parameter_1' => '9h!@#$%^&*()-='],
						['type' => 'Check for error in XML', 'parameter_1' => '0i!@#$%^&*()-='],
						['type' => 'Check for error using regular expression', 'parameter_1' => '1j!@#$%^&*()-=', 'parameter_2' => '2k!@#$%^&*()-=']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Add the same preprocessing',
						'Key' => 'item.theSamePpreprocessing'
					],
					'preprocessing' => [
						['type' => 'Change per second'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'XML XPath', 'parameter_1' => '1a2b3c'],
						['type' => 'XML XPath', 'parameter_1' => '1a2b3c'],
						['type' => 'JSONPath', 'parameter_1' => '1a2b3c'],
						['type' => 'JSONPath', 'parameter_1' => '1a2b3c'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Boolean to decimal'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'In range', 'parameter_1' => '-5.5', 'parameter_2' => '10'],
						['type' => 'In range', 'parameter_1' => '-5.5', 'parameter_2' => '10'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test_expression'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test_expression'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'not_expression'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'not_expression'],
						['type' => 'Check for error in JSON', 'parameter_1' => '/path'],
						['type' => 'Check for error in JSON', 'parameter_1' => '/path'],
						['type' => 'Check for error in XML', 'parameter_1' => '/path/xml'],
						['type' => 'Check for error in XML', 'parameter_1' => '/path/xml'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'regexp', 'parameter_2' => '\1'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'regexp', 'parameter_2' => '\1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item with preprocessing rule with user macro',
						'Key' => 'item-user-macro'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$DELIM}(.*)', 'parameter_2' => '\1'],
						['type' => 'Trim', 'parameter_1' => '{$DELIM}'],
						['type' => 'Right trim', 'parameter_1' => '{$MACRO}'],
						['type' => 'Left trim', 'parameter_1' => '{$USER}'],
						['type' => 'XML XPath', 'parameter_1' => 'number(/values/Item/value[../key=\'{$DELIM}\'])'],
						['type' => 'JSONPath', 'parameter_1' => '$.data[\'{$KEY}\']'],
						['type' => 'Custom multiplier', 'parameter_1' => '{$VALUE}'],
						['type' => 'In range', 'parameter_1' => '{$FROM}', 'parameter_2' => '{$TO}'],
						['type' => 'Matches regular expression', 'parameter_1' => '{$EXPRESSION}(.*)'],
						['type' => 'Does not match regular expression', 'parameter_1' => '{$REGEXP}(.+)'],
						['type' => 'JavaScript', 'parameter_1' => '{$JAVASCRIPT}'],
						['type' => 'Check for error in JSON', 'parameter_1' => '{$USERMACRO}'],
						['type' => 'Check for error in XML', 'parameter_1' => '/tmp/{$PATH}'],
						['type' => 'Check for error using regular expression', 'parameter_1' => '^{$REGEXP}(.+)', 'parameter_2' => '\0'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '{$SECONDS}'],
						['type' => 'Prometheus to JSON', 'parameter_1' => '{$PATTERN}']
					]
				]
			]
		];
	}
	/**
	 * @dataProvider getCreateAllStepsData
	 */
	public function testFormItemPreprocessing_CreateAllSteps($data) {
		$this->executeCreate($data);
	}

	public static function getCreatePrometheusData() {
		return [
			// Prometheus pattern validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus empty first parameter',
						'Key' => 'prometeus-empty-first-parameter'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Prometheus space in pattern',
						'Key' => 'prometheus-space-in-pattern'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu usage_metric'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Prometheus only digits in pattern',
						'Key' => 'prometheus-digits-in-pattern'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '1223'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter starts with digits',
						'Key' => 'prometeus-digits-first-parameter'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '1name_of_metric']
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong equals operator',
						'Key' => 'rometeus-wrong-equals-operator'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}=1']
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator >',
						'Key' => 'prometeus-unsupported-operator-1'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}>1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator <',
						'Key' => 'prometeus-unsupported-operator-2'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}<1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator !==',
						'Key' => 'prometeus-unsupported-operator-3'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}!==1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator >=',
						'Key' => 'prometeus-unsupported-operator-4'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}>=1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator =<',
						'Key' => 'prometeus-unsupported-operator-5'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}=<1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported label operator !=',
						'Key' => 'prometeus-unsupported-label-operator-1'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name!="regex_pattern"}'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported label operator !~',
						'Key' => 'prometeus-unsupported-label-operator-2'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name!~"<regex>"}'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus duplicate metric condition',
						'Key' => 'duplicate-metric-condition'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_system{__name__="metric_name"}'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item duplicate Prometeus steps',
						'Key' => 'duplicate-prometheus-steps'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system'],
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system']
					],
					'error' => 'Only one Prometheus step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - space',
						'Key' => 'wrong-second-parameter-space'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern',  'parameter_1' => 'cpu_usage_system', 'parameter_2' => 'label name']
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus output.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - quotes',
						'Key' => 'wrong-second-parameter-quotes'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern',  'parameter_1' => 'cpu_usage_system', 'parameter_2' => '"label_name"']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus output.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - triangle quotes',
						'Key' => 'wrong-second-parameter-triangle-quotes'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern',  'parameter_1' => 'cpu_usage_system', 'parameter_2' => '<label_name>']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus output.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - slash',
						'Key' => 'wrong-second-parameter-slash'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern',  'parameter_1' => 'cpu_usage_system', 'parameter_2' => '\0']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus output.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - digits',
						'Key' => 'wrong-second-parameter-digits'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern',  'parameter_1' => 'cpu_usage_system', 'parameter_2' => '123']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus output.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong first parameter - pipe',
						'Key' => 'wrong-second-parameter-pipe'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'metric==1e|5']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong first parameter - slash',
						'Key' => 'wrong-second-parameter-slash'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label="value\"}']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong first parameter - LLD macro',
						'Key' => 'wrong-first-parameter-macro'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{#METRICNAME}==1']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - LLD macro',
						'Key' => 'wrong-second-parameter-macro'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => '{#LABELNAME}']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus output.'
				]
			],
			// Prometheus to JSON validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter starts with digits',
						'Key' => 'json-prometeus-digits-first-parameter'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '1name_of_metric']
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON wrong equals operator',
						'Key' => 'json-prometeus-wrong-equals-operator'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}=1']
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator >',
						'Key' => 'json-prometeus-unsupported-operator-1'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}>1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator <',
						'Key' => 'json-prometeus-unsupported-operator-2'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}<1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator !==',
						'Key' => 'json-prometeus-unsupported-operator-3'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}!==1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator >=',
						'Key' => 'json-prometeus-unsupported-operator-4'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}>=1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator =<',
						'Key' => 'json-prometeus-unsupported-operator-5'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}=<1'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported label operator !=',
						'Key' => 'json-prometeus-unsupported-label-operator-1'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name!="regex_pattern"}'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported label operator !~',
						'Key' => 'json-prometeus-unsupported-label-operator-2'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name!~"<regex>"}'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON duplicate metric condition',
						'Key' => 'json-duplicate-metric-condition'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_system{__name__="metric_name"}'],
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON wrong parameter - space',
						'Key' => 'json-wrong-parameter-space'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON',  'parameter_1' => 'cpu usage_system']
					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON wrong parameter - slash',
						'Key' => 'json-wrong-parameter-slash'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON',  'parameter_1' => 'cpu\\']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometheus to JSON wrong parameter - digits',
						'Key' => 'json-wrong-parameter-digits'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON',  'parameter_1' => '123']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometheus to JSON wrong first parameter - pipe',
						'Key' => 'json-wrong-parameter-pipe'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'metric==1e|5']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometheus to JSON wrong first parameter - slash',
						'Key' => 'json-wrong-parameter-slash'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label="value\"}']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometheus to JSON wrong first parameter - LLD macro',
						'Key' => 'json-wrong-first-parameter-macro'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{#METRICNAME}==1']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item duplicate Prometeus to JSON steps',
						'Key' => 'duplicate-prometheus-to-json-steps'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system_1'],
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system_1']
					],
					'error' => 'Only one Prometheus step is allowed.'
				]
			],
			// Successful Prometheus pattern creation.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus empty second parameter',
						'Key' => 'prometeus-empty-second-parameter'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => '']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus both parameters present',
						'Key' => 'prometeus-both-parameters-present'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => 'label_name']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter +inf',
						'Key' => 'prometeus-plus-inf'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system==+inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter inf',
						'Key' => 'prometeus-inf'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter -inf',
						'Key' => 'prometeus-negative-inf'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}==-inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter nan',
						'Key' => 'prometeus-nan'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==nan']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter exp',
						'Key' => 'prometeus-exp'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system==3.5180e+11']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter ==1',
						'Key' => 'prometeus-neutral-digit'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameters ==+1',
						'Key' => 'prometeus-positive-digit'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==+1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameters ==-1',
						'Key' => 'prometeus-negative-digit'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==-1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus label operator =',
						'Key' => 'prometeus-label-operator-equal-strong'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name="name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus label operator =~',
						'Key' => 'prometeus-label-operator-contains'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name=~"name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Trailing spaces',
						'Key' => 'prometeus-space-in-parameters'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '  metric  ', 'parameter_2' => '  output  ']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus slashes in pattern',
						'Key' => 'prometeus-slashes-pattern'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label="value\\\\"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus user macros in parameters',
						'Key' => 'prometeus-macros-1'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{$METRIC_NAME}==1', 'parameter_2' => '{$LABEL_NAME}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus user macros in parameters',
						'Key' => 'prometeus-macros-2'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="{$METRIC_NAME}"}', 'parameter_2' => '']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus user macros in parameters',
						'Key' => 'prometeus-macros-3'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{{$LABEL_NAME}="<label value>"}', 'parameter_2' => '']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus user macros in parameters',
						'Key' => 'prometeus-macros-4'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name="{$LABEL_VALUE}"}', 'parameter_2' => '']
					]
				]
			],
			// Successful Prometheus to JSON creation.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON empty first parameter',
						'Key' => 'json-prometeus-empty-first-parameter'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter +inf',
						'Key' => 'json-prometeus-plus-inf'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system==+inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter inf',
						'Key' => 'json-prometeus-inf'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter -inf',
						'Key' => 'json-prometeus-negative-inf'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}==-inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter nan',
						'Key' => 'json-prometeus-nan'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==nan']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter exp',
						'Key' => 'json-prometeus-exp'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system==3.5180e+11']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter ==1',
						'Key' => 'json-prometeus-neutral-digit'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameters ==+1',
						'Key' => 'json-prometeus-positive-digit'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==+1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameters ==-1',
						'Key' => 'json-prometeus-negative-digit'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==-1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON label operator =',
						'Key' => 'json-prometeus-label-operator-equal-strong'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name="name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON label operator =~',
						'Key' => 'json-prometeus-label-operator-contains'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name=~"name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Trailing spaces',
						'Key' => 'json-prometeus-space-in-parameter'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '  metric  ']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON slashes in pattern',
						'Key' => 'json-prometeus-slashes-pattern'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label="value\\\\"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON user macros in parameter',
						'Key' => 'json-prometeus-macros-1'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{$METRIC_NAME}==1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON user macros in parameter',
						'Key' => 'json-prometeus-macros-2'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="{$METRIC_NAME}"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON user macros in parameters',
						'Key' => 'json-prometeus-macros-3'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{{$LABEL_NAME}="<label value>"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON user macros in parameters',
						'Key' => 'json-prometeus-macros-4'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name="{$LABEL_VALUE}"}']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreatePrometheusData
	 */
	public function testFormItemPreprocessing_CreatePrometheus($data) {
		$this->executeCreate($data);
	}

	private function executeCreate($data) {
		if ($data['expected'] === TEST_BAD) {
			$sql_items = 'SELECT * FROM items ORDER BY itemid';
			$old_hash = CDBHelper::getHash($sql_items);
		}
		$this->page->login()->open('items.php?filter_set=1&filter_hostids[0]='.self::HOST_ID);
		$this->query('button:Create item')->waitUntilPresent()->one()->click();

		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->fill($data['fields']);
		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps($data['preprocessing']);
		$form->submit();

		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals('Item added', $message->getTitle());

				// Check result in frontend form.
				$id = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.zbx_dbstr($data['fields']['Key']));
				$this->page->open('items.php?form=update&hostid='.self::HOST_ID.'&itemid='.$id);
				$form->selectTab('Preprocessing');
				// Check for Trailing spaces case.
				if ($data['fields']['Name'] === 'Trailing spaces'){
					$data['preprocessing'][0]['parameter_1'] = trim($data['preprocessing'][0]['parameter_1']);
					if (array_key_exists('parameter_2', $data['preprocessing'][0])){
						$data['preprocessing'][0]['parameter_2'] = trim($data['preprocessing'][0]['parameter_2']);
					}
				}
				$this->assertPreprocessingSteps($data['preprocessing']);
				break;

				// Check results in DB.
				foreach ($data['preprocessing'] as $key => $options) {
					// The array of allowed types must be synced with CItem::$supported_preprocessing_types.
					$db_type = get_preprocessing_types($type[$key], false, [ZBX_PREPROC_REGSUB, ZBX_PREPROC_TRIM,
						ZBX_PREPROC_RTRIM, ZBX_PREPROC_LTRIM, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
						ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED, ZBX_PREPROC_BOOL2DEC,
						ZBX_PREPROC_OCT2DEC, ZBX_PREPROC_HEX2DEC, ZBX_PREPROC_VALIDATE_RANGE,
						ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON,
						ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_ERROR_FIELD_REGEX, ZBX_PREPROC_THROTTLE_VALUE,
						ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON
					]);
					$this->assertEquals($options['type'], $db_type);

					if (in_array($type, ['Regular expression', 'In range', 'Check for error using regular expression', 'Prometheus pattern'])){
						$params = $options['parameter_1']."\n".$options['parameter_2'];
						$this->assertEquals($params, $db_params[$key]);
					}
					else {
						$this->assertEquals($options['parameter_1'], $db_params[$key]);
					}
				}
				break;

			case TEST_BAD:
				$this->assertTrue($message->isBad());
				$this->assertEquals('Cannot add item', $message->getTitle());
				$this->assertTrue($message->hasLine($data['error']));
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_items));
				break;
		}
	}

	/**
	 * Test copies templated item from one host to another.
	 */
	public function testFormItemPreprocessing_CopyItem() {
		$preprocessing_item_key = 'test-inheritance-item-preprocessing';	// testInheritanceItemPreprocessing.
		$preprocessing_item_name = 'testInheritanceItemPreprocessing';
		$preprocessing_item_id = 15094;
		$original_host_id = 15001;											// Template inheritance test host
		$target_hostname = 'Simple form test host';

		$this->page->login()->open('items.php?filter_set=1&filter_hostids[0]='.$original_host_id);
		$table = $this->query('xpath://form[@name="items"]/table')->asTable()->one();
		$table->findRow('Key', $preprocessing_item_key)->select();
		$this->query('button:Copy')->one()->click();
		$form = $this->query('name:elements_form')->waitUntilPresent()->asForm()->one();

		$form->fill([
			'Target type'	=> 'Hosts',
			'Target' => ['values' => [$target_hostname], 'context' => 'Zabbix servers']
		]);
		$form->submit();

		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Item copied', $message->getTitle());

		// Open original item form and get steps text.
		$this->page->open('items.php?form=update&hostid='.$original_host_id.'&itemid='.$preprocessing_item_id);
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Preprocessing');
		$original_steps = $this->listPreprocessingSteps();
		// Open copied item form, get steps text and compare to original.
		$this->page->open('items.php?filter_set=1&filter_hostids[0]='.self::HOST_ID);
		$this->query('link:'.$preprocessing_item_name)->one()->click();
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$this->assertEquals($preprocessing_item_name, $form->getField('Name')->getValue());
		$this->assertEquals($preprocessing_item_key, $form->getField('Key')->getValue());
		$form->selectTab('Preprocessing');
		$copied_steps = $this->listPreprocessingSteps();
		$this->assertEquals($original_steps, $copied_steps);
		// Get steps inputs and check if they are not disabled.
		foreach (array_keys($copied_steps) as $i) {
			$step = $this->query('id:preprocessing_'.$i.'_type')->one();
			$this->assertNull($step->getAttribute('readonly'));
		}
	}

	public static function getCustomOnFailData() {
		$options = [
			ZBX_PREPROC_FAIL_DISCARD_VALUE	=> 'Discard value',
			ZBX_PREPROC_FAIL_SET_VALUE		=> 'Set value to',
			ZBX_PREPROC_FAIL_SET_ERROR		=> 'Set error to'
		];

		$data = [];
		foreach ($options as $value => $label) {
			$item = [
				'item_fields' => [
					'Name' => 'LLD Preprocessing '.$label,
					'Key' => 'lld-preprocessing-steps-discard-on-fail'.$value
				],
				'preprocessing' => [
					[
						'type' => 'Regular expression',
						'parameter_1' => 'expression',
						'parameter_2' => '\1',
						'on_fail' => true
					],
					[
						'type' => 'JSONPath',
						'parameter_1' => '$.data.test',
						'on_fail' => true
					],
					[
						'type' => 'Does not match regular expression',
						'parameter_1' => 'Pattern',
						'on_fail' => true
					],
					[
						'type' => 'Check for error in JSON',
						'parameter_1' => '$.new.path'
					],
					[
						'type' => 'Discard unchanged with heartbeat',
						'parameter_1' => '30'
					]
				],
				'value' => $value
			];

			foreach ($item['preprocessing'] as &$step) {
				if (!array_key_exists('on_fail', $step) || !$step['on_fail']) {
					continue;
				}

				$step['error_handler'] = $label;

				if ($value !== ZBX_PREPROC_FAIL_DISCARD_VALUE) {
					$step['error_handler_params'] = 'handler parameter';
				}
			}

			$data[] = [$item];
		}

		return $data;
	}

	/**
	 * Check Custom on fail checkbox.
	 *
	 * @dataProvider getCustomOnFailData
	 */
	public function testFormItemPreprocessing_CustomOnFail($data) {
		$this->page->login()->open('items.php?filter_set=1&filter_hostids[0]='.self::HOST_ID);
		$this->query('button:Create item')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		$form->fill($data['item_fields']);

		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps($data['preprocessing']);
		$steps = $this->getPreprocessingSteps();

		foreach ($data['preprocessing'] as $i => $options) {
			if ($options['type'] === 'Check for error in JSON'
					|| $options['type'] === 'Discard unchanged with heartbeat') {

				$this->assertFalse($steps[$i]['on_fail']->isEnabled());
			}
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Check message title and if message is positive.
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Item added', $message->getTitle());

		// Get item data from DB.
		$db_item = CDBHelper::getRow('SELECT name,key_,itemid FROM items where key_='.
				zbx_dbstr($data['item_fields']['Key'])
		);
		$this->assertEquals($db_item['name'], $data['item_fields']['Name']);
		$itemid = $db_item['itemid'];

		// Check saved pre-processing.
		$this->page->open('items.php?form=update&hostid=10084&itemid='.$itemid);
		$form->selectTab('Preprocessing');
		$steps = $this->assertPreprocessingSteps($data['preprocessing']);

		$rows = [];
		foreach (CDBHelper::getAll('SELECT step, error_handler FROM item_preproc WHERE itemid='.$itemid) as $row) {
			$rows[$row['step']] = $row['error_handler'];
		}

		foreach ($data['preprocessing'] as $i => $options) {
			// Validate preprocessing step in DB.
			$expected = (!array_key_exists('on_fail', $options) || !$options['on_fail'])
					? ZBX_PREPROC_FAIL_DEFAULT : $data['value'];

			$this->assertEquals($expected, $rows[$i + 1]);

			if (in_array($options['type'], ['Check for error in JSON', 'Discard unchanged with heartbeat'])){
				$this->assertFalse($steps[$i]['on_fail']->isEnabled());
				$this->assertFalse($steps[$i]['on_fail']->isSelected());
				$this->assertTrue($steps[$i]['error_handler'] === null || !$steps[$i]['error_handler']->isVisible());
				$this->assertTrue($steps[$i]['error_handler_params'] === null
					|| !$steps[$i]['error_handler_params']->isVisible()
				);
			}
			else {
				$this->assertTrue($steps[$i]['on_fail']->isSelected());
				$this->assertTrue($steps[$i]['on_fail']->isEnabled());
			}
		}
	}

	public static function getCustomOnFailValidationData() {
		$cases = [
			// 'Set value to' validation.
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set value empty',
					'Key' => 'set-value-empty'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => ''
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set value number',
					'Key' => 'set-value-number'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => '500'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set value string',
					'Key' => 'set-value-string'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => 'String'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set value special-symbols',
					'Key' => 'set-value-special-symbols'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => '!@#$%^&*()_+<>,.\/'
				]
			],
			// 'Set error to' validation.
			[
				'expected' => TEST_BAD,
				'fields' => [
					'Name' => 'Set error empty',
					'Key' => 'set-error-empty'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => ''
				],
				'error_details' => 'Incorrect value for field "error_handler_params": cannot be empty.'
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set error string',
					'Key' => 'set-error-string'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => 'Test error'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set error number',
					'Key' => 'set-error-number'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => '999'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set error special symbols',
					'Key' => 'set-error-special-symbols'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => '!@#$%^&*()_+<>,.\/'
				]
			]
		];

		$data = [];
		$preprocessing = [
			[
				'type' => 'Regular expression',
				'parameter_1' => 'expression',
				'parameter_2' => '\1',
				'on_fail' => true
			],
			[
				'type' => 'JSONPath',
				'parameter_1' => '$.data.test',
				'on_fail' => true
			],
			[
				'type' => 'Does not match regular expression',
				'parameter_1' => 'Pattern',
				'on_fail' => true
			]
		];

		foreach ($cases as $case) {
			$case['preprocessing'] = [];
			foreach ($preprocessing as $step) {
				$case['preprocessing'][] = array_merge($step, $case['custom_on_fail']);
			}

			$data[] = [$case];
		}

		return $data;
	}

	/**
	 * @dataProvider getCustomOnFailValidationData
	 */
	public function testFormItemPreprocessing_CustomOnFailValidation($data) {
		$this->page->login()->open('items.php?filter_set=1&filter_hostids[0]='.self::HOST_ID);
		$this->query('button:Create item')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		$form->fill($data['fields']);
		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps($data['preprocessing']);
		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']) {
			case TEST_GOOD:
				// Check if message is positive.
				$this->assertTrue($message->isGood());
				// Check message title.
				$this->assertEquals('Item added', $message->getTitle());
				// Check the results in DB.
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM items WHERE key_='.
						zbx_dbstr($data['fields']['Key']))
				);
				break;

			case TEST_BAD:
				// Check if message is negative.
				$this->assertTrue($message->isBad());
				// Check message title.
				$this->assertEquals('Cannot add item', $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB.
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM items where key_ = '.
						zbx_dbstr($data['fields']['Key']))
				);
				break;
		}
	}
}
