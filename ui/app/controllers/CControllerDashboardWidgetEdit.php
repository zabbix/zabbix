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


class CControllerDashboardWidgetEdit extends CController {

	private $context;

	protected function checkInput() {
		$fields = [
			'templateid' =>					'db dashboard.templateid',
			'type' =>						'string',
			'name' =>						'string',
			'view_mode' =>					'in '.implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]),
			'prev_type' =>					'string',
			'fields' =>						'json',
			'unique_id' =>					'string',
			'dashboard_page_unique_id' =>	'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$this->context = $this->hasInput('templateid')
				? CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD
				: CWidgetConfig::CONTEXT_DASHBOARD;

			if ($this->hasInput('type')) {
				if (!CWidgetConfig::isWidgetTypeSupportedInContext($this->getInput('type'), $this->context)) {
					error(_('Widget type is not supported in this context.'));

					$ret = false;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->context === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD)
			? ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN)
			: ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$known_widget_types = CWidgetConfig::getKnownWidgetTypes($this->context);

		natsort($known_widget_types);

		if ($this->hasInput('type')) {
			$type = $this->getInput('type');

			if (!array_key_exists($type, $known_widget_types) || $this->getInput('prev_type', $type) !== $type) {
				CProfile::update('web.dashboard.last_widget_type', $type, PROFILE_TYPE_STR);
			}
		}
		else {
			$type = CProfile::get('web.dashboard.last_widget_type');
			if (!array_key_exists($type, $known_widget_types)) {
				$type = array_keys($known_widget_types)[0];
			}
		}

		$templateid = ($this->context === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD)
			? $this->getInput('templateid')
			: null;

		$form = CWidgetConfig::getForm($type, $this->getInput('fields', '{}'), $templateid);

		// Transforms corrupted data to default values.
		$form->validate();

