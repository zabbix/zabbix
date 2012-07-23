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
require_once dirname(__FILE__).'/../../include/classes/validators/CTimePeriodValidator.php';

class CTimePeriodValidatorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CTimePeriodValidator
	 */
	protected static $validator;

	public static function setUpBeforeClass() {
		self::$validator = new CTimePeriodValidator();
	}

	public static function validPeriods() {
		return array(
			array('1-7,00:00-24:00'),
			array('1,00:00-24:00'),
			array('1-7,0:00-9:00'),
			array('1-7,00:00-09:00'),
			array('1-7,00:00-09:00;'),
			array('1-7,00:00-09:00;1-7,00:00-09:00'),
			array('1-7,00:00-09:00;1-7,00:00-09:00;'),
		);
	}

	public static function invalidPeriods() {
		return array(
			array('1'),
			array('asd'),
			array('a5-7,00:00-09:00'),
			array('5-7,00:00-24:59;'),
			array('5-2,00:00-09:00;'),
			array('5-7,20:00-09:00;'),
			array('5-7,20:00-00:00;'),
			array('5-7,10:00-20:00;;'),
		);
	}

	/**
	 * @dataProvider validPeriods
	 */
	public function test_valid($period) {
		$result = self::$validator->validate($period);
		$this->assertTrue($result, sprintf('Valid time period "%s" is treated as invalid', $period));
	}

	/**
	 * @dataProvider invalidPeriods
	 */
	public function test_invalid($period) {
		$result = self::$validator->validate($period);
		$this->assertFalse($result, sprintf('Invalid time period "%s" is treated as valid', $period));
	}

	public function test_option_allow_multiple() {
		$validator = new CTimePeriodValidator(array('allow_multiple' => false));
		$validMultiPeriod = '1-7,00:00-09:00;1-7,00:00-09:00;';

		$result = $validator->validate($validMultiPeriod);
		$this->assertFalse($result, '"allow_multiple" option does not work');
	}
}
