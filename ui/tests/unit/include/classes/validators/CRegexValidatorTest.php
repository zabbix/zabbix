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


class CRegexValidatorTest extends CValidatorTest
{
	public function dataProviderValidParam()
	{
		return [[
			[
				'messageInvalid' => 'Invalid regular expression'
			]
		]];
	}

	public function dataProviderValidValues()
	{
		return [
			[[], 'foobar'],
			[[], '/foobar'],
			[[], 'foobar/'],
			[[], 'foobar/i'],
			[[], '/'],
			[[], ' '],
			[[], '\\\\'],
			[[], '[A-Z]+[0-9]{123}foo.*(bar|buz)[^A-K]{4}'],
			[[], 'asd\('],
			[[], '^Timestamp \[[0-9]{4}-[A-Za-z]{3}-[0-9]{1,2}\]: ERROR.*$'],
			[[], '/[a-z]+'],
			[[], '[a-z]+\ \[/'],
			[[], '[a-f0-9]{32}/iu'],
			[[], '[a-f0-9]{32}/i'],
			[[], '/foo bar// me!/'],
			[[], 1],
			[[], 1.2]
		];
	}

	public function dataProviderInvalidValues()
	{
		return [
			[
				['messageInvalid' => 'Not a string'],
				[],
				'Not a string'
			],
			[
				['messageInvalid' => 'Not a string'],
				null,
				'Not a string'
			],
			[
				['messageInvalid' => 'Not a string'],
				true,
				'Not a string'
			],
			[
				['messageRegex' => 'Incorrect regular expression "%1$s": "%2$s"'],
				'[[',
				'Incorrect regular expression "[[": "Compilation failed: missing terminating ] for character class at offset 2"'
			],
			[
				['messageRegex' => 'Incorrect regular expression "%1$s": "%2$s".'],
				'asd(',
				'Incorrect regular expression "asd(": "Compilation failed: missing closing parenthesis at offset 4".'
			]
		];
	}

	public function dataProviderInvalidValuesWithObjects()
	{
		return [
			[
				['messageRegex' => 'Incorrect regular expression "%2$s" for object "%1$s": "%3$s"'],
				'test[',
				'Incorrect regular expression "test[" for object "object": "Compilation failed: missing terminating ] for character class at offset 5"'
			]
		];
	}

	protected function createValidator(array $params = [])
	{
		return new CRegexValidator($params);
	}
}
