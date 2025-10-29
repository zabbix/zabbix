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
 * Test for assuring that bug from ZBX-23302 is not reproducing, respectively
 * that multiselects' dialogs do not contain any errors before and after filling.
 *
 * @onBefore prepareProxyData
 *
 * @backup hosts
 */
class testMultiselectsErrorsHostsTemplates extends testMultiselectDialogs {

	const HOST = 'Template inheritance test host';
	const TEMPLATE = 'AIX by Zabbix agent';

	public static function prepareProxyData() {
		CDataHelper::call('proxy.create',
			[
				[
					'name' => 'Proxy for Multiselects test',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				]
			]
		);
	}

	public static function getCheckDialogsData() {
		return [
			// #0.
			[
				[
					'object' => 'Hosts',
					'checked_multiselects' => [
						['Host groups' => ['title' => 'Host groups']],
						['Templates' => ['title' => 'Templates'], 'Template group' => ['title' =>'Template groups']],
						['Proxies' => ['title' => 'Proxies']]
					],
					// Fill this filter to enable 'Proxy' multiselect.
					'filter' => ['Monitored by' => 'Proxy'],
					'filled_multiselects' => [
						['Proxy' => 'Proxy for Multiselects test']
					]
				]
			],
			// #1.
			[
				[
					'object' => 'Hosts',
					'sub_object' => 'Items' ,
					'checked_multiselects' => [
						['Value mapping' => ['title' => 'Value mapping']]
					],
					'filled_multiselects' => [
						['Value mapping' => 'Template value mapping']
					]
				]
			],
			// #2.
			[
				[
					'object' => 'Hosts',
					'sub_object' => 'Triggers'
				]
			],
			// #3.
			[
				[
					'object' => 'Hosts',
					'sub_object' => 'Graphs'
				]
			],
			// #4.
			[
				[
					'object' => 'Hosts',
					'sub_object' => 'Discovery'
				]
			],
			// #5.
			[
				[
					'object' => 'Hosts',
					'sub_object' => 'Web'
				]
			],
			// #6.
			[
				[
					'object' => 'Templates',
					'checked_multiselects' => [
						['Template groups' => ['title' => 'Template groups']],
						['Linked templates' => ['title' => 'Templates'], 'Template group' => ['title' => 'Template groups']]
					]
				]
			],
			// #7.
			[
				[
					'object' => 'Templates',
					'sub_object' => 'Items' ,
					'checked_multiselects' => [
						['Value mapping' => ['title' => 'Value mapping']]
					],
					'filled_multiselects' => [
						['Value mapping' => 'Zabbix agent ping status']
					]
				]
			],
			// #8.
			[
				[
					'object' => 'Templates',
					'sub_object' => 'Triggers'
				]
			],
			// #9.
			[
				[
					'object' => 'Templates',
					'sub_object' => 'Graphs'
				]
			],
			// #10.
			[
				[
					'object' => 'Templates',
					'sub_object' => 'Discovery'
				]
			],
			// #11.
			[
				[
					'object' => 'Templates',
					'sub_object' => 'Web'
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckDialogsData
	 */
	public function testMultiselectsErrorsHostsTemplates_CheckDialogs($data) {
		$this->page->login()->open(($data['object'] === 'Hosts') ? 'zabbix.php?action=host.list' : 'zabbix.php?action=template.list');

		if (array_key_exists('sub_object', $data)) {
			$this->query('class:list-table')->asTable()->waitUntilPresent()->one()
					->findRow('Name', ($data['object'] === 'Hosts') ? self::HOST : self::TEMPLATE)
					->getColumn($data['sub_object'])->query('tag:a')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();

			// Add common multiselect fields to data provider.
			$common_multiselects = ($data['object'] === 'Hosts')
				? [
					['Host groups' => ['title' => 'Host groups']],
					['Hosts' => ['title' => 'Hosts'], 'Host group' => ['title' => 'Host groups']]
				]
				: [
					['Template groups' => ['title' => 'Template groups']],
					['Templates' => ['title' => 'Templates'], 'Template group' => ['title' => 'Template groups']]
				];

			$data['checked_multiselects'] = array_merge($common_multiselects,
					CTestArrayHelper::get($data, 'checked_multiselects', [])
			);

			$common_fields = ($data['object'] === 'Hosts')
				? [['Host groups' => 'Zabbix servers'], ['Hosts' => 'ЗАББИКС Сервер']]
				: [['Template groups' => 'Templates'], ['Templates' => 'Zabbix agent']];
		}
		else {
			$common_fields = ($data['object'] === 'Hosts')
				? [['Host groups' => 'Zabbix servers'], ['Templates' => 'Zabbix agent']]
				: [['Template groups' => 'Templates'], ['Linked templates' => 'Zabbix agent']];
		}

		$filter_form = $this->query('name:zbx_filter')->asForm()->one();

		// Fill filter to enable dependent multiselects.
		if (array_key_exists('filter', $data)) {
			$filter_form->fill($data['filter']);
		}

		// Check all multiselects in filter before one of them is filled.
		$this->checkMultiselectDialogs($filter_form, $data['checked_multiselects']);

		// Fill multiselects one by one and check other multiselects after that.
		$fields = array_merge($common_fields, CTestArrayHelper::get($data, 'filled_multiselects', []));

		foreach ($fields as $field) {
			// Fill filter to enable dependent multiselects.
			if (array_key_exists('filter', $data)) {
				$filter_form->fill($data['filter']);
			}

			$filter_form->fill($field);

			// Check all multiselects in filter after one multiselect is filled.
			$this->checkMultiselectDialogs($filter_form, $data['checked_multiselects']);
			$filter_form->query('button:Reset')->waitUntilClickable()->one()->click();
		}
	}
}
