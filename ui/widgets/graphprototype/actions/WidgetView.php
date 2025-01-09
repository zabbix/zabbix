<?php declare(strict_types = 0);
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


namespace Widgets\GraphPrototype\Actions;

use API,
	APP,
	CControllerResponseData,
	CControllerWidgetIterator,
	CTableInfo,
	CWidgetsData;

use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\Fields\CWidgetFieldReference;

class WidgetView extends CControllerWidgetIterator {

	protected const GRAPH_WIDGET_ID = 'graph';

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'view_mode' => 'in '.implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]),
			'has_custom_time_period' => 'in 1'
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

		if ($this->fields_values['override_hostid']) {
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

			// Analog graph prototype for the overridden host.
			$options['hostids'] = $this->fields_values['override_hostid'];
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
		if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
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
					$prepend_host_name = $this->isTemplateDashboard()
						? false
						: count($graph['hosts']) == 1 || $this->fields_values['override_hostid'];

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
			$widget_defaults = $widget->getDefaults();

			foreach ($graphs_collected as $graphid => $name) {
				$child_fields = [
					'source_type' => ZBX_WIDGET_FIELD_RESOURCE_GRAPH,
					'graphid' => $graphid,
					'time_period' => [
						CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
							$this->fields_values[CWidgetFieldReference::FIELD_NAME],
							CWidgetsData::DATA_TYPE_TIME_PERIOD
						)
					],
					'show_legend' => $this->fields_values['show_legend']
				];

				$child_form = $widget->getForm($child_fields, null);

				$child_form->validate(false);

				$children[] = [
					'widgetid' => (string) $graphid,
					'type' => self::GRAPH_WIDGET_ID,
					'name' => $name,
					'fields' => $child_form->getFieldsValues(),
					'defaults' => $widget_defaults
				];
			}
		}

		if ($this->hasInput('name')) {
			$widget_name = $this->getInput('name');
		}
		else {
			$host_names = array_column($graph_prototype['hosts'], 'name', 'hostid');
			$host_name = $host_names[$graph_prototype['discoveryRule']['hostid']];

			$widget_name = $this->isTemplateDashboard()
				? $graph_prototype['name']
				: $host_name.NAME_DELIMITER.$graph_prototype['name'];
		}

		return [
			'name' => $widget_name,
			'info' => $this->makeWidgetInfo(),
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

		if ($this->fields_values['override_hostid']) {
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

			// Analog item prototype for the overridden host.
			$options['hostids'] = $this->fields_values['override_hostid'];
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
		if (!$this->isTemplateDashboard() || $this->fields_values['override_hostid']) {
			$items_created_all = API::Item()->get([
				'output' => ['itemid', 'name_resolved'],
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
				$items_collected[$item['itemid']] = $this->isTemplateDashboard()
					? $item['name_resolved']
					: $item_prototype['hosts'][0]['name'].NAME_DELIMITER.$item['name_resolved'];
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
			$widget_defaults = $widget->getDefaults();

			foreach ($items_collected as $itemid => $name) {
				$child_fields = [
					'source_type' => ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH,
					'itemid' => $itemid,
					'time_period' => [
						CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
							$this->fields_values[CWidgetFieldReference::FIELD_NAME],
							CWidgetsData::DATA_TYPE_TIME_PERIOD
						)
					],
					'show_legend' => $this->fields_values['show_legend']
				];

				$child_form = $widget->getForm($child_fields, null);

				$child_form->validate(false);

				$children[] = [
					'widgetid' => (string) $itemid,
					'type' => self::GRAPH_WIDGET_ID,
					'name' => $name,
					'fields' => $child_form->getFieldsValues(),
					'defaults' => $widget_defaults
				];
			}
		}

		if ($this->hasInput('name')) {
			$widget_name = $this->getInput('name');
		}
		else {
			$widget_name = $this->isTemplateDashboard()
				? $item_prototype['name']
				: $item_prototype['hosts'][0]['name'].NAME_DELIMITER.$item_prototype['name'];
		}

		return [
			'name' => $widget_name,
			'info' => $this->makeWidgetInfo(),
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

	/**
	 * Make widget specific info to show in widget's header.
	 */
	private function makeWidgetInfo(): array {
		$info = [];

		if ($this->hasInput('has_custom_time_period')) {
			$info[] = [
				'icon' => ZBX_ICON_TIME_PERIOD,
				'hint' => relativeDateToText($this->fields_values['time_period']['from'],
					$this->fields_values['time_period']['to']
				)
			];
		}

		return $info;
	}
}
