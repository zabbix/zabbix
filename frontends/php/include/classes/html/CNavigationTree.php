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


class CNavigationTree extends CDiv {
		private $error;
		private $script_file;
		private $script_run;
		private $field_items;
		private $problems;
		private $problems_per_severity_tpl;
		private $severity_config;
		private $severity_min;
		private $data;

		public function __construct(array $data = []) {
			parent::__construct();

			// seveity
			$this->problems_per_severity_tpl = [];
			$this->severity_config = [];
			$this->severity_min = 0;
			$config = select_config();

			foreach (range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1) as $severity) {
				$this->problems_per_severity_tpl[$severity] = 0;
				$this->severity_config[$severity] = [
					'color' => $config['severity_color_'.$severity],
					'name' => $config['severity_name_'.$severity],
				];
			}

			// response
			$this->setId(uniqid());
			$this->addClass(ZBX_STYLE_NAVIGATIONTREE);

			$sysmaps = zbx_objectValues($data['field_items'], 'mapid');
			$this->error = null;
			$this->field_items = $data['field_items'];
			$this->problems = $this->getNumberOfProblemsBySysmap($sysmaps);
			$this->data = $data;
			$this->script_file = 'js/class.cnavtree.js';
			$this->script_run = '';
		}

		public function setError($value) {
			$this->error = $value;
			return $this;
		}

		public function getScriptFile() {
			return $this->script_file;
		}

		public function getScriptRun() {
			if ($this->error === null) {
				$this->script_run .= ''.
					'jQuery("#'.$this->getId().'").zbx_navtree({'.
						'problems: '.json_encode($this->problems).','.
						'severity_levels: '.json_encode($this->getSeverityConfig()).','.
						'uniqueid: "'.$this->data['uniqueid'].'",'.
						'max_depth: '.WIDGET_NAVIGATION_TREE_MAX_DEPTH.
					'});';
			}

			return $this->script_run;
		}

		protected function getNumberOfProblemsBySysmap(array $mapsId = []) {
			$response = [];
			$sysmaps = API::Map()->get([
				'output' => ['sysmapid', 'severity_min'],
				'sysmapids' => $mapsId,
				'preservekeys' => true,
				'severity_min' => $this->severity_min,
				'selectSelements' => API_OUTPUT_EXTEND
			]);

			if ($sysmaps) {
				$problems_by_elements = [];

				foreach ($sysmaps as $map) {
					foreach ($map['selements'] as $selement) {
						switch ($selement['elementtype']) {
							case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
								$element = reset($selement['elements']);
								if ($element) {
									$problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$element['groupid']]
											= $this->problems_per_severity_tpl;
								}
								break;
							case SYSMAP_ELEMENT_TYPE_TRIGGER:
								foreach (zbx_objectValues($selement['elements'], 'triggerid') as $triggerid) {
									$problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER][$triggerid] = $this->problems_per_severity_tpl;
								}
								break;
							case SYSMAP_ELEMENT_TYPE_HOST:
								$element = reset($selement['elements']);
								if ($element) {
									$problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST][$element['hostid']] = $this->problems_per_severity_tpl;
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

										if (array_key_exists(SYSMAP_ELEMENT_TYPE_MAP, $problems_by_elements)) {
											$problems_by_elements[SYSMAP_ELEMENT_TYPE_MAP][$element['sysmapid']][$el_type][] = $el_id;
											$problems_by_elements[$el_type][$el_id] = $this->problems_per_severity_tpl;
										}
									}
								}
								break;
						}
					}
				}

				$severity_min = min(zbx_objectValues($sysmaps, 'severity_min'));

				if (array_key_exists(SYSMAP_ELEMENT_TYPE_HOST_GROUP, $problems_by_elements)) {
					$triggers = API::Trigger()->get([
						'output' => ['triggerid', 'priority'],
						'groupids' => array_keys($problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST_GROUP]),
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

							if ($host_group) {
								$problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$host_group['groupid']][$trigger['priority']]++;
							}
						}
					}
				}

				if (array_key_exists(SYSMAP_ELEMENT_TYPE_TRIGGER, $problems_by_elements)) {
					$triggers = API::Trigger()->get([
						'output' => ['triggerid', 'priority'],
						'triggerids' => array_keys($problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER]),
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
						'objectids' => array_keys($problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER])
					]);

					if ($events) {
						foreach ($events as $event) {
							$trigger = $triggers[$event['objectid']];

							$problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER][$event['objectid']][$trigger['priority']]++;
						}
					}
				}

				if (array_key_exists(SYSMAP_ELEMENT_TYPE_HOST, $problems_by_elements)) {
					$triggers = API::Trigger()->get([
						'output' => ['triggerid', 'priority'],
						'selectHosts' => ['hostid'],
						'hostids' => array_keys($problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST]),
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
								$problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST][$host['hostid']][$trigger['priority']]++;
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
									$problems = $problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$element['groupid']];
									break;
								case SYSMAP_ELEMENT_TYPE_TRIGGER:
									$problems = $this->problems_per_severity_tpl;

									foreach (zbx_objectValues($selement['elements'], 'triggerid') as $triggerid) {
										$problems = array_map(function() {
											return array_sum(func_get_args());
										}, $problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER][$triggerid], $problems);
									}
										break;
								case SYSMAP_ELEMENT_TYPE_HOST:
									$problems = $problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST][$element['hostid']];
									break;
								case SYSMAP_ELEMENT_TYPE_MAP:
									$problems = $this->problems_per_severity_tpl;

									if (array_key_exists(SYSMAP_ELEMENT_TYPE_MAP, $problems_by_elements)) {
										foreach ($problems_by_elements[SYSMAP_ELEMENT_TYPE_MAP][$element['sysmapid']] as $el_type => $el_list) {
											foreach ($el_list as $el_id) {
												if (array_key_exists($el_id, $problems_by_elements[$el_type])) {
													$problems = array_map(function() {
														return array_sum(func_get_args());
													}, $problems_by_elements[$el_type][$el_id], $problems);
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

		private function build() {
			if ($this->error !== null) {
				$span->addClass(ZBX_STYLE_DISABLED);
			}

			$this->addItem((new CDiv())->addClass('tree'));
		}

		public function getSeverityConfig() {
			return $this->severity_config;
		}

		public function toString($destroy = true) {
			$this->build();

			return parent::toString($destroy);
		}
}
