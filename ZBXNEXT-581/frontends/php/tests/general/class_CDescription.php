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


require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/db.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';
require_once dirname(__FILE__).'/../../include/classes/core/Z.php';

function error($error) {
	echo "\nError reported: $error\n";
	return true;
}

class class_CDescription extends PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass() {
		$z = new Z();
		$z->run();
		DBconnect($error);
	}

	public static function providerTriggers() {
		return array(
			array(
				'13517',
				'trigger host.host:Host for trigger description macros | '.
				'host.host2:{HOST.HOST2} | host.name:Host for trigger description macros | '.
				'item.value:5 | item.value1:5 | item.lastvalue:5 | host.ip:127.0.0.1 | '.
				'host.dns: | host.conn:127.0.0.1'
			),
		);
	}

	public static function providerReferenceMacros() {
		return array(
			array(
				'expression' => '{123}=1',
				'text' => 'd $1'
			),
			array(
				'expression' => '{1}=1&{2}>2',
				'text' => 'd $1 $2 $3'
			),
			array(
				'expression' => '{1}=123&{2}>{$MACRO}',
				'text' => 'd $1 $2 $3'
			),
		);
	}

	/**
	 * @dataProvider providerTriggers
	 */
	public function test_expandTrigger($triggerId, $expectedDescription) {
		$trigger = DBfetch(DBselect(
			'SELECT t.triggerid,t.expression,t.description'.
				' FROM triggers t'.
				' WHERE t.triggerid='.$triggerId
		));

		$description = CMacrosResolverHelper::resolveTriggerName($trigger);

		$this->assertEquals($expectedDescription, $description);
	}

	/**
	 * @dataProvider providerReferenceMacros
	 */
	public function test_resolveTriggerReference($expression, $text) {
		$result = CMacrosResolverHelper::resolveTriggerReference($expression, $text);

		$this->assertEquals($text, $result);
	}
}
