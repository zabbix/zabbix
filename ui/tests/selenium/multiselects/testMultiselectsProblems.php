<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testMultiselectDialogs.php';

/**
 * Test for checking multiselects dialogs on Problems page.
 *
 * @backup hosts
 *
 * @onBefore prepareTriggerData
 */
class testMultiselectsProblems extends testMultiselectDialogs {

	public static function getCheckDialogsData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Host groups' => 'Zabbix servers'
					]
				]
			],
			// #1.
			[
				[
					'fields' => [
						'Hosts' => 'ЗАББИКС Сервер'
					]
				]
			],
			// #2.
			[
				[
					'fields' => [
						'Triggers' => 'First test trigger with tag priority'
					]
				]
			]
		];
	}

	/**
	 * Test for checking that multiselects' dialogs do not contain any errors before and after filling.
	 *
	 * @dataProvider getCheckDialogsData
	 */
	public function testMultiselectsProblems_CheckDialogs($data) {
		$this->page->login()->open('zabbix.php?action=problem.view');
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$multiselects = [
			['Host groups' => 'Host groups'],
			['Hosts' => 'Hosts', 'Host group' => 'Host groups'],
			['Triggers' => 'Triggers', 'Host' => 'Hosts', 'Host group' => 'Host groups']
		];

		// Check all multiselects in filter before one of the multiselects is filled.
		$this->checkMultiselectDialogs($filter_form, $multiselects);
		$filter_form->fill($data['fields']);

		// Check all multiselects in filter after one of the multiselects is filled.
		$this->checkMultiselectDialogs($filter_form, $multiselects);

		$this->query('button:Reset')->waitUntilClickable()->one()->click();
	}

	public static function prepareTriggerData() {
		// Create host groups.
		CDataHelper::call('hostgroup.create', [
			['name' => 'ZBX6648 All Triggers'],
			['name' => 'ZBX6648 Enabled Triggers'],
			['name' => 'ZBX6648 Disabled Triggers'],
			['name' => 'ZBX6648 Group No Hosts']
		]);
		$groupids = CDataHelper::getIds('name');

		CDataHelper::createHosts([
			// Host without triggers.
			[
				'host' => 'ZBX6648 No Triggers Host',
				'groups' => ['groupid' => 4],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'zbx6648 no triggers',
						'key_' => 'zbx6648-no-triggers',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			// Host with one enabled trigger and one disabled trigger.
			[
				'host' => 'ZBX6648 All Triggers Host',
				'groups' => ['groupid' => $groupids['ZBX6648 All Triggers']],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'zbx6648 item all',
						'key_' => 'zbx6648-item-all',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'zbx6648 item all disabled',
						'key_' => 'zbx6648-item-all-disabled',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'status' => ITEM_STATUS_DISABLED
					]
				]
			],
			// Host with disabled trigger and with disabled item that belongs to enabled trigger.
			[
				'host' => 'ZBX6648 Disabled Triggers Host',
				'groups' => [
					'groupid' => $groupids['ZBX6648 Disabled Triggers'],
					'groupid' => 4
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'zbx6648 item for disabled trigger',
						'key_' => 'zbx6648-item-for-disabled-trigger',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'zbx6648 item disabled',
						'key_' => 'zbx6648-item-disabled',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'status' => ITEM_STATUS_DISABLED
					]
				]
			],
			// Host with one enabled trigger.
			[
				'host' => 'ZBX6648 Enabled Triggers Host',
				'groups' => ['groupid' => $groupids['ZBX6648 Enabled Triggers']],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'zbx6648 item enabled',
						'key_' => 'zbx6648-item-enabled',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		// Create host triggers.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'zbx6648 trigger disabled',
				'expression' => 'last(/ZBX6648 Disabled Triggers Host/zbx6648-item-for-disabled-trigger)=0',
				'status' => TRIGGER_STATUS_DISABLED
			],
			[
				'description' => 'zbx6648 item disabled for enabled trigger',
				'expression' => 'last(/ZBX6648 Disabled Triggers Host/zbx6648-item-disabled)=0'
			],
			[
				'description' => 'zbx6648 trigger enabled',
				'expression' => 'last(/ZBX6648 Enabled Triggers Host/zbx6648-item-enabled)=0'
			],
			[
				'description' => 'zbx6648 trigger all enabled',
				'expression' => 'last(/ZBX6648 All Triggers Host/zbx6648-item-all)=0'
			],
			[
				'description' => 'zbx6648 trigger all disabled',
				'expression' => 'last(/ZBX6648 All Triggers Host/zbx6648-item-all)=0',
				'status' => TRIGGER_STATUS_DISABLED
			],
			[
				'description' => 'zbx6648 trigger all disabled item',
				'expression' => 'last(/ZBX6648 All Triggers Host/zbx6648-item-all-disabled)=0'
			]
		]);
	}

	public static function getTriggerData() {
		return [
			[
				[
					'hostgroup' => 'ZBX6648 All Triggers',
					'host' => 'ZBX6648 All Triggers Host',
					'triggers' => ['zbx6648 trigger all enabled'],
					'overlay' => 'triggers'
				]
			],
			[
				[
					'hostgroup' => 'ZBX6648 Enabled Triggers',
					'host' => 'ZBX6648 Enabled Triggers Host',
					'triggers' => ['zbx6648 trigger enabled'],
					'overlay' => 'triggers'
				]
			],
			// Host is not visible because there are no triggers or trigger is disabled.
			[
				[
					'hostgroup' => 'Zabbix servers',
					'host' => ['ZBX6648 No Triggers Host', 'ZBX6648 Disabled Triggers Host'],
					'overlay' => 'hosts'
				]
			],
			// Trigger is disabbled on the host and there are no hosts in the group.
			[
				[
					'hostgroup' => ['ZBX6648 Disabled Triggers', 'ZBX6648 Group No Hosts'],
					'overlay' => 'host groups'
				]
			]
		];
	}

	/**
	 * Check that disabled triggers and host without triggers are not visible in the Triggers field overlay dialogs.
	 *
	 * @dataProvider getTriggerData
	 */
	public function testMultiselectsProblems_TriggerDialogs($data) {
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

		$this->page->login()->open('zabbix.php?action=problem.view')->waitUntilReady();
		$filter_form = CFilterElement::find()->one()->getForm();
		$trigger_overlay = $filter_form->getField('Triggers')->edit();
		$this->assertEquals('Triggers', $trigger_overlay->getTitle());

		switch ($data['overlay']) {
			case 'triggers':
				$trigger_overlay->setDataContext([
					'values' => $data['host'],
					'context' => $data['hostgroup']
				]);
				$this->assertEquals('Triggers', $trigger_overlay->getTitle());
				$this->assertTrue($trigger_overlay->query('link', $data['triggers'])->exists());
				$this->assertEquals(count($data['triggers']), $trigger_overlay->asTable()->getRows()->count());
				break;
			case 'hosts':
				$host_overlay = $trigger_overlay->asForm(['normalized' => true])->getField('Host')->edit();
				$host_overlay->setDataContext($data['hostgroup']);
				$this->assertEquals('Hosts', $host_overlay->getTitle());
				$this->assertFalse($host_overlay->query('link', $data['host'])->exists());
				break;
			case 'host groups':
				$host_overlay = $trigger_overlay->asForm(['normalized' => true])->getField('Host')->edit();
				$group_overlay = $host_overlay->asForm(['normalized' => true])->getField('Host group')->edit();
				$this->assertEquals('Host groups', $group_overlay->getTitle());
				$this->assertFalse($group_overlay->query('link', $data['hostgroup'])->exists());
				break;
		}
	}
}

