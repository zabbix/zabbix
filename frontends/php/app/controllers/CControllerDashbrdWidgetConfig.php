<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CControllerDashbrdWidgetConfig extends CController {

	protected function checkInput() {
		$fields = [
			'type' => 'in '.implode(',', array_keys(CWidgetConfig::getKnownWidgetTypes())),
			'name' => 'string',
			'fields' => 'json'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var string fields[<name>]  (optional)
			 */
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['body' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$known_widget_types = CWidgetConfig::getKnownWidgetTypes();
		natsort($known_widget_types);

		$type = $this->getInput('type', array_keys($known_widget_types)[0]);
		$form = CWidgetConfig::getForm($type, $this->getInput('fields', '{}'));

		$config = select_config();

		$this->setResponse(new CControllerResponseData([
			'config' => [
				'event_ack_enable' => $config['event_ack_enable'],
				'severity_name_0' => $config['severity_name_0'],
				'severity_name_1' => $config['severity_name_1'],
				'severity_name_2' => $config['severity_name_2'],
				'severity_name_3' => $config['severity_name_3'],
				'severity_name_4' => $config['severity_name_4'],
				'severity_name_5' => $config['severity_name_5']
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'dialogue' => [
				'type' => $type,
				'name' => $this->getInput('name', ''),
				'fields' => $form->getFields(),
			],
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

						case WIDGET_FIELD_SELECT_RES_GRAPH:
							$captions['simple'][$resource_type][$id] = _('Inaccessible graph');
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
						foreach ($maps as $key => $map) {
							$list[$map['sysmapid']] = $map['name'];
						}
					}
					break;

				case WIDGET_FIELD_SELECT_RES_GRAPH:
					$graphs = API::Graph()->get([
						'graphids' => array_keys($list),
						'selectHosts' => ['name'],
						'output' => ['graphid', 'name']
					]);

					if ($graphs) {
						foreach ($graphs as $key => $graph) {
							order_result($graph['hosts'], 'name');
							$graph['host'] = reset($graph['hosts']);
							$list[$graph['graphid']] = $graph['host']['name'].NAME_DELIMITER.$graph['name'];
						}
					}
					break;
			}
		}
		unset($list);

		// Prepare data for CMultiSelect controls.
		$groupids = [];
		$hostids = [];
		$itemids = [];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldGroup) {
				$field_name = $field->getName();
				$captions['ms']['groups'][$field_name] = [];

				foreach ($field->getValue() as $groupid) {
					$captions['ms']['groups'][$field_name][$groupid] = ['id' => $groupid];
					$groupids[$groupid][] = $field_name;
				}
			}
			elseif ($field instanceof CWidgetFieldHost) {
				$field_name = $field->getName();
				$captions['ms']['hosts'][$field_name] = [];

				foreach ($field->getValue() as $hostid) {
					$captions['ms']['hosts'][$field_name][$hostid] = ['id' => $hostid];
					$hostids[$hostid][] = $field_name;
				}
			}
			elseif ($field instanceof CWidgetFieldItem) {
				$field_name = $field->getName();
				$captions['ms']['items'][$field_name] = [];

				foreach ($field->getValue() as $itemid) {
					$captions['ms']['items'][$field_name][$itemid] = ['id' => $itemid];
					$itemids[$itemid][] = $field_name;
				}
			}
		}

		if ($groupids) {
			$groups = API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => array_keys($groupids),
				'preservekeys' => true
			]);

			foreach ($groups as $groupid => $group) {
				foreach ($groupids[$groupid] as $field_name) {
					$captions['ms']['groups'][$field_name][$groupid]['name'] = $group['name'];
					unset($captions['ms']['groups'][$field_name][$groupid]['inaccessible']);
				}
			}
		}

		if ($hostids) {
			$hosts = API::Host()->get([
				'output' => ['name'],
				'hostids' => array_keys($hostids),
				'preservekeys' => true
			]);

			foreach ($hosts as $hostid => $host) {
				foreach ($hostids[$hostid] as $field_name) {
					$captions['ms']['hosts'][$field_name][$hostid]['name'] = $host['name'];
				}
			}
		}

		if ($itemids) {
			$items = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_'],
				'selectHosts' => ['name'],
				'itemids' => array_keys($itemids),
				'preservekeys' => true
			]);

			$items = CMacrosResolverHelper::resolveItemNames($items);

			foreach ($items as $itemid => $item) {
				foreach ($itemids[$itemid] as $field_name) {
					$captions['ms']['items'][$field_name][$itemid] = [
						'id' => $itemid,
						'name' => $item['name_expanded'],
						'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER
					];
				}
			}
		}

		$inaccessible_resources = [
			'groups' => _('Inaccessible group'),
			'hosts' => _('Inaccessible host'),
			'items' => _('Inaccessible item')
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
