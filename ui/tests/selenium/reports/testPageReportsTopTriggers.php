<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup hosts
 *
 * @onBefore prepareData
 */
class testPageReportsTopTriggers extends CWebTest {

	use TableTrait;

	protected static $groupids;

	public function prepareData() {
		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'First Group for Reports->TOP 100 triggers check 🏦'],
			['name' => 'Second Group for Reports->TOP 100 triggers check']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts and trapper items for top 100 triggers data test.
		CDataHelper::createHosts([
			[
				'host' => 'Host for Reports - TOP 100 triggers filter checks',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.5.1',
						'dns' => '',
						'port' => '10077'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['First Group for Reports->TOP 100 triggers check 🏦']
				],
				'items' => [
					[
						'name' => 'Item for Top 100 triggers reports 🛒',
						'key_' => 'topreports',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host for Reports - TOP 100 triggers filter checks 2',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10078'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['Second Group for Reports->TOP 100 triggers check']
				],
				'items' => [
					[
						'name' => 'Item for Top 100 triggers reports2',
						'key_' => 'topreports2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		CDataHelper::call('trigger.create', [
			[
				'description' => 'Problem Disaster',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks/topreports)=5',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Severity status: High',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks 2/topreports2)=4',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Problem Warning',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks/topreports)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Severity status: Warning⚠️',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks/topreports)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Problem with tag',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks/topreports)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING,
				'tags' => [
					[
						'tag' => 'test1',
						'value' => 'tag1'
					]
				]
			]
		]);
	}

	public function testPageReportsTopTriggers_FilterLayout() {
		$link = 'zabbix.php?action=toptriggers.list';
		$this->page->login()->open($link)->waitUntilReady();
		$this->page->assertTitle('Top 100 triggers');
		$this->page->assertHeader('Top 100 triggers');

		$filter = CFilterElement::find()->one();
		$this->assertEquals('Last 1 hour', $filter->getSelectedTabName());
		$this->assertEquals('Last 1 hour', $filter->query('link', 'Last 1 hour')->one()->getText());
		$this->assertTrue($filter->isExpanded());

		foreach ([false, true] as $state) {
			$filter->expand($state);
			// Leave the page and reopen the previous page to make sure the filter state is still saved..
			$this->page->open('zabbix.php?action=report.status')->waitUntilReady();
			$this->page->open($link)->waitUntilReady();
			$this->assertTrue($filter->isExpanded($state));
		}

		$filter->selectTab('Filter');
		$this->assertEquals('Filter', $filter->getSelectedTabName());

		// Check buttons on the Event correlation page.
		$this->assertEquals(4, $this->query('button', ['Apply', 'Reset', 'Add', 'Remove'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		$filter_form = $filter->getForm();
		$this->assertEquals(['Host groups', 'Hosts', 'Problem', 'Severity', 'Problem tags'],
				$filter_form->getLabels()->asText()
		);

		$default_state = [
			'Host groups' => '',
			'Hosts' => '',
			'Problem' => '',
			'id:filter_severities_0' => false,
			'id:filter_severities_1' => false,
			'id:filter_severities_2' => false,
			'id:filter_severities_3' => false,
			'id:filter_severities_4' => false,
			'id:filter_severities_5' => false,
			'id:filter_evaltype_0' => 'And/Or',
			'id:filter_tags_0_tag' => '',
			'id:filter_tags_0_operator' => 'Contains',
			'id:filter_tags_0_value' => ''
		];
		$filter_form->checkValue($default_state);

		// Check attributes of input elements.
		$inputs = [
			'id:filter_groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:filter_hostids__ms' => [
				'placeholder' => 'type here to search'
			],
			'Problem' => [
				'maxlength' => 255
			],
			'id:filter_tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:filter_tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $filter_form->getField($field)->getAttribute($attribute));
			}
		}

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['Host', 'Trigger', 'Severity', 'Number of problems'], $table->getHeadersText());
	}
}
