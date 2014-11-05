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


class CheckTypeTest extends PHPUnit_Framework_TestCase {

	public static function setupBeforeClass() {
		CWebUser::$data['debug_mode'] = true;
	}

	public function testValidProvider() {
		$fieldName = 'field_name';
		return array(
			array($fieldName, P_SYS, '[]', T_ZBX_JSON),
			array($fieldName, P_SYS, '[1,2,3]', T_ZBX_JSON),
			array($fieldName, P_SYS, '{"asdf":"123"}', T_ZBX_JSON),
			array($fieldName, P_SYS, '[[]]', T_ZBX_JSON),
		);
	}

	public function testInvalidProvider() {
		$fieldName = 'field_name';
		return array(
			array($fieldName, P_SYS, '123', T_ZBX_JSON, 'error', 'Field "'.$fieldName.'" is not a JSON-encoded array.'),
			array($fieldName, P_SYS, '', T_ZBX_JSON, 'error', 'Field "'.$fieldName.'" is not a JSON-encoded array.'),
			array($fieldName, P_SYS, '"sadasd"', T_ZBX_JSON, 'error', 'Field "'.$fieldName.'" is not a JSON-encoded array.'),
			array($fieldName, P_SYS, 'aa.ee', T_ZBX_JSON, 'error', 'Field "'.$fieldName.'" is not a JSON-encoded array.'),
			array($fieldName, P_SYS, false, T_ZBX_JSON, 'error', 'Field "'.$fieldName.'" is not a JSON-encoded array.'),
			array($fieldName, P_SYS, 1234, T_ZBX_JSON, 'error', 'Field "'.$fieldName.'" is not a JSON-encoded array.'),
			array($fieldName, P_SYS, null, T_ZBX_JSON, 'error', 'Field "'.$fieldName.'" is not a JSON-encoded array.'),
		);
	}

	protected function setUp() {
		global $ZBX_MESSAGES;

		$ZBX_MESSAGES = array();
	}

	/**
	 * @dataProvider testValidProvider
	 *
	 * @param string	$field
	 * @param integer	$flags
	 * @param mixed		$var
	 * @param integer	$type
	 */
	public function testValidCheckType($field, $flags, $var, $type) {

		$varBefore = $var;
		$ok = check_type($field, $flags, $var, $type, $field);

		$this->assertSame(ZBX_VALID_OK, $ok);
		$this->assertSame(CJs::encodeJson($var), $varBefore);
	}

	/**
	 * @dataProvider testInvalidProvider
	 *
	 * @param string 	$field
	 * @param integer	$flags
	 * @param mixed		$var
	 * @param integer	$type
	 * @param string	$messageType
	 * @param string	$messageText
	 *
	 * @internal     param string $caption
	 */
	public function testInvalidCheckType($field, $flags, $var, $type, $messageType, $messageText) {
		global $ZBX_MESSAGES;
		$ok = check_type($field, $flags, $var, $type, $field);

		$this->assertSame(ZBX_VALID_ERROR, $ok);
		$this->assertSame($messageType, $ZBX_MESSAGES[0]['type']);
		$this->assertSame($messageText, $ZBX_MESSAGES[0]['message']);
	}
}
