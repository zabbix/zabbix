<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
			'widgetid'	=> 'db widget.widgetid',
			'type'		=> 'in '.implode(',', array_keys(CWidgetConfig::getKnownWidgetTypes())),
			'name'		=> 'string',
			'fields'	=> 'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var string fields[<name>]  (optional)
			 */
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['body' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$type = $this->getInput('type', WIDGET_CLOCK);
		$form = CWidgetConfig::getForm($type, $this->getInput('fields', []));

		$config = select_config();

		$this->setResponse(new CControllerResponseData([
			'config' => [
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
				'type' => $this->getInput('type', WIDGET_CLOCK),
				'name' => $this->getInput('name', ''),
				'form' => $form,
			],
			'captions' => $this->getCaptions($form)
		]));
	}

	/**
	 * Prepares mapped list of names for all required resources
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
						case WIDGET_FIELD_SELECT_RES_ITEM:
							$captions['simple'][$resource_type][$id] = _('Inaccessible item');
							break;

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
				case WIDGET_FIELD_SELECT_RES_ITEM:
					$items = API::Item()->get([
						'output' => ['itemid', 'hostid', 'key_', 'name'],
						'selectHosts' => ['name'],
						'itemids' => array_keys($list),
						'webitems' => true
					]);

					if ($items) {
						$items = CMacrosResolverHelper::resolveItemNames($items);

						foreach ($items as $key => $item) {
							$list[$item['itemid']] = $item['hosts'][0]['name'].NAME_DELIMITER.$item['name_expanded'];
						}
					}
					break;

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
			}
		}
		unset($list);

		// Prepare data for CMultiSelect controls.
		$groupids = [];
		$hostids = [];

		foreach ($form->getFields() as $field) {
			if ($field instanceof CWidgetFieldGroup) {
				$field_name = $field->getName();
				$captions['ms']['groups'][$field_name] = [];

				foreach ($field->getValue() as $groupid) {
					$captions['ms']['groups'][$field_name][$groupid] = [
						'id' => $groupid,
						'name' => _('Inaccessible group'),
						'inaccessible' => true
					];
					$groupids[$groupid][] = $field_name;
				}
			}
			elseif ($field instanceof CWidgetFieldHost) {
				$field_name = $field->getName();
				$captions['ms']['hosts'][$field_name] = [];

				foreach ($field->getValue() as $hostid) {
					$captions['ms']['hosts'][$field_name][$hostid] = [
						'id' => $hostid,
						'name' => _('Inaccessible host'),
						'inaccessible' => true
					];
					$hostids[$hostid][] = $field_name;
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
					unset($captions['ms']['hosts'][$field_name][$hostid]['inaccessible']);
				}
			}
		}

		return $captions;
	}
}
