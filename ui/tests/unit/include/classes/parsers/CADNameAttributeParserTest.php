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

class CADNameAttributeParserTest extends TestCase {

	/**
	 * An array of user name strings and parsed results.
	 */
	public static function dataProvider() {
		$strict = ['strict' => true];
		// Parse only sAMAccountName.
		$only_sama = ['nametype' => CADNameAttributeParser::ZBX_TYPE_SAMA];
		// Parse only UserPrincipalName.
		$only_upn = ['nametype' => CADNameAttributeParser::ZBX_TYPE_UPN];
		// Parse sAMAccountName and UserPrincipalName.
		$sama_and_upn = ['nametype' => CADNameAttributeParser::ZBX_TYPE_SAMA | CADNameAttributeParser::ZBX_TYPE_UPN];

		// Parsed name type should be sAMAccountName.
		$should_be_sama = ['name_type' => CADNameAttributeParser::ZBX_TYPE_SAMA];
		// Parsed name type should be UserPrincipalName.
		$should_be_upn = ['name_type' => CADNameAttributeParser::ZBX_TYPE_UPN];
		// Parsed name type should be unknown.
		$should_be_unknown = [
			'name_type' => CADNameAttributeParser::ZBX_TYPE_UNKNOWN,
			'match' => '',
			'user' => null,
			'domain' => null
		];

		return [
			// sAMAccountName tests.
			[
				'longdomainnamegoeshere\user', 0, $strict,
				[
					'rc' => CParser::PARSE_FAIL
				] + $should_be_unknown
			],
			[
				'longdomainnamegoeshere\user', 7, $strict,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'ainnamegoeshere\user',
					'user' => 'user',
					'domain' => 'ainnamegoeshere'
				] + $should_be_sama
			],
			[
				'domain\user', 0, $strict,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'domain\user',
					'user' => 'user',
					'domain' => 'domain'
				] + $should_be_sama
			],
			[
				'user@domain.com', 0, $strict + $only_sama,
				[
					'rc' => CParser::PARSE_FAIL
				] + $should_be_unknown
			],
			[
				'domain\user', 0, $strict + $sama_and_upn,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'domain\user',
					'user' => 'user',
					'domain' => 'domain'
				] + $should_be_sama
			],
			[
				'longdomainnamegoeshere\user', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'longdomainnamegoeshere\user',
					'user' => 'user',
					'domain' => 'longdomainnamegoeshere'
				] + $should_be_sama
			],
			[
				'"/\[]:;|=,+*?<>\user', 0, [],
				[
					'rc' => CParser::PARSE_FAIL
				] + $should_be_unknown
			],
			[
				'"/\[]:;|=,+*?<>\user', 0, $strict,
				[
					'rc' => CParser::PARSE_FAIL
				] + $should_be_unknown
			],
			[
				'valid"/\[]:;|=,+*?<>\user', 6, $strict,
				[
					'rc' => CParser::PARSE_FAIL
				] + $should_be_unknown
			],
			// UPN tests.
			[
				'domain\user', 0, $only_upn,
				[
					'rc' => CParser::PARSE_FAIL
				] + $should_be_unknown
			],
			[
				'domain\user@domain.com', 0, $only_upn,
				[
					'rc' => CParser::PARSE_FAIL
				] + $should_be_unknown
			],
			[
				'anna@comp', 0, $only_upn,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'anna@comp',
					'user' => 'anna',
					'domain' => 'comp'
				] + $should_be_upn
			],
			[
				'user@example.com', 0, $only_upn,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'user@example.com',
					'user' => 'user',
					'domain' => 'example.com'
				] + $should_be_upn
			],
			[
				'user@example.com', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'user@example.com',
					'user' => 'user',
					'domain' => 'example.com'
				] + $should_be_upn
			],
			[
				'r@cameron@mydomain.com', 0, $only_upn,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'r@cameron@mydomain.com',
					'user' => 'r@cameron',
					'domain' => 'mydomain.com'
				] + $should_be_upn
			],
			// sAMAccountName or UPN
			[
				'example\user@domain.com', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'example\user',
					'user' => 'user',
					'domain' => 'example'
				] + $should_be_sama
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	 */
	public function testParse($source, $pos, array $options, array $expected) {
		$parser = new CADNameAttributeParser($options);
		$out = [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'name_type' => $parser->getNameType(),
			'user' => $parser->getUserName(),
			'domain' => $parser->getDomainName()
		];
		ksort($out);
		ksort($expected);

		$this->assertSame($expected, array_intersect_key($out, $expected));
	}
}
