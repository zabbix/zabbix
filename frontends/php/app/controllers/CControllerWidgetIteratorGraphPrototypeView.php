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
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid',
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		if ($fields['source_type'] === ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE) {
			$return = $this->doGraphPrototype();
		} elseif ($fields['source_type'] === ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE) {
			$return = $this->doSimpleGraphPrototype();
		} else {
			error(_('Page received incorrect data'));
		}

		if (($messages = getMessages()) !== null) {
			$return = ['messages' => $messages->toString()];
		}

		echo (new CJson())->encode($return);
	}

	protected function doGraphPrototype() {
		$fields = $this->getForm()->getFieldsData();

		$options = [
			'output' => ['graphid', 'name'],
			'selectHosts' => ['name'],
			'selectDiscoveryRule' => ['hostid']
		];

		$dynamic_hostid = $this->getInput('dynamic_hostid', 0);

		if ($fields['dynamic'] && $dynamic_hostid) {
			// The key of the actual graph prototype selected on widget's edit form.
			$graph_prototype = API::GraphPrototype()->get([
				'output' => ['name'],
				'graphids' => [$fields['graphid']]
			]);
			$graph_prototype = reset($graph_prototype);

			// Analog graph prototype for the selected dynamic host.
			$options['hostids'] = [$dynamic_hostid];
			$options['filter'] = ['name' => $graph_prototype['name']];
		}
		else {
			// Just fetch the item prototype selected on widget's edit form.
			$options['graphids'] = [$fields['graphid']];
		}

		// Use this graph prototype as base for collecting created graphs.
		$graph_prototype = API::GraphPrototype()->get($options);
		$graph_prototype = reset($graph_prototype);

		$graphs_collected = [];

		if ($graph_prototype) {
			$graphs_created_all = API::Graph()->get([
				'output' => ['graphid', 'name'],
				'hostids' => [$graph_prototype['discoveryRule']['hostid']],
				'selectGraphDiscovery' => ['graphid', 'parent_graphid'],
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
				'expandName' => true
			]);

			// Collect graphs based on the graph prototype.
			foreach ($graphs_created_all as $graph) {
				if ($graph['graphDiscovery']['parent_graphid'] === $graph_prototype['graphid']) {
					$graphs_collected[$graph['graphid']] = $graph['name'];
				}
			}
			natsort($graphs_collected);
		}

		$widgets_of_iterator = [];

		foreach ($graphs_collected as $graphid => $name) {
			$widgets_of_iterator[] = [
				'widgetid' => (string) $graphid,
				'type' => 'graph',
				'header' => $name,
				'fields' => [
					'source_type' => ZBX_WIDGET_FIELD_RESOURCE_GRAPH,
					'graphid' => $graphid,
				]
			];
		}

		return [
			'header' =>
				$this->getInput('name', $graph_prototype['hosts'][0]['name'].NAME_DELIMITER.$graph_prototype['name']),

			'widgets_of_iterator' => $widgets_of_iterator
		];
	}

	protected function doSimpleGraphPrototype() {
		$fields = $this->getForm()->getFieldsData();

		$options = [
			'output' => ['itemid', 'name'],
			'selectHosts' => ['name'],
			'selectDiscoveryRule' => ['hostid']
		];

		$dynamic_hostid = $this->getInput('dynamic_hostid', 0);

		if ($fields['dynamic'] && $dynamic_hostid) {
			// The key of the actual item prototype selected on widget's edit form.
			$item_prototype = API::ItemPrototype()->get([
				'output' => ['key_'],
				'itemids' => [$fields['itemid']]
			]);
			$item_prototype = reset($item_prototype);

			// Analog item prototype for the selected dynamic host.
			$options['hostids'] = [$dynamic_hostid];
			$options['filter'] = ['key_' => $item_prototype['key_']];
		}
		else {
			// Just fetch the item prototype selected on widget's edit form.
			$options['itemids'] = [$fields['itemid']];
		}

		// Use this item prototype as base for collecting created items.
		$item_prototype = API::ItemPrototype()->get($options);
		$item_prototype = reset($item_prototype);

		$items_collected = [];

		if ($item_prototype) {
			$items_created_all = API::Item()->get([
				'output' => ['itemid', 'name', 'key_', 'hostid'],
				'hostids' => [$item_prototype['discoveryRule']['hostid']],
				'selectItemDiscovery' => ['itemid', 'parent_itemid'],
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED]
			]);

			// Collect items based on the item prototype.
			$items_created = [];
			foreach ($items_created_all as $item) {
				if ($item['itemDiscovery']['parent_itemid'] === $item_prototype['itemid']) {
					$items_created[] = $item;
				}
			}
			foreach (CMacrosResolverHelper::resolveItemNames($items_created) as $item) {
				$items_collected[$item['itemid']] = $item['name_expanded'];
			}
			natsort($items_collected);
		}

		$widgets_of_iterator = [];

		foreach ($items_collected as $itemid => $name) {
			$widgets_of_iterator[] = [
				'widgetid' => (string) $itemid,
				'type' => 'graph',
				'header' => $name,
				'fields' => [
					'source_type' => ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH,
					'itemid' => $itemid,
				]
			];
		}

		return [
			'header' =>
				$this->getInput('name', $item_prototype['hosts'][0]['name'].NAME_DELIMITER.$item_prototype['name']),

			'widgets_of_iterator' => $widgets_of_iterator
		];
	}
}
