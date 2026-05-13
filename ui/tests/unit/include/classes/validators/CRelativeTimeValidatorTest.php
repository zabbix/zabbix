<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

class CRelativeTimeValidatorTest extends TestCase {
	public function dataProvider(): array {
		return [
			// Valid relative times.
			['now', 				[], 							null],
			['now/d', 				[], 							null],
			['now-1s', 				[], 							null],
			['now/d+1s',			[],								null],
			['{$MACRO}', 			['usermacros' => true], 		null],
			['now', 				['max_now' => true],			null],

			// Invalid relative time
			['{$MACRO}', 			[],																	'a relative time is expected'],
			['{#MACRO}', 			[],																	'a relative time is expected'],
			['now/d', 				['allowed_types' => [CRelativeTimeParser::ZBX_TOKEN_OFFSET]],		'a relative time is expected'],
			['now-1s', 				['allowed_types' => [CRelativeTimeParser::ZBX_TOKEN_PRECISION]],	'a relative time is expected'],
			['now+1s',				['max_now' => true], 												'should be less than or equal to current time'],
			['now+1h',				['allowed_suffixes' => ['s']], 										'unsupported time suffix'],
			['now+10s+5s',			['max_tokens' => 1], 												'only one time unit is allowed'],
			['now+10+5+3',			['max_tokens' => 2], 												'only 2 time units are allowed']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testRelativeTimeValidator($name, $options, $expected_error): void {
		$validator = new CRelativeTimeValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($name));
		$this->assertSame($expected_error, $validator->getError());
	}
}
