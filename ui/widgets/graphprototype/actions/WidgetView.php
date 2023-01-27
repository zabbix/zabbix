<?php declare(strict_types = 0);
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


namespace Widgets\GraphPrototype\Actions;

use API,
	APP,
	CControllerResponseData,
	CControllerWidgetIterator,
	CTableInfo;

use Zabbix\Core\CWidget;

class WidgetView extends CControllerWidgetIterator {

	protected const GRAPH_WIDGET_ID = 'graph';

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'view_mode' => 'in '.implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]),
			'dynamic_hostid' => 'db hosts.hostid'
		]);
	}

	protected function doAction(): void {
		if ($this->fields_values['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE) {
			$data = $this->doGraphPrototype();
		}
		elseif ($this->fields_values['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE) {
			$data = $this->doSimpleGraphPrototype();
		}
		else {
			error(_('Page received incorrect data'));
		}

		if ($messages = get_and_clear_messages()) {
			$data['error']['messages'] = array_column($messages, 'message');
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data, JSON_THROW_ON_ERROR)]));
	}

	/**
	 * Get graph prototype widget data for graph prototype source.
	 *
	 * @return array  Dashboard response data
	 */
	protected function doGraphPrototype(): array {
		$options = [
			'output' => ['graphid', 'name'],
			'selectHosts' => ['hostid', 'name'],
			'selectDiscoveryRule' => ['hostid']
		];

		$is_template_dashboard = $this->hasInput('templateid');
		$is_dynamic_item = ($is_template_dashboard || $this->fields_values['dynamic'] == CWidget::DYNAMIC_ITEM);

		$dynamic_hostid = $this->getInput('dynamic_hostid', 0);

		if ($is_dynamic_item && $dynamic_hostid != 0) {
			// The key of the actual graph prototype selected on widget's edit form.
			$graph_prototype = API::GraphPrototype()->get([
				'output' => ['name'],
				'graphids' => reset($this->fields_values['graphid'])
			]);
			if ($graph_prototype) {
				$graph_prototype = reset($graph_prototype);
			}
			else {
				return $this->inaccessibleError();
			}

			// Analog graph prototype for the selected dynamic host.
			$options['hostids'] = [$dynamic_hostid];
			$options['filter'] = ['name' => $graph_prototype['name']];
		}
		else {
			// Just fetch the item prototype selected on widget's edit form.
			$options['graphids'] = reset($this->fields_values['graphid']);
		}

		// Use this graph prototype as base for collecting created graphs.
		$graph_prototype = API::GraphPrototype()->get($options);
		if ($graph_prototype) {
			$graph_prototype = reset($graph_prototype);
		}
		else {
			return $this->inaccessibleError();
		}

		$graphs_collected = [];

		// Do not collect graphs while editing a template dashboard.
		if (!$is_template_dashboard || $this->hasInput('dynamic_hostid')) {
			$graphs_created_all = API::Graph()->get([
				'output' => ['graphid', 'name'],
				'hostids' => [$graph_prototype['discoveryRule']['hostid']],
				'selectGraphDiscovery' => ['graphid', 'parent_graphid'],
				'selectHosts' => ['name'],
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_CREATED],
				'expandName' => true
			]);

			// Collect graphs based on the graph prototype.
			foreach ($graphs_created_all as $graph) {
				if ($graph['graphDiscovery']['parent_graphid'] === $graph_prototype['graphid']) {
					$prepend_host_name = $is_template_dashboard
						? false
						: count($graph['hosts']) == 1 || ($is_dynamic_item && $dynamic_hostid != 0);

					$graphs_collected[$graph['graphid']] = $prepend_host_name
						? $graph['hosts'][0]['name'].NAME_DELIMITER.$graph['name']
						: $graph['name'];
				}
			}
			natsort($graphs_collected);
		}

		$page = $this->getIteratorPage(count($graphs_collected));
		$page_count = $this->getIteratorPageCount(count($graphs_collected));

		$graphs_collected = array_slice(
			$graphs_collected, $this->getIteratorPageSize() * ($page - 1), $this->getIteratorPageSize(), true
		);

		$children = [];

		$widget = APP::ModuleManager()->getModule(self::GRAPH_WIDGET_ID);

		if ($widget !== null) {
			foreach ($graphs_collected as $graphid => $name) {
				$child_fields = [
					'source_type' => ZBX_WIDGET_FIELD_RESOURCE_GRAPH,
					'graphid' => $graphid,
					'show_legend' => $this->fields_values['show_legend']
				];

				$children[] = [
					'widgetid' => (string) $graphid,
					'type' => self::GRAPH_WIDGET_ID,
					'name' => $name,
					'fields' => $child_fields,
					'defaults' => $widget->getDefaults()
				];
			}
		}

		if ($this->hasInput('name')) {
			$widget_name = $this->getInput('name');
		}
		else {
			$host_names = array_column($graph_prototype['hosts'], 'name', 'hostid');
			$host_name = $host_names[$graph_prototype['discoveryRule']['hostid']];

			$widget_name = $is_template_dashboard
				? $graph_prototype['name']
				: $host_name.NAME_DELIMITER.$graph_prototype['name'];
		}

		return [
			'name' => $widget_name,
			'children' => $children,
			'page' => $page,
			'page_count' => $page_count
		];
	}

	/**
	 * Get graph prototype widget data for simple graph prototype source.
	 */
	protected function doSimpleGraphPrototype(): array {
		$options = [
			'output' => ['itemid', 'name'],
			'selectHosts' => ['name'],
			'selectDiscoveryRule' => ['hostid']
		];

		$is_template_dashboard = $this->hasInput('templateid');
		$is_dynamic_item = ($is_template_dashboard || $this->fields_values['dynamic'] == CWidget::DYNAMIC_ITEM);

		$dynamic_hostid = $this->getInput('dynamic_hostid', 0);

		if ($is_dynamic_item && $dynamic_hostid != 0) {
			// The key of the actual item prototype selected on widget's edit form.
			$item_prototype = API::ItemPrototype()->get([
				'output' => ['key_'],
				'itemids' => reset($this->fields_values['itemid'])
			]);
			if ($item_prototype) {
				$item_prototype = reset($item_prototype);
			}
			else {
				return $this->inaccessibleError();
			}

			// Analog item prototype for the selected dynamic host.
			$options['hostids'] = [$dynamic_hostid];
			$options['filter'] = ['key_' => $item_prototype['key_']];
		}
		else {
			// Just fetch the item prototype selected on widget's edit form.
			$options['itemids'] = reset($this->fields_values['itemid']);
		}

		// Use this item prototype as base for collecting created items.
		$item_prototype = API::ItemPrototype()->get($options);
		if ($item_prototype) {
			$item_prototype = reset($item_prototype);
		}
		else {
			return $this->inaccessibleError();
		}

		$items_collected = [];

		// Do not collect items while editing a template dashboard.
		if (!$is_template_dashboard || $this->hasInput('dynamic_hostid')) {
			$items_created_all = API::Item()->get([
				'output' => ['itemid', 'name'],
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
			foreach ($items_created as $item) {
				$items_collected[$item['itemid']] = $is_template_dashboard
					? $item['name']
					: $item_prototype['hosts'][0]['name'].NAME_DELIMITER.$item['name'];
			}
			natsort($items_collected);
		}

		$page = $this->getIteratorPage(count($items_collected));
		$page_count = $this->getIteratorPageCount(count($items_collected));

		$items_collected = array_slice(
			$items_collected, $this->getIteratorPageSize() * ($page - 1), $this->getIteratorPageSize(), true
		);

		$children = [];

		$widget = APP::ModuleManager()->getModule(self::GRAPH_WIDGET_ID);

		if ($widget !== null) {
			foreach ($items_collected as $itemid => $name) {
				$child_fields = [
					'source_type' => ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH,
					'itemid' => $itemid,
					'show_legend' => $this->fields_values['show_legend']
				];

				$children[] = [
					'widgetid' => (string) $itemid,
					'type' => self::GRAPH_WIDGET_ID,
					'name' => $name,
					'fields' => $child_fields,
					'defaults' => $widget->getDefaults()
				];
			}
		}

		if ($this->hasInput('name')) {
			$widget_name = $this->getInput('name');
		}
		else {
			$widget_name = $is_template_dashboard
				? $item_prototype['name']
				: $item_prototype['hosts'][0]['name'].NAME_DELIMITER.$item_prototype['name'];
		}

		return [
			'name' => $widget_name,
			'children' => $children,
			'page' => $page,
			'page_count' => $page_count
		];
	}

	/**
	 * Get graph prototype widget data for no permission's error.
	 */
	protected function inaccessibleError(): array {
		return [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'body' => (new CTableInfo())
				->setNoDataMessage(_('No permissions to referred object or it does not exist!'))
				->toString()
		];
	}
}
