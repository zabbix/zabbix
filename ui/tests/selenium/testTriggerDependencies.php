<?php
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup trigger_depends
 */
class testTriggerDependencies extends CLegacyWebTest {

	/**
	* @dataProvider testTriggerDependenciesFromHost_SimpleTestProvider
	*/
	public function testTriggerDependenciesFromHost_SimpleTest($hostId, $trigger, $template, $dependencies, $expected) {
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

		$this->zbxTestLogin('triggers.php?filter_set=1&context=template&filter_hostids[0]='.$hostId);
		$this->zbxTestCheckTitle('Configuration of triggers');

		$this->zbxTestClickLinkTextWait($trigger);
		$this->zbxTestClickWait('tab_dependenciesTab');

		$this->zbxTestClick('bnt1');
		$this->zbxTestLaunchOverlayDialog('Triggers');
		$host = COverlayDialogElement::find()->one()->query('class:multiselect-control')->asMultiselect()->one();
		$host->fill([
			'values' => $template,
			'context' => 'Templates'
		]);
		$this->zbxTestClickLinkTextWait($dependencies);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('bnt1'));
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent($expected);
	}

	public function testTriggerDependenciesFromHost_SimpleTestProvider() {
		return [
			[
				'10050',
				'Zabbix agent is not available (for {$AGENT.TIMEOUT})',
				'FreeBSD',
				'/etc/passwd has been changed on FreeBSD',
				'Not all templates are linked to'
			],
			[
				'10265',
				'Apache: Service is down',
				'Apache by HTTP',
				'Apache: Service response time is too high (over 10s for 5m)',
				'Cannot create circular dependencies.'
			],
			[
				'10265',
				'Apache: has been restarted (uptime < 10m)',
				'Apache by HTTP',
				'Apache: has been restarted (uptime < 10m)',
				'Cannot create dependency on trigger itself.'
			],
			[
				'10265',
				'Apache: has been restarted (uptime < 10m)',
				'Apache by HTTP',
				'Apache: Service is down',
				'Trigger updated'
			]
		];
	}
}
