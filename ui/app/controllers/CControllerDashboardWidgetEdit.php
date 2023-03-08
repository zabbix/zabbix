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


use Zabbix\Core\{
	CModule,
	CWidget
};

use Zabbix\Widgets\CWidgetForm;

use Zabbix\Widgets\Fields\{
	CWidgetFieldMultiSelectAction,
	CWidgetFieldMultiSelectGraph,
	CWidgetFieldMultiSelectGraphPrototype,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectItem,
	CWidgetFieldMultiSelectItemPrototype,
	CWidgetFieldMultiSelectMediaType,
	CWidgetFieldMultiSelectService,
	CWidgetFieldMultiSelectSla,
	CWidgetFieldMultiSelectUser,
	CWidgetFieldSelectResource};

class CControllerDashboardWidgetEdit extends CController {

	private ?CWidget $widget;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'type' =>						'string|required',
			'fields' =>						'array',
			'templateid' =>					'db dashboard.templateid',
			'name' =>						'string',
			'view_mode' =>					'in '.implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]),
			'unique_id' =>					'string',
			'dashboard_page_unique_id' =>	'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/** @var CWidget $widget */
			$widget = APP::ModuleManager()->getModule($this->getInput('type'));

			if ($widget !== null && $widget->getType() === CModule::TYPE_WIDGET) {
				$this->widget = $widget;
			}
			else {
				error(_('Inaccessible widget type.'));

				$ret = false;
			}
		}

		if ($ret && $this->hasInput('templateid') && !$this->widget->hasTemplateSupport()) {
			error(_('Widget type is not supported in this context.'));

			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'header' => $this->hasInput('unique_id') ? _('Edit widget') : _('Add widget'),
						'error' => [
							'messages' => array_column(get_and_clear_messages(), 'message')
						]
					], JSON_THROW_ON_ERROR)
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->hasInput('templateid')
			? ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN)
			: ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction(): void {
		$known_types = [];
		$deprecated_types = [];

		/** @var CWidget $widget */
		foreach (APP::ModuleManager()->getWidgets($this->hasInput('templateid')) as $widget) {
			if (!$widget->isDeprecated()) {
				$known_types[$widget->getId()] = $widget->getDefaultName();
			}
			else {
				$deprecated_types[$widget->getId()] = $widget->getDefaultName();
			}
		}

		natcasesort($known_types);
		natcasesort($deprecated_types);

		$form = $this->widget->getForm($this->getInput('fields', []),
			$this->hasInput('templateid') ? $this->getInput('templateid') : null
		);

		// Transforms corrupted data to default values.
		$form->validate();

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', ''),
			'type' => $this->getInput('type'),
			'known_types' => $known_types,
			'deprecated_types' => $deprecated_types,
			'fields' => $form->getFields(),
			'view_mode' => $this->getInput('view_mode', ZBX_WIDGET_VIEW_MODE_NORMAL),
			'unique_id' => $this->hasInput('unique_id') ? $this->getInput('unique_id') : null,
			'dashboard_page_unique_id' => $this->hasInput('dashboard_page_unique_id')
				? $this->getInput('dashboard_page_unique_id')
				: null,
			'captions' => $this->getCaptions($form),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Prepares mapped list of names for all required resources.
	 */
	private function getCaptions(CWidgetForm $form): array {
		$captions = ['simple' => [], 'ms' => []];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldSelectResource) {
				$resource_type = $field->getResourceType();
				$id = $field->getValue();

				if (!array_key_exists($resource_type, $captions['simple'])) {
					$captions['simple'][$resource_type] = [];
				}

				if ($id != 0 && $resource_type == CWidgetFieldSelectResource::RESOURCE_TYPE_SYSMAP) {
					$captions['simple'][$resource_type][$id] = _('Inaccessible map');
				}
			}
		}

		foreach ($captions['simple'] as $resource_type => &$list) {
			if (!$list) {
				continue;
			}

			if ($resource_type == CWidgetFieldSelectResource::RESOURCE_TYPE_SYSMAP) {
				$maps = API::Map()->get([
					'sysmapids' => array_keys($list),
					'output' => ['sysmapid', 'name']
				]);

				if ($maps) {
					foreach ($maps as $map) {
						$list[$map['sysmapid']] = $map['name'];
					}
				}
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
			'sla' => [],
			'user' => [],
			'action' => [],
			'media_type' => []
		];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldMultiSelectGroup) {
				$key = 'groups';
				$var = 'group';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectHost) {
				$key = 'hosts';
				$var = 'host';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectItem) {
				$key = 'items';
				$var = 'item';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectGraph) {
				$key = 'graphs';
				$var = 'graph';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectItemPrototype) {
				$key = 'item_prototypes';
				$var = 'prototype_item';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectGraphPrototype) {
				$key = 'graph_prototypes';
				$var = 'prototype_graph';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectService) {
				$key = 'services';
				$var = 'service';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectSla) {
				$key = 'slas';
				$var = 'sla';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectUser) {
				$key = 'users';
				$var = 'user';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectAction) {
				$key = 'actions';
				$var = 'action';
			}
			elseif ($field instanceof CWidgetFieldMultiSelectMediaType) {
				$key = 'media_types';
				$var = 'media_type';
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

		if ($ids['user']) {
			$db_users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_keys($ids['user']),
				'preservekeys' => true
			]);

			foreach ($db_users as $userid => $user) {
				foreach ($ids['user'][$userid] as $field_name) {
					$captions['ms']['users'][$field_name][$userid] += [
						'name' => getUserFullname($user)
					];
				}
			}
		}

		if ($ids['action']) {
			$db_actions = API::Action()->get([
				'output' => ['actionid', 'name'],
				'actionids' => array_keys($ids['action']),
				'preservekeys' => true
			]);

			foreach ($db_actions as $actionid => $action) {
				foreach ($ids['action'][$actionid] as $field_name) {
					$captions['ms']['actions'][$field_name][$actionid] += [
						'name' => $action['name']
					];
				}
			}
		}

		if ($ids['media_type']) {
			$db_media_types = API::MediaType()->get([
				'output' => ['mediatypeid', 'name'],
				'mediatypeids' => array_keys($ids['media_type']),
				'preservekeys' => true
			]);

			foreach ($db_media_types as $mediatypeid => $media_type) {
				foreach ($ids['media_type'][$mediatypeid] as $field_name) {
					$captions['ms']['media_types'][$field_name][$mediatypeid] += [
						'name' => $media_type['name']
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
			'slas' => _('Inaccessible SLA'),
			'users' => _('Inaccessible user'),
			'actions' => _('Inaccessible action'),
			'media_types' => _('Inaccessible media type')
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
