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


require_once __DIR__.'/../common/testMultiselectDialogs.php';

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
			['Host groups' => ['title' => 'Host groups']],
			['Hosts' => ['title' => 'Hosts'], 'Host group' => ['title' => 'Host groups']],
			['Triggers' => ['title' => 'Triggers'], 'Host' => ['title' => 'Hosts'], 'Host group' => ['title' => 'Host groups']]
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
			['name' => 'All Triggers'],
			['name' => 'Enabled Triggers'],
			['name' => 'Disabled Triggers'],
			['name' => 'Group No Hosts']
		]);
		$groupids = CDataHelper::getIds('name');

		CDataHelper::createHosts([
			// Host without triggers.
			[
				'host' => 'No Triggers Host',
				'groups' => ['groupid' => 4],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'no triggers',
						'key_' => 'no-triggers',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			// Host with one enabled trigger and one disabled trigger.
			[
				'host' => 'All Triggers Host',
				'groups' => ['groupid' => $groupids['All Triggers']],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'item all triggers',
						'key_' => 'item-all-triggers',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'item all triggers disabled',
						'key_' => 'item-all-triggers-disabled',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'status' => ITEM_STATUS_DISABLED
					]
				]
			],
			// Host with disabled trigger and with disabled item that belongs to enabled trigger.
			[
				'host' => 'Disabled Triggers Host',
				'groups' => [
					'groupid' => $groupids['Disabled Triggers'],
					'groupid' => 4
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'item for disabled trigger',
						'key_' => 'item-for-disabled-trigger',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'item disabled',
						'key_' => 'item-disabled',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'status' => ITEM_STATUS_DISABLED
					]
				]
			],
			// Host with one enabled trigger.
			[
				'host' => 'Enabled Triggers Host',
				'groups' => ['groupid' => $groupids['Enabled Triggers']],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'item enabled',
						'key_' => 'item-enabled',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		// Create host triggers.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'trigger disabled',
				'expression' => 'last(/Disabled Triggers Host/item-for-disabled-trigger)=0',
				'status' => TRIGGER_STATUS_DISABLED
			],
			[
				'description' => 'item disabled for enabled trigger',
				'expression' => 'last(/Disabled Triggers Host/item-disabled)=0'
			],
			[
				'description' => 'trigger enabled',
				'expression' => 'last(/Enabled Triggers Host/item-enabled)=0'
			],
			[
				'description' => 'trigger all enabled',
				'expression' => 'last(/All Triggers Host/item-all-triggers)=0'
			],
			[
				'description' => 'trigger all disabled',
				'expression' => 'last(/All Triggers Host/item-all-triggers)=0',
				'status' => TRIGGER_STATUS_DISABLED
			],
			[
				'description' => 'trigger all disabled item',
				'expression' => 'last(/All Triggers Host/item-all-triggers-disabled)=0'
			]
		]);
	}

	public static function getTriggerData() {
		return [
			[
				[
					'hostgroup' => 'All Triggers',
					'host' => 'All Triggers Host',
					'triggers' => ['trigger all enabled'],
					'overlay' => 'triggers'
				]
			],
			[
				[
					'hostgroup' => 'Enabled Triggers',
					'host' => 'Enabled Triggers Host',
					'triggers' => ['trigger enabled'],
					'overlay' => 'triggers'
				]
			],
			// Host is not visible because there are no triggers or trigger is disabled.
			[
				[
					'hostgroup' => 'Zabbix servers',
					'host' => ['No Triggers Host', 'Disabled Triggers Host'],
					'overlay' => 'hosts'
				]
			],
			// Trigger is disabled on the host and there are no hosts in the group.
			[
				[
					'hostgroup' => ['Disabled Triggers', 'Group No Hosts'],
					'overlay' => 'host groups'
				]
			]
		];
	}

	/**
	 * Check that disabled triggers and host without triggers are not visible in the Triggers field overlay dialogs.
	 * The original issue was firstly represented by ticket number ZBX-6648.
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

		COverlayDialogElement::closeAll(true);
	}
}

