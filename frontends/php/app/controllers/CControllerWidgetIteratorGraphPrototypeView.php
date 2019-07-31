<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerWidgetIteratorGraphPrototypeView extends CControllerWidgetIterator {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_GRAPH_PROTOTYPE);
		$this->setValidationRules([
			'name' => 'string',
			'uniqueid' => 'required|string',
//			'widgetid' => 'required|string',
			'initial_load' => 'in 0,1',
			'edit_mode' => 'in 0,1',
			'dashboardid' => 'db dashboard.dashboardid',
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid',
			'dynamic_groupid' => 'db hosts.hostid',
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();



		$dynamic_widget_name = $this->getDefaultHeader();
		$same_host = true;







		$dynamic_hostid = $this->getInput('dynamic_hostid', 0);


		$created_items_resolved = [];

		$options = [
			'output' => ['itemid', 'name'],
			'selectHosts' => ['name'],
			'selectDiscoveryRule' => ['hostid']
		];

		if ($fields['dynamic'] && $dynamic_hostid) {
			$item_prototype = API::ItemPrototype()->get([
				'output' => ['key_'],
				'itemids' => [$fields['itemid']]
			]);
			$item_prototype = reset($item_prototype);

			$options['hostids'] = [$dynamic_hostid];
			$options['filter'] = ['key_' => $item_prototype['key_']];
		}
		else {
			$options['itemids'] = [$fields['itemid']];
		}

		$item_prototype = API::ItemPrototype()->get($options);
		$item_prototype = reset($item_prototype);

		if ($item_prototype) {
			// get all created (discovered) items for current host
			$allCreatedItems = API::Item()->get([
				'output' => ['itemid', 'name', 'key_', 'hostid'],
				'hostids' => [$item_prototype['discoveryRule']['hostid']],
				'selectItemDiscovery' => ['itemid', 'parent_itemid'],
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
			]);

			// collect those items where parent item is item prototype selected for this screen item as resource
			$created_items = [];
			foreach ($allCreatedItems as $item) {
				if ($item['itemDiscovery']['parent_itemid'] == $item_prototype['itemid']) {
					$created_items[] = $item;
				}
			}

			foreach (CMacrosResolverHelper::resolveItemNames($created_items) as $item) {
				$created_items_resolved[$item['itemid']] = $item['name_expanded'];
			}
			natsort($created_items_resolved);
		}



		$dynamic_widget_name = $item_prototype['hosts'][0]['name'].NAME_DELIMITER.$item_prototype['name'];





		$widgets = [];

		foreach ($created_items_resolved as $itemid => $name) {
			$widgets[] = [
				"widgetid" => (string) $itemid,
				"type" => "graph",
				"header" => $name,
				"scrollable" => false,
				"padding" => true,
				"fields" => [
					'source_type' => 1,
					'itemid' => $itemid,
				],
			];
		}

		$output = [
			'header' => $this->getInput('name', $dynamic_widget_name),
			'widgets_of_iterator' => $widgets,
		];

//		usleep(500000);

		echo (new CJson())->encode($output);
	}
}