		$this->setResponse(new CControllerResponseData([
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'dialogue' => [
				'type' => $type,
				'name' => $this->getInput('name', ''),
				'view_mode' => $this->getInput('view_mode', ZBX_WIDGET_VIEW_MODE_NORMAL),
				'fields' => $form->getFields()
			],
			'templateid' => $templateid,
			'unique_id' => $this->hasInput('unique_id') ? $this->getInput('unique_id') : null,
			'dashboard_page_unique_id' => $this->hasInput('dashboard_page_unique_id')
				? $this->getInput('dashboard_page_unique_id')
				: null,
			'known_widget_types' => $known_widget_types,
			'captions' => $this->getCaptions($form)
		]));
	}

	/**
	 * Prepares mapped list of names for all required resources.
	 *
	 * @param CWidgetForm $form
	 *
	 * @return array
	 */
	private function getCaptions($form) {
		$captions = ['simple' => [], 'ms' => []];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldSelectResource) {
				$resource_type = $field->getResourceType();
				$id = $field->getValue();

				if (!array_key_exists($resource_type, $captions['simple'])) {
					$captions['simple'][$resource_type] = [];
				}

				if ($id != 0) {
					switch ($resource_type) {
						case WIDGET_FIELD_SELECT_RES_SYSMAP:
							$captions['simple'][$resource_type][$id] = _('Inaccessible map');
							break;
					}
				}
			}
		}

		foreach ($captions['simple'] as $resource_type => &$list) {
			if (!$list) {
				continue;
			}

			switch ($resource_type) {
				case WIDGET_FIELD_SELECT_RES_SYSMAP:
					$maps = API::Map()->get([
						'sysmapids' => array_keys($list),
						'output' => ['sysmapid', 'name']
					]);

					if ($maps) {
						foreach ($maps as $map) {
							$list[$map['sysmapid']] = $map['name'];
						}
					}
					break;
			}
		}
		unset($list);

		// Prepare data for CMultiSelect controls.
		$ids = [
			'group' => [],
			'host' => [],
			'item' => [],
			'graph' => [],
			'prototype_item' => [],
			'prototype_graph' => [],
			'service' => [],
			'sla' => []
		];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldMsGroup) {
				$key = 'groups';
				$var = 'group';
			}
			elseif ($field instanceof CWidgetFieldMsHost) {
				$key = 'hosts';
				$var = 'host';
			}
			elseif ($field instanceof CWidgetFieldMsItem) {
				$key = 'items';
				$var = 'item';
			}
			elseif ($field instanceof CWidgetFieldMsGraph) {
				$key = 'graphs';
				$var = 'graph';
			}
			elseif ($field instanceof CWidgetFieldMsItemPrototype) {
				$key = 'item_prototypes';
				$var = 'prototype_item';
			}
			elseif ($field instanceof CWidgetFieldMsGraphPrototype) {
				$key = 'graph_prototypes';
				$var = 'prototype_graph';
			}
			elseif ($field instanceof CWidgetFieldMsService) {
				$key = 'services';
				$var = 'service';
			}
			elseif ($field instanceof CWidgetFieldMsSla) {
				$key = 'slas';
				$var = 'sla';
			}
			else {
				continue;
			}

			$field_name = $field->getName();
			$captions['ms'][$key][$field_name] = [];

			foreach ($field->getValue() as $id) {
				$captions['ms'][$key][$field_name][$id] = ['id' => $id];
				$ids[$var][$id][] = $field_name;
			}
		}

		if ($ids['group']) {
			$db_groups = API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => array_keys($ids['group']),
				'preservekeys' => true
			]);

			foreach ($db_groups as $groupid => $group) {
				foreach ($ids['group'][$groupid] as $field_name) {
					$captions['ms']['groups'][$field_name][$groupid]['name'] = $group['name'];
				}
			}
		}

		if ($ids['host']) {
			$db_hosts = API::Host()->get([
				'output' => ['name'],
				'hostids' => array_keys($ids['host']),
				'preservekeys' => true
			]);

			foreach ($db_hosts as $hostid => $host) {
				foreach ($ids['host'][$hostid] as $field_name) {
					$captions['ms']['hosts'][$field_name][$hostid]['name'] = $host['name'];
				}
			}
		}

		if ($ids['item']) {
			$db_items = API::Item()->get([
				'output' => ['name'],
				'selectHosts' => ['name'],
				'itemids' => array_keys($ids['item']),
				'webitems' => true,
				'preservekeys' => true
			]);

			foreach ($db_items as $itemid => $item) {
				foreach ($ids['item'][$itemid] as $field_name) {
					$captions['ms']['items'][$field_name][$itemid] += [
						'name' => $item['name'],
						'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER
					];
				}
			}
		}

		if ($ids['graph']) {
			$db_graphs = API::Graph()->get([
				'output' => ['graphid', 'name'],
				'selectHosts' => ['name'],
				'graphids' => array_keys($ids['graph']),
				'preservekeys' => true
			]);

			foreach ($db_graphs as $graphid => $graph) {
				foreach ($ids['graph'][$graphid] as $field_name) {
					$captions['ms']['graphs'][$field_name][$graphid] += [
						'name' => $graph['name'],
						'prefix' => $graph['hosts'][0]['name'].NAME_DELIMITER
					];
				}
			}
		}

		if ($ids['prototype_item']) {
			$db_item_prototypes = API::ItemPrototype()->get([
				'output' => ['name'],
				'selectHosts' => ['name'],
				'itemids' => array_keys($ids['prototype_item']),
				'preservekeys' => true
			]);

			foreach ($db_item_prototypes as $itemid => $item) {
				foreach ($ids['prototype_item'][$itemid] as $field_name) {
					$captions['ms']['item_prototypes'][$field_name][$itemid] += [
						'name' => $item['name'],
						'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER
					];
				}
			}
		}

		if ($ids['prototype_graph']) {
			$db_graph_prototypes = API::GraphPrototype()->get([
				'output' => ['graphid', 'name'],
				'selectHosts' => ['hostid', 'name'],
				'selectDiscoveryRule' => ['hostid'],
				'graphids' => array_keys($ids['prototype_graph']),
				'preservekeys' => true
			]);

			foreach ($db_graph_prototypes as $graphid => $graph) {
				$host_names = array_column($graph['hosts'], 'name', 'hostid');

				foreach ($ids['prototype_graph'][$graphid] as $field_name) {
					$captions['ms']['graph_prototypes'][$field_name][$graphid] += [
						'name' => $graph['name'],
						'prefix' => $host_names[$graph['discoveryRule']['hostid']].NAME_DELIMITER
					];
				}
			}
		}

		if ($ids['service']) {
			$db_services = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => array_keys($ids['service']),
				'preservekeys' => true
			]);

			foreach ($db_services as $serviceid => $service) {
				foreach ($ids['service'][$serviceid] as $field_name) {
					$captions['ms']['services'][$field_name][$serviceid] += [
						'name' => $service['name']
					];
				}
			}
		}

		if ($ids['sla']) {
			$db_slas = API::Sla()->get([
				'output' => ['slaid', 'name'],
				'slaids' => array_keys($ids['sla']),
				'preservekeys' => true
			]);

			foreach ($db_slas as $slaid => $sla) {
				foreach ($ids['sla'][$slaid] as $field_name) {
					$captions['ms']['slas'][$field_name][$slaid] += [
						'name' => $sla['name']
					];
				}
			}
		}

		$inaccessible_resources = [
			'groups' => _('Inaccessible group'),
			'hosts' => _('Inaccessible host'),
			'items' => _('Inaccessible item'),
			'graphs' => _('Inaccessible graph'),
			'item_prototypes' => _('Inaccessible item prototype'),
			'graph_prototypes' => _('Inaccessible graph prototype'),
			'services' => _('Inaccessible service'),
			'slas' => _('Inaccessible SLA')
		];

		foreach ($captions['ms'] as $resource_type => &$fields_captions) {
			foreach ($fields_captions as &$field_captions) {
				$n = 0;

				foreach ($field_captions as &$caption) {
					if (!array_key_exists('name', $caption)) {
						$postfix = (++$n > 1) ? ' ('.$n.')' : '';
						$caption['name'] = $inaccessible_resources[$resource_type].$postfix;
						$caption['inaccessible'] = true;
					}
				}
				unset($caption);
			}
			unset($field_captions);
		}
		unset($fields_captions);

		return $captions;
	}
}
