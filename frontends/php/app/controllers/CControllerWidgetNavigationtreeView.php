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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetNavigationtreeView extends CController {
	private $problems_per_severity_tpl;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		// TODO VM: delete comment. Removed widgetid, becuase it is no longer used, after introduction of uniqueid.
		$fields = [
			'name'		=>	'string',
			'uniqueid'	=>	'required',
			'fields'	=>	'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function getNumberOfProblemsBySysmap(array $mapsId = []) {
		$response = [];
		$sysmaps = API::Map()->get([
			'output' => ['sysmapid', 'severity_min'],
			'sysmapids' => $mapsId,
			'preservekeys' => true,
			'severity_min' => 0,
			'selectSelements' => API_OUTPUT_EXTEND
		]);

		if ($sysmaps) {
			$problems_by_elems = [];

			foreach ($sysmaps as $map) {
				foreach ($map['selements'] as $selement) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$element = reset($selement['elements']);
							if ($element) {
								$problems_by_elems[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$element['groupid']]
										= $this->problems_per_severity_tpl;
							}
							break;
						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							foreach (zbx_objectValues($selement['elements'], 'triggerid') as $triggerid) {
								$problems_by_elems[SYSMAP_ELEMENT_TYPE_TRIGGER][$triggerid]
									= $this->problems_per_severity_tpl;
							}
							break;
						case SYSMAP_ELEMENT_TYPE_HOST:
							$element = reset($selement['elements']);
							if ($element) {
								$problems_by_elems[SYSMAP_ELEMENT_TYPE_HOST][$element['hostid']]
										= $this->problems_per_severity_tpl;
							}
							break;
						case SYSMAP_ELEMENT_TYPE_MAP:
							$element = reset($selement['elements']);
							if ($element) {
								$db_mapselements = DBselect(
									'SELECT DISTINCT se.elementtype,se.elementid'.
									' FROM sysmaps_elements se'.
									' WHERE se.sysmapid='.zbx_dbstr($element['sysmapid'])
								);
								while ($db_mapelement = DBfetch($db_mapselements)) {
									$el_type = $db_mapelement['elementtype'];
									$el_id = $db_mapelement['elementid'];

									if (array_key_exists(SYSMAP_ELEMENT_TYPE_MAP, $problems_by_elems)) {
										$problems_by_elems[SYSMAP_ELEMENT_TYPE_MAP][$element['sysmapid']][$el_type][]
											= $el_id;
										$problems_by_elems[$el_type][$el_id] = $this->problems_per_severity_tpl;
									}
								}
							}
							break;
					}
				}
			}

			$severity_min = min(zbx_objectValues($sysmaps, 'severity_min'));

			if (array_key_exists(SYSMAP_ELEMENT_TYPE_HOST_GROUP, $problems_by_elems)) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'priority'],
					'groupids' => array_keys($problems_by_elems[SYSMAP_ELEMENT_TYPE_HOST_GROUP]),
					'min_severity' => $severity_min,
					'skipDependent' => true,
					'selectGroups' => ['groupid'],
					'preservekeys' => true
				]);

				$events = API::Event()->get([
					'output' => ['objectid'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'value' => TRIGGER_VALUE_TRUE,
					'objectids' => zbx_objectValues($triggers, 'triggerid')
				]);

				if ($events) {
					foreach ($events as $event) {
						$trigger = $triggers[$event['objectid']];
						$host_group = reset($trigger['groups']);
						$groupid = $host_group['groupid'];

						if ($host_group) {
							$problems_by_elems[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$groupid][$trigger['priority']]++;
						}
					}
				}
			}

			if (array_key_exists(SYSMAP_ELEMENT_TYPE_TRIGGER, $problems_by_elems)) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'priority'],
					'triggerids' => array_keys($problems_by_elems[SYSMAP_ELEMENT_TYPE_TRIGGER]),
					'min_severity' => $severity_min,
					'skipDependent' => true,
					'selectGroups' => ['groupid'],
					'preservekeys' => true
				]);

				$events = API::Event()->get([
					'output' => ['objectid'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'value' => TRIGGER_VALUE_TRUE,
					'objectids' => array_keys($problems_by_elems[SYSMAP_ELEMENT_TYPE_TRIGGER])
				]);

				if ($events) {
					foreach ($events as $event) {
						$trigger = $triggers[$event['objectid']];

						$problems_by_elems[SYSMAP_ELEMENT_TYPE_TRIGGER][$event['objectid']][$trigger['priority']]++;
					}
				}
			}

			if (array_key_exists(SYSMAP_ELEMENT_TYPE_HOST, $problems_by_elems)) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'priority'],
					'selectHosts' => ['hostid'],
					'hostids' => array_keys($problems_by_elems[SYSMAP_ELEMENT_TYPE_HOST]),
					'min_severity' => $severity_min,
					'skipDependent' => true,
					'preservekeys' => true
				]);

				$events = API::Event()->get([
					'output' => ['objectid'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'value' => TRIGGER_VALUE_TRUE,
					'objectids' => zbx_objectValues($triggers, 'triggerid')
				]);

				if ($events) {
					foreach ($events as $event) {
						$trigger = $triggers[$event['objectid']];
						$host = reset($trigger['hosts']);

						if ($host) {
							$problems_by_elems[SYSMAP_ELEMENT_TYPE_HOST][$host['hostid']][$trigger['priority']]++;
						}
					}
				}
			}

			foreach ($sysmaps as $map) {
				$response[$map['sysmapid']] = $this->problems_per_severity_tpl;

				foreach ($map['selements'] as $selement) {
					$element = reset($selement['elements']);
					if ($element) {
						switch ($selement['elementtype']) {
							case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
								$problems = $problems_by_elems[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$element['groupid']];
								break;
							case SYSMAP_ELEMENT_TYPE_TRIGGER:
								$problems = $this->problems_per_severity_tpl;

								foreach (zbx_objectValues($selement['elements'], 'triggerid') as $triggerid) {
									$problems = array_map(function() {
										return array_sum(func_get_args());
									}, $problems_by_elems[SYSMAP_ELEMENT_TYPE_TRIGGER][$triggerid], $problems);
								}
								break;
							case SYSMAP_ELEMENT_TYPE_HOST:
								$problems = $problems_by_elems[SYSMAP_ELEMENT_TYPE_HOST][$element['hostid']];
								break;
							case SYSMAP_ELEMENT_TYPE_MAP:
								$problems = $this->problems_per_severity_tpl;

								if (array_key_exists(SYSMAP_ELEMENT_TYPE_MAP, $problems_by_elems)) {
									$sysmapid = $element['sysmapid'];

									foreach ($problems_by_elems[SYSMAP_ELEMENT_TYPE_MAP][$sysmapid] as $type => $list) {
										foreach ($el_list as $el_id) {
											if (array_key_exists($el_id, $problems_by_elems[$el_type])) {
												$problems = array_map(function() {
													return array_sum(func_get_args());
												}, $problems_by_elems[$el_type][$el_id], $problems);
											}
										}
									}
								}
								break;
							default:
								$problems = null;
								break;
						}

						if (is_array($problems)) {
							$response[$map['sysmapid']] = array_map(function () {
								return array_sum(func_get_args());
							}, $response[$map['sysmapid']], $problems);
						}
					}
				}
			}
		}

		return $response;
	}

	protected function doAction() {
		$error = null;
		$data = [];

		// Default values
		$default = [];

		if ($this->hasInput('fields')) {
			// Use configured data, if possible
			$data = $this->getInput('fields');
		}

		// Apply defualt value for data
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		// Get list of sysmapids.
		$sysmapids = [];
		foreach ($data as $field_key => $field_value) {
			if (is_numeric($field_value)) {
				preg_match('/^mapid\.\d+$/', $field_key, $field_details);
				if ($field_details) {
					$sysmapids[] = $field_value;
				}
			}
			unset($data[$field_key]);
		}

		// Get severity levels and colors and select list of sysmapids to count problems per maps.
		$sysmapids = array_keys(array_flip($sysmapids));
		$this->problems_per_severity_tpl = [];
		$config = select_config();
		$severity_config = [];

		foreach (range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1) as $severity) {
			$this->problems_per_severity_tpl[$severity] = 0;
			$severity_config[$severity] = [
				'color' => $config['severity_color_'.$severity],
				'name' => $config['severity_name_'.$severity],
			];
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_NAVIGATION_TREE]),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'uniqueid' => getRequest('uniqueid'),
			'problems' => $this->getNumberOfProblemsBySysmap($sysmapids),
			'severity_config' => $severity_config,
			'error' => $error
		]));
	}
}
