<?php
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


require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/db.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';
require_once dirname(__FILE__).'/../../include/classes/core/APP.php';

function error($error) {
	echo "\nError reported: $error\n";
	return true;
}

use PHPUnit\Framework\TestCase;

class class_CDescription extends TestCase {

	public static function setUpBeforeClass() {
		$app = new App();
		$app->run();
		DBconnect($error);
	}

	public static function providerTriggers() {
		return [
			[
				'13517',
				'trigger host.host:Host for trigger description macros | '.
				'host.host2:{HOST.HOST2} | host.name:Host for trigger description macros | '.
				'item.value:5 | item.value1:5 | item.lastvalue:5 | host.ip:127.0.0.1 | '.
				'host.dns: | host.conn:127.0.0.1'
			]
		];
	}

	public static function providerReferenceMacros() {
		return [
			[
				'expression' => '{123}=1',
				'text' => 'd $1'
			],
			[
				'expression' => '{1}=1&{2}>2',
				'text' => 'd $1 $2 $3'
			],
			[
				'expression' => '{1}=123&{2}>{$MACRO}',
				'text' => 'd $1 $2 $3'
			]
		];
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
