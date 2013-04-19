<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/debug.inc.php';
require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/classes/validators/CValidator.php';
require_once dirname(__FILE__).'/../../include/classes/validators/CTriggerFunctionValidator.php';

class CTriggerFunctionValidatorTest extends PHPUnit_Framework_TestCase {

	public static function provider() {
		return array(
			// str
			array('str', array('abc', '1'), 4, true),
			array('str', array('abc', '0#'), 4, false),
			array('str', array('abc', '#'), 4, false),
			array('str', array('abc', '#0'), 4, false),
			array('str', array('abc', '#-1'), 4, false),
			array('str', array('abc', '#a'), 4, false),
			array('str', array('abc', '#1.0'), 4, false),
			array('str', array('abc', '#1.1'), 4, false),
			array('str', array('abc', '#0.1'), 4, false),
			array('str', array('abc', '#1a'), 4, false),
			array('str', array('abc', '#1'), 4, true),
			array('str', array('abc', '#123467890123456790'), 4, true),
			// regexp
			array('regexp', array('abc', '1'), 4, true),
			array('regexp', array('abc', '123467890123456790'), 4, true),
			array('regexp', array('abc', '0'), 4, false),
			array('regexp', array('abc', '#'), 4, false),
			array('regexp', array('abc', '0#'), 4, false),
			array('regexp', array('abc', '#0'), 4, false),
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test_parse($functionName, $functionParamList, $valueType, $expectedResult) {
		$triggerFunctionValidator = new CTriggerFunctionValidator();

		$result = $triggerFunctionValidator->validate(array(
			'functionName' => $functionName,
			'functionParamList' => $functionParamList,
			'valueType' => $valueType
		));

		if (!$result && $expectedResult) {
			echo $triggerFunctionValidator->getError()."\n";
		}

		$this->assertSame($result, $expectedResult);
	}
}
