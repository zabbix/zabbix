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

class CGlobalRegexpTest extends TestCase
{
	public function dataProvider()
	{
		return [
			// "character string included", case-sensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_INCLUDED, 'testString', 1),
				'success' => ['This is testString', 'testString starts here', 'orendswithtestString'],
				'fail' => ['', 'This is teststring', 'TeStString maybe?', 'orendswithTestString']
			],
			// "character string included", case-insensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_INCLUDED, 'testString', 0),
				'success' => ['This is testStrinGG', 'TestStrinG starts here', 'orendswithtestString',
					'This is teststring', 'TeStString maybe?'
				],
				'fail' => ['', 'orendswithTest String', 'this is not a TEST string']
			],
			// "character string NOT included", case-sensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_NOT_INCLUDED, 'testString', 1),
				'success' => ['', 'This is teststring', 'TeStString maybe?', 'orendswithTestString'],
				'fail' => ['This is testString', 'testString starts here', 'orendswithtestString']
			],
			// "character string NOT included", case-insensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_NOT_INCLUDED, 'testString', 0),
				'success' => ['', 'orendswithTest String', 'this is not a TEST string'],
				'fail' => ['This is testStrinGG', 'TestStrinG starts here', 'orendswithtestString',
					'This is teststring', 'TeStString maybe?'
				]
			],
			// ANY character string included, case-sensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_ANY_INCLUDED, 'Error,Disaster,Critical', 1),
				'success' => ['Error message', 'Object has Error', 'Status: Critical', 'Disaster Errors'],
				'fail' => ['ERROR: error', 'Object state: CRITICAL']
			],
			// ANY character string included, case-insensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_ANY_INCLUDED, 'Error,Disaster,Critical', 0),
				'success' => ['Error message', 'Object has Error', 'Status: Critical', 'Disaster Errors',
					'ERROR: error', 'Object state: CRITICAL', 'Log levels: DISASTER', 'Log levels: error'
				],
				'fail' => ['no errs or disastrs here']
			],
			// regular expressions, TRUE, case-sensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_TRUE, '^Log entry [0-9]+', 1),
				'success' => ['Log entry 29', 'Log entry 0', "line\nLog entry 5"],
				'fail' => [' Log entry 171', 'Log entry', 'lineLog entry 5']
			],
			// regular expressions, TRUE, case-insensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_TRUE, '^Log entry [0-9]+', 0),
				'success' => ['LOG ENTRY 71', 'log entry 161: something bad happened', "line\nLog entry 5"],
				'fail' => [' Log entry 171', 'log entry', 'lineLog entry 5']
			],
			// regular expressions, FALSE, case-sensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_FALSE, 'server state (1|3|5)', 1),
				'success' => ['server state 2', 'server state OK'],
				'fail' => ['server state 3', 'server state 1 - power failure']
			],
			// regular expressions, FALSE, case-insensitive
			[
				'expression' => $this->expr(EXPRESSION_TYPE_FALSE, 'server state (fail|outage)', 0),
				'success' => ['server state 3', 'server state NOT OK - power failure'],
				'fail' => ['Server state FAIL', 'server state outage of cooling liquid']
			],
			// extra tests, should verify both escaped and non-escaped slashes
			[
				'expression' => $this->expr(EXPRESSION_TYPE_TRUE, 'http://example.com/', 0),
				'success' => ['referrer: http://example.com/', 'request to http://example.com/test'],
				'fail' => ['example.com']
			],
			// empty part of search string is ignored during match
			[
				'expression' => $this->expr(EXPRESSION_TYPE_ANY_INCLUDED, '/', 0, '/'),
				'success' => ['/'],
				'fail' => []
			],
			// empty part of search string is ignored during match
			[
				'expression' => $this->expr(EXPRESSION_TYPE_ANY_INCLUDED, '/0/a/ /', 0, '/'),
				'success' => ['d// e', '1/0// '],
				'fail' => ['/', 'b//	b']
			]
		];
	}

	public function dataProviderMatchMethod() {
		return [
			// Char '/' is escaped before preg_match call.
			[
				'expression' => '/',
				'type' => EXPRESSION_TYPE_TRUE,
				'string' => 'test\/string'
			],
			// Double escaping of '/' should fail.
			[
				'expression' => '\\/',
				'type' => EXPRESSION_TYPE_FALSE,
				'string' => 'test/string'
			],
			// Char '/' should not be escaped if used as part of regex pattern.
			[
				'expression' => '^[a-z/]+$',
				'type' => EXPRESSION_TYPE_TRUE,
				'string' => 'test/string'
			],
			// Char '/' should not be escaped if used as part of regex pattern.
			[
				'expression' => '^[a-z\\\\/]+$',
				'type' => EXPRESSION_TYPE_TRUE,
				'string' => 'test/\\string'
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testMatchExpressions($expression, $successValues, $failValues)
	{
		foreach ($successValues as $successValue) {
			$this->assertTrue(CGlobalRegexp::matchExpression($expression, $successValue), 'Value: '.$successValue);
		}

		foreach ($failValues as $failValue) {
			$this->assertFalse(CGlobalRegexp::matchExpression($expression, $failValue), 'Value: '.$failValue);
		}
	}

	/**
	 * @dataProvider dataProviderMatchMethod
	 */
	public function testMatchMethod($expression, $expression_type, $string) {
		$expr = new CGlobalRegexp($expression);

		if ($expression_type == EXPRESSION_TYPE_TRUE) {
			$this->assertTrue($expr->match($string));
		}
		else {
			$this->assertFalse($expr->match($string));
		}
	}

	protected function expr($type, $expression, $caseSensitive, $separator = ',')
	{
		return [
			'expression_type' => $type,
			'expression' => $expression,
			'case_sensitive' => $caseSensitive,
			'exp_delimiter' => $separator
		];
	}
}
