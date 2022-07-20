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
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup trigger_depends, hosts_templates
 *
 * @onBefore prepareTemplateData
 */
class testTriggerDependencies extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	const TEMPLATE_AGENT = 'Zabbix agent';
	const TEMPLATE_FREEBSD = 'FreeBSD by Zabbix agent';
	const TEMPLATE_APACHE = 'Apache by HTTP';

	protected static $agent_templateid;
	protected static $freebsd_templateid;
	protected static $apache_templateid;

	/**
	 * Function links Zabbix agent template to FreeBSD by Zabbix agent template.
	 */
	public static function prepareTemplateData() {
		$template_ids = CDBHelper::getAll('SELECT hostid FROM hosts WHERE host IN ('.zbx_dbstr(self::TEMPLATE_AGENT).','.
				zbx_dbstr(self::TEMPLATE_FREEBSD).','.zbx_dbstr(self::TEMPLATE_APACHE).') ORDER BY host ASC'
		);

		self::$apache_templateid = $template_ids[0]['hostid'];
		self::$freebsd_templateid = $template_ids[1]['hostid'];
		self::$agent_templateid = $template_ids[2]['hostid'];

		CDataHelper::call('template.update', [
			[
				'templateid' => self::$freebsd_templateid,
				'templates' => [
					[
						'templateid' => self::$agent_templateid
					]
				]
			]
		]);
	}

	/**
	 * @dataProvider getTriggerDependenciesData
	 */
	public function testTriggerDependenciesFromHost_SimpleTest($data) {
		// Get the id of template to be updated based on the template that owns the trigger in dependencies tab.
		$ids = [
			self::TEMPLATE_AGENT => self::$agent_templateid,
			self::TEMPLATE_APACHE => self::$apache_templateid
		];
		$update_id = ($data['template'] === self::TEMPLATE_APACHE) ? $ids[self::TEMPLATE_APACHE] : $ids[self::TEMPLATE_AGENT];

		$this->zbxTestLogin('triggers.php?filter_set=1&context=template&filter_hostids[0]='.$update_id);
		$this->zbxTestCheckTitle('Configuration of triggers');

		$this->zbxTestClickLinkTextWait($data['trigger']);
		$this->zbxTestClickWait('tab_dependenciesTab');

		$this->zbxTestClick('bnt1');
		$this->zbxTestLaunchOverlayDialog('Triggers');
		$host = COverlayDialogElement::find()->one()->query('class:multiselect-control')->asMultiselect()->one();
		$host->fill([
			'values' => $data['template'],
			'context' => 'Templates'
		]);
		$this->zbxTestClickLinkTextWait($data['dependency']);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('bnt1'));
		$this->zbxTestClickWait('update');
		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update trigger', $data['error_message']);
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Trigger updated');
		}
	}

	public function getTriggerDependenciesData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'trigger' => 'Zabbix agent is not available',
					'template' => self::TEMPLATE_FREEBSD,
					'dependency' => '/etc/passwd has been changed on FreeBSD by Zabbix agent',
					'error_message' => 'Trigger "Zabbix agent is not available" cannot depend on the trigger "/etc/passwd has been changed'.
							' on {HOST.NAME}" from the template "FreeBSD by Zabbix agent", because dependencies on triggers'.
							' from a child template or host are not allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'trigger' => 'Apache: Service is down',
					'template' => self::TEMPLATE_APACHE,
					'dependency' => 'Apache: Service response time is too high',
					'error_message' => 'Trigger "Apache: Service is down" cannot depend on the trigger "Apache: Service response'.
							' time is too high", because a circular linkage ("Apache: Service response time is too high" ->'.
							' "Apache: Service is down" -> "Apache: Service response time is too high") would occur.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'trigger' => 'Apache: has been restarted',
					'template' => self::TEMPLATE_APACHE,
					'dependency' => 'Apache: has been restarted',
					'error_message' => 'Trigger "Apache: has been restarted" cannot depend on the trigger "Apache: '.
							'has been restarted", because a circular linkage ("Apache: has been restarted" -> "Apache: '.
							'has been restarted") would occur.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'trigger' => 'Apache: has been restarted',
					'template' => self::TEMPLATE_APACHE,
					'dependency' => 'Apache: Service is down'
				]
			]
		];
	}
}
