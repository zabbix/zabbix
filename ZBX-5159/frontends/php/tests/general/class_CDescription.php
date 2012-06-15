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
require_once dirname(__FILE__).'/../../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/db.inc.php';
require_once dirname(__FILE__).'/../../include/classes/db/DB.php';
require_once dirname(__FILE__).'/../../include/classes/debug/CProfiler.php';
require_once dirname(__FILE__).'/../../include/classes/helpers/trigger/CDescription.php';
require_once dirname(__FILE__).'/../../include/classes/helpers/trigger/CTriggerDescription.php';
require_once dirname(__FILE__).'/../../include/classes/helpers/trigger/CEventDescription.php';

class class_CDescription extends PHPUnit_Framework_TestCase {

	public static function providerTriggers() {
		return array(
			array(
				'1111',
				'description'
			),
		);
	}

	public static function providerReferenceMacros() {
		return array(
			array(
				array(
					'expression' => '{123}=1',
					'description' => 'd $1'
				),
				'd 1',
			),
			array(
				array(
					'expression' => '{1}=1&{2}>2',
					'description' => 'd $1 $2 $3'
				),
				'd 1 2 ',
			),
			array(
				array(
					'expression' => '{1}=123&{2}>{$MACRO}',
					'description' => 'd $1 $2 $3'
				),
				'd 123  ',
			),
		);
	}

	public function test_expandTrigger($triggerId, $expectedDescription) {
		$trigger = DBfetch(DBselect(
			'SELECT t.trigerid,t,expression,t.description'.
				' FROM triggers t'.
				' WHERE t.trigerid='.$triggerId
		));
		$description = CDescription::expandTrigger($trigger);

		$this->assertEquals($expectedDescription, $description);
	}

	/**
	 * @dataProvider providerReferenceMacros
	 */
	public function test_expandReferenceMacros($trigger, $expectedDescription) {
		$description = CDescription::expandReferenceMacros($trigger);

		$this->assertEquals($expectedDescription, $description);
	}
}
