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


require_once dirname(__FILE__).'/../common/testMultiselectDialogs.php';

/**
 * Test for assuring that bug from ZBX-23302 is not reproducing.
 */
class testMultiselectsErrorsHostsTemplates extends testMultiselectDialogs {

	public static function getCheckDialogsData() {
		return [
			// #0.
			[
				[
					'object' => 'Hosts',
					'multiselects' => [
						['Host groups' => 'Host groups'],
						['Templates' => 'Templates', 'Template group' => 'Template groups'],
						['Proxy' => 'Proxies']
					],
					'filter' => ['Monitored by' => 'Proxy']
				]
			],
			// #1.
			[
				[
					'object' => 'Hosts',
					'sub_object' => 'Items' ,
					'multiselects' => [
						['Value mapping' => 'Value mapping']
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
					'multiselects' => [
						['Template groups' => 'Template groups'],
						['Linked templates' => 'Templates', 'Template group' => 'Template groups']
					]
				]
			],
			// #7.
			[
				[
					'object' => 'Templates',
					'sub_object' => 'Items' ,
					'multiselects' => [
						['Value mapping' => 'Value mapping']
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
					->findRow('Name', ($data['object'] === 'Hosts') ? 'Template inheritance test host' : 'AIX by Zabbix agent')
					->getColumn($data['sub_object'])->query('tag:a')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();

			// Add common multiselect fields to data provider.
			$common_fields = ($data['object'] === 'Hosts')
				? [['Host groups' => 'Host groups'], ['Hosts' => 'Hosts', 'Host group' => 'Host groups']]
				: [['Template groups' => 'Template groups'], ['Templates' => 'Templates', 'Template group' => 'Template groups']];

			$data['multiselects'] = array_merge(CTestArrayHelper::get($data, 'multiselects', []), $common_fields);
		}

		$filter_form = $this->query('name:zbx_filter')->asForm()->one();

		// Fill this filter to enable 'Proxy' multiselect.
		if (array_key_exists('filter', $data)) {
			$filter_form->fill($data['filter']);
		}

		// Check all multiselects in filter before the first multiselect is filled.
		$this->checkMultiselectDialogs($filter_form, $data['multiselects']);

		$fields = ($data['object'] === 'Hosts')
			? ['Host groups' => 'Zabbix servers']
			: ['Template groups' => 'Templates'];

		$filter_form->fill($fields);

		// Check all multiselects in filter after the first multiselect is filled.
		$this->checkMultiselectDialogs($filter_form, $data['multiselects']);

		$filter_form->query('button:Reset')->waitUntilClickable()->one()->click();
	}
}
