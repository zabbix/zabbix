<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/locales.inc.php';
require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/classes/validators/CValidator.php';
require_once dirname(__FILE__).'/../../include/classes/validators/CColorValidator.php';


class CTimeColorValidatorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CTimeColorValidator
	 */
	protected static $validator;

	public static function setUpBeforeClass() {
		self::$validator = new CColorValidator();
	}

	public static function provider() {
		return array(
				array('A1A1A1', TRUE),
				array('B2B2B2', TRUE),
				array('C3C3C3', TRUE),
				array('D4D4D4', TRUE),
				array('E5E5E5', TRUE),
				array('D6D6D6', TRUE),
				array('000000', TRUE),
				array('999999', TRUE),
				array('111111', TRUE),
				array('222222', TRUE),
				array('333333', TRUE),
				array('444444', TRUE),
				array('555555', TRUE),
				array('666666', TRUE),
				array('777777', TRUE),
				array('888888', TRUE),
				array('AAAAAA', TRUE),
				array('BBBBBB', TRUE),
				array('CCCCCC', TRUE),
				array('DDDDDD', TRUE),
				array('EEEEEE', TRUE),
				array('FFFFFF', TRUE),
				array('1A1A1A', TRUE),
				array('2B2B2B', TRUE),
				array('3C3C3C', TRUE),
				array('4D4D4D', TRUE),
				array('5E5E5E', TRUE),
				array('6F6F6F', TRUE),
				array('A1A1A1', TRUE),
				array('B2B2B2', TRUE),
				array('C3C3C3', TRUE),
				array('D4D4D4', TRUE),
				array('E5E5E5', TRUE),
				array('D6D6D6', TRUE),
				array('123456', TRUE),
				array('789012', TRUE),
				array('345678', TRUE),
				array('901234', TRUE),
				array('567890', TRUE),
				array('123456', TRUE),
				array('7890AB', TRUE),
				array('CDEF12', TRUE),
				array('90ABCD', TRUE),
				array('EF1234', TRUE),
				array('aaaaaa', TRUE),
				array('bbbbbb', TRUE),
				array('cccccc', TRUE),
				array('dddddd', TRUE),
				array('eeeeee', TRUE),
				array('ffffff', TRUE),
				array('ZZZZZ', FALSE),
				array('@#$%^&', FALSE),
				array('', FALSE),
				array('123', FALSE),
				array('ABC', FALSE),
				array('acb', FALSE),
				array('123ZZZ', FALSE),
				array('WWW123', FALSE),
				array('black', FALSE),
				array('pink', FALSE),
				array('yellow', FALSE),
				array('AAAAAA;;;', FALSE),
				array('A1A1A1;B1B1B1;C1C1C1', FALSE),
				array('123456;789012;345678;', FALSE),
				array('ABCDEF1234567890', FALSE),
		);
	}

	/**
	* @dataProvider provider
	*/
	public function test_parseColour($color, $expectedResult) {

		$result = self::$validator->validate($color);
		if ($expectedResult === false) {
			$this->assertFalse($result, sprintf('Invalid color "%s" is treated as valid', $color));
		}
		else {
			$this->assertTrue($result, sprintf('Valid color "%s" is treated as invalid', $color));
		}
	}
}
