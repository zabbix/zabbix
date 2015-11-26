<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CStringValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return array(
			array(array(
				'empty' => true,
				'maxLength' => 10,
				'regex' => '/[a-z]+/',
				'messageInvalid' => 'Not a string',
				'messageEmpty' => 'String empty',
				'messageMaxLength' => 'String too long',
				'messageRegex' => 'Incorrect string'
			))
		);
	}

	public function validValuesProvider() {
		return array(
			array(array(), 'string'),
			array(array(), 123),
			array(array(), 123.5),
			array(array(), 0),

			array(array('empty' => true), ''),

			array(array('maxLength' => 6), 'string'),
			array(array('maxLength' => 6), 123456),
			array(array('maxLength' => 6), 1234.5),

			array(array('regex' => '/^\d+$/'), 1),
			array(array('regex' => '/^\d+$/'), '3'),
			array(array('regex' => '/^\d+$/', 'empty' => true), ''),
		);
	}

	public function invalidValuesProvider() {
		return array(
			array(
				array('messageEmpty' => 'Empty string'),
				'',
				'Empty string'
			),
			array(
				array('messageInvalid' => 'Not a string'),
				null,
				'Not a string'
			),
			array(
				array('messageInvalid' => 'Not a string'),
				array(),
				'Not a string'
			),

			array(
				array('maxLength' => 6, 'messageMaxLength' => 'String "%1$s" is longer then %2$s chars'),
				'longstring',
				'String "longstring" is longer then 6 chars'
			),
			array(
				array('maxLength' => 6, 'messageMaxLength' => 'String "%1$s" is longer then %2$s chars'),
				1234567,
				'String "1234567" is longer then 6 chars'
			),
			array(
				array('maxLength' => 6, 'messageMaxLength' => 'String "%1$s" is longer then %2$s chars'),
				1234567.8,
				'String "1234567.8" is longer then 6 chars'
			),

			array(
				array('regex' => '/^\d+$/', 'messageRegex' => 'String "%1$s" doesn\'t match regex'),
				'string',
				'String "string" doesn\'t match regex'
			),
			array(
				array('regex' => '/^\d+$/', 'messageEmpty' => 'Empty string'),
				'',
				'Empty string'
			),
		);
	}

	public function invalidValuesWithObjectsProvider() {
		return array(
			array(
				array('messageEmpty' => 'Empty string for "%1$s"'),
				'',
				'Empty string for "object"'
			),
			array(
				array('messageInvalid' => 'Not a string for "%1$s"'),
				null,
				'Not a string for "object"'
			),
			array(
				array('messageInvalid' => 'Not a string for "%1$s"'),
				array(),
				'Not a string for "object"'
			),

			array(
				array('maxLength' => 6, 'messageMaxLength' => 'String "%2$s" is longer then %3$s chars for "%1$s"'),
				'longstring',
				'String "longstring" is longer then 6 chars for "object"'
			),
			array(
				array('maxLength' => 6, 'messageMaxLength' => 'String "%2$s" is longer then %3$s chars for "%1$s"'),
				1234567,
				'String "1234567" is longer then 6 chars for "object"'
			),
			array(
				array('maxLength' => 6, 'messageMaxLength' => 'String "%2$s" is longer then %3$s chars for "%1$s"'),
				1234567.8,
				'String "1234567.8" is longer then 6 chars for "object"'
			),

			array(
				array('regex' => '/^\d+$/', 'messageRegex' => 'String "%2$s" doesn\'t match regex for "%1$s"'),
				'string',
				'String "string" doesn\'t match regex for "object"'
			),
			array(
				array('regex' => '/^$/', 'messageEmpty' => 'Empty string'),
				'',
				'Empty string'
			),
		);
	}

	protected function createValidator(array $params = array()) {
		return new CStringValidator($params);
	}
}
