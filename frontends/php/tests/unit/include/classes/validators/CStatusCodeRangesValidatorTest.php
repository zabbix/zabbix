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


/**
 * PHPUnit test for status code ranges validator.
 */
class CStatusCodeRangesValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return [
			[['usermacros' => false]]
		];
	}

	public function validValuesProvider() {
		return [
			[
				['usermacros' => true], '{$MACRO}'
			],
			[
				['usermacros' => true], '{$MACRO}-{$MACRO}'
			],
			[
				['usermacros' => true], '200-{$MACRO}'
			],
			[
				['usermacros' => true], '{$MACRO}-200'
			],
			[
				['lldmacros' => true], '{#SCODE}'
			],
			[
				['lldmacros' => true], '{#SCODE_MIN}-{#SCODE_MAX}'
			],
			[
				['lldmacros' => true], '200-{#SCODE_MAX}'
			],
			[
				['lldmacros' => true], '{#SCODE_MIN}-200'
			],
			[
				[], '200-400'
			],
			[
				[], '200'
			],
			[
				[], '200,301'
			],
			[
				[], "200,\t301,\r\n100\t-\r\n    200\t"
			],
			[
				['usermacros' => true], '{$MACRO}-{$MACRO},{$MACRO},{$MACRO}-200,200-{$MACRO},200-301'
			],
			[
				['lldmacros' => true], '{#SCODE_MIN}-{#SCODE_MAX},{#SCODE},{#SCODE_MIN}-200,200-{#SCODE_MAX},200-301'
			],
			[
				['usermacros' => true, 'lldmacros' => true],
				'{$MACRO}-{$MACRO},{$MACRO},{$MACRO}-200,200-{$MACRO},200-301,{#SCODE_MIN}-{#SCODE_MAX},{#SCODE},'.
					'{#SCODE_MIN}-200,200-{#SCODE_MAX},200-301,{#SCODE}-{$MACRO}'
			]
		];
	}

	public function invalidValuesProvider() {
		return [
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				'{$MACRO}',
				'Invalid value "{$MACRO}"'
			],
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				'{#SCODE}',
				'Invalid value "{#SCODE}"'
			],
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				'200-',
				'Invalid value "200-"'
			],
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				'200-300-500',
				'Invalid value "200-300-500"'
			],
			[
				['usermacros' => false, 'messageInvalid' => 'Invalid value "%1$s"'],
				'200-{$MACRO}',
				'Invalid value "200-{$MACRO}"'
			],
			[
				['usermacros' => true, 'messageInvalid' => 'Invalid value "%1$s"'],
				'200-{#SCODE}',
				'Invalid value "200-{#SCODE}"'
			],
			[
				['lldmacros' => false, 'messageInvalid' => 'Invalid value "%1$s"'],
				'200-{#SCODE}',
				'Invalid value "200-{#SCODE}"'
			],
			[
				['lldmacros' => true, 'messageInvalid' => 'Invalid value "%1$s"'],
				'200-{$MACRO}',
				'Invalid value "200-{$MACRO}"'
			],
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				'500-100',
				'Invalid value "500-100"'
			],
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				'',
				'Invalid value ""'
			],
			[
				['usermacros' => true, 'messageInvalid' => 'Invalid value "%1$s"'],
				'',
				'Invalid value ""'
			],
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				false,
				'Invalid value "false"'
			],
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				null,
				'Invalid value "null"'
			],
			[
				['messageInvalid' => 'Invalid value "%1$s"'],
				[],
				'Invalid value "array"'
			]
		];
	}

	public function invalidValuesWithObjectsProvider() {
		return [
			[
				['messageInvalid' => 'Invalid value "%2$s" for %1$s'],
				null,
				'Invalid value "null" for object'
			],
		];
	}

	public function createValidator(array $params = []) {
		return new CStatusCodeRangesValidator($params);
	}
}
