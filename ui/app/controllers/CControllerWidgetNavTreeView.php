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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetNavTreeView extends CControllerWidget {

	private $problems_per_severity_tpl;

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_NAV_TREE);
		$this->setValidationRules([
			'name' => 'string',
			'uniqueid' => 'required|string',
			'widgetid' => 'db widget.widgetid',
			'initial_load' => 'in 0,1',
			'fields' => 'json'
		]);
	}

	protected function getNumberOfProblemsBySysmap(array $navtree_items = []) {
		$response = [];
		$sysmapids = [];

		foreach ($navtree_items as $navtree_item) {
			$sysmapids[$navtree_item['sysmapid']] = true;
		}
		unset($sysmapids[0]);

		$sysmaps = $sysmapids
			? API::Map()->get([
				'output' => ['sysmapid', 'severity_min'],
				'selectLinks' => ['linktriggers', 'permission'],
				'selectSelements' => ['elements', 'elementtype', 'permission'],
				'sysmapids' => array_keys($sysmapids),
				'preservekeys' => true
			])
			: [];

		if ($sysmaps) {
			$triggers_per_hosts = [];
			$triggers_per_host_groups = [];
			$problems_per_trigger = [];
			$submaps_relations = [];
			$submaps_found = [];
			$host_groups = [];
			$hosts = [];

			// Gather submaps from all selected maps.
			foreach ($sysmaps as $map) {
				foreach ($map['selements'] as $selement) {
					if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
						if (($element = reset($selement['elements'])) !== false) {
							$submaps_relations[$map['sysmapid']][] = $element['sysmapid'];
							$submaps_found[] = $element['sysmapid'];
						}
					}
				}
			}

			// Gather maps added as submaps for each of map in any depth.
			$sysmaps_resolved = array_keys($sysmaps);
			while ($diff = array_diff($submaps_found, $sysmaps_resolved)) {
				$submaps = API::Map()->get([
					'output' => ['sysmapid', 'severity_min'],
					'selectLinks' => ['linktriggers', 'permission'],
					'selectSelements' => ['elements', 'elementtype', 'permission'],
					'sysmapids' => $diff,
					'preservekeys' => true
				]);

				$sysmaps_resolved = array_merge($sysmaps_resolved, $diff);

				foreach ($submaps as $submap) {
					$sysmaps[$submap['sysmapid']] = $submap;

					foreach ($submap['selements'] as $selement) {
						if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
							$element = reset($selement['elements']);
							if ($element) {
								$submaps_relations[$submap['sysmapid']][] = $element['sysmapid'];
								$submaps_found[] = $element['sysmapid'];
							}
						}
					}
				}
			}

			// Gather elements from all maps selected.
			foreach ($sysmaps as $map) {
				// Collect triggers from map links.
				foreach ($map['links'] as $link) {
					foreach ($link['linktriggers'] as $linktrigger) {
						$problems_per_trigger[$linktrigger['triggerid']] = $this->problems_per_severity_tpl;
					}
				}

				// Collect map elements.
				foreach ($map['selements'] as $selement) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							if (($element = reset($selement['elements'])) !== false) {
								$host_groups[$element['groupid']] = true;
							}
							break;

						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							foreach (zbx_objectValues($selement['elements'], 'triggerid') as $triggerid) {
								$problems_per_trigger[$triggerid] = $this->problems_per_severity_tpl;
							}
							break;

						case SYSMAP_ELEMENT_TYPE_HOST:
							if (($element = reset($selement['elements'])) !== false) {
								$hosts[$element['hostid']] = true;
							}
							break;
					}
				}
			}

			// Drop all inaccessible triggers.
			if ($problems_per_trigger) {
				$triggers = API::Trigger()->get([
					'output' => [],
					'triggerids' => array_keys($problems_per_trigger),
					'monitored' => true,
					'preservekeys' => true
				]);

				$problems_per_trigger = array_intersect_key($problems_per_trigger, $triggers);

				unset($triggers);
			}

			// Select lowest severity to reduce amount of data returned by API.
			$severity_min = min(zbx_objectValues($sysmaps, 'severity_min'));

			// Get triggers related to host groups.
			if ($host_groups) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid'],
					'groupids' => array_keys($host_groups),
					'selectGroups' => ['groupid'],
					'preservekeys' => true
				]);

				foreach ($triggers as $trigger) {
					foreach ($trigger['groups'] as $host_group) {
						$triggers_per_host_groups[$host_group['groupid']][$trigger['triggerid']] = true;
					}
					$problems_per_trigger[$trigger['triggerid']] = $this->problems_per_severity_tpl;
				}

				unset($host_groups);
			}

			// Get triggers related to hosts.
			if ($hosts) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid'],
					'selectHosts' => ['hostid'],
					'hostids' => array_keys($hosts),
					'preservekeys' => true,
					'monitored' => true
				]);

				foreach ($triggers as $trigger) {
					if (($host = reset($trigger['hosts'])) !== false) {
						$triggers_per_hosts[$host['hostid']][$trigger['triggerid']] = true;
						$problems_per_trigger[$trigger['triggerid']] = $this->problems_per_severity_tpl;
					}
				}

				unset($hosts);
			}

			// Count problems per trigger.
			if ($problems_per_trigger) {
				$problems = API::Problem()->get([
					'output' => ['objectid', 'severity'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'objectids' => array_keys($problems_per_trigger),
					'severities' => range($severity_min, TRIGGER_SEVERITY_COUNT - 1),
					'preservekeys' => true
				]);

				if ($problems) {
					foreach ($problems as $problem) {
						$problems_per_trigger[$problem['objectid']][$problem['severity']]++;
					}
				}
			}

			// Count problems in each submap included in navigation tree:
			foreach ($navtree_items as $id => $navtree_item) {
				$maps_need_to_count_in = $navtree_item['child_sysmapids'];
				if ($navtree_item['sysmapid'] != 0) {
					$maps_need_to_count_in[$navtree_item['sysmapid']] = true;
				}

				$response[$id] = $this->problems_per_severity_tpl;
				$problems_counted = [];

				foreach (array_keys($maps_need_to_count_in) as $sysmapid) {
					if (array_key_exists($sysmapid, $sysmaps)) {
						$map = $sysmaps[$sysmapid];

						// Count problems occurred in linked elements.
						foreach ($map['selements'] as $selement) {
							if ($selement['permission'] >= PERM_READ) {
								$problems = $this->getElementProblems($selement, $problems_per_trigger, $sysmaps,
									$submaps_relations, $map['severity_min'], $problems_counted, $triggers_per_hosts,
									$triggers_per_host_groups
								);

								if ($problems !== null) {
									$response[$id] = self::sumArrayValues($response[$id], $problems);
								}
							}
						}

						// Count problems occurred in triggers which are related to links.
						foreach ($map['links'] as $link) {
							$uncounted_problem_triggers = array_diff_key(
								array_flip(zbx_objectValues($link['linktriggers'], 'triggerid')),
								$problems_counted
							);

							foreach ($uncounted_problem_triggers as $triggerid => $var) {
								$problems_to_add = $problems_per_trigger[$triggerid];
								$problems_counted[$triggerid] = true;

								// Remove problems which are less important than map's min-severity.
								if ($map['severity_min'] > 0) {
									foreach ($problems_to_add as $sev => $probl) {
										if ($map['severity_min'] > $sev) {
											$problems_to_add[$sev] = 0;
										}
									}
								}

								$response[$id] = self::sumArrayValues($response[$id], $problems_to_add);
							}
							unset($uncounted_problem_triggers);
						}
					}
				}
			}
		}

		foreach ($response as &$row) {
			// Reduce the amount of data transferred over Ajax.
			if ($row === $this->problems_per_severity_tpl) {
				$row = 0;
			}
		}
		unset($row);

		return $response;
	}

	protected function getElementProblems(array $selement, array $problems_per_trigger, array $sysmaps,
			array $submaps_relations, $severity_min = 0, array &$problems_counted = [], array $triggers_per_hosts = [],
			array $triggers_per_host_groups = []) {
		$problems = null;

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$problems = $this->problems_per_severity_tpl;

				if (($element = reset($selement['elements'])) !== false) {
					if (array_key_exists($element['groupid'], $triggers_per_host_groups)) {
						$uncounted_problem_triggers = array_diff_key($triggers_per_host_groups[$element['groupid']],
							$problems_counted
						);
						foreach ($uncounted_problem_triggers as $triggerid => $var) {
							$problems_counted[$triggerid] = true;
							$problems = self::sumArrayValues($problems, $problems_per_trigger[$triggerid]);
						}
						unset($uncounted_problem_triggers);
					}
				}
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$problems = $this->problems_per_severity_tpl;
				$uncounted_problem_triggers = array_diff_key(
					array_flip(zbx_objectValues($selement['elements'], 'triggerid')),
					$problems_counted
				);
				foreach ($uncounted_problem_triggers as $triggerid => $var) {
					$problems_counted[$triggerid] = true;

					if (array_key_exists($triggerid, $problems_per_trigger)) {
						$problems = self::sumArrayValues($problems, $problems_per_trigger[$triggerid]);
					}
				}
				unset($uncounted_problem_triggers);
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				$problems = $this->problems_per_severity_tpl;

				if (($element = reset($selement['elements'])) !== false) {
					if (array_key_exists($element['hostid'], $triggers_per_hosts)) {
						$uncounted_problem_triggers = array_diff_key($triggers_per_hosts[$element['hostid']],
							$problems_counted
						);
						foreach ($uncounted_problem_triggers as $triggerid => $var) {
							$problems_counted[$triggerid] = true;
							$problems = self::sumArrayValues($problems, $problems_per_trigger[$triggerid]);
						}
						unset($uncounted_problem_triggers);
					}
				}
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				$problems = $this->problems_per_severity_tpl;

				if (($submap_element = reset($selement['elements'])) !== false) {
					// Recursively find all submaps in any depth and put them into an array.
					$maps_to_process[$submap_element['sysmapid']] = false;

					while (array_filter($maps_to_process, function($item) {return !$item;})) {
						foreach ($maps_to_process as $linked_map => $val) {
							$maps_to_process[$linked_map] = true;

							if (array_key_exists($linked_map, $submaps_relations)) {
								foreach ($submaps_relations[$linked_map] as $submap) {
									if (!array_key_exists($submap, $maps_to_process)) {
										$maps_to_process[$submap] = false;
									}
								}
							}
						}
					}

					// Count problems in each of selected submap.
					foreach ($maps_to_process as $sysmapid => $val) {
						// Count problems in elements assigned to selements.
						if (array_key_exists($sysmapid, $sysmaps)) {
							foreach ($sysmaps[$sysmapid]['selements'] as $submap_selement) {
								if ($submap_selement['permission'] >= PERM_READ) {
									$problems_in_submap = $this->getElementProblems($submap_selement,
										$problems_per_trigger, $sysmaps, $submaps_relations,
										$sysmaps[$sysmapid]['severity_min'], $problems_counted, $triggers_per_hosts,
										$triggers_per_host_groups
									);

									if ($problems_in_submap !== null) {
										$problems = self::sumArrayValues($problems, $problems_in_submap);
									}
								}
							}
						}

						// Count problems in triggers assigned to linked.
						if (array_key_exists($sysmapid, $sysmaps)) {
							foreach ($sysmaps[$sysmapid]['links'] as $link) {
								if ($link['permission'] >= PERM_READ) {
									$uncounted_problem_triggers = array_diff_key(
										array_flip(zbx_objectValues($link['linktriggers'], 'triggerid')),
										$problems_counted
									);
									foreach ($uncounted_problem_triggers as $triggerid => $var) {
										$problems_counted[$triggerid] = true;
										$problems = self::sumArrayValues($problems, $problems_per_trigger[$triggerid]);
									}
									unset($uncounted_problem_triggers);
								}
							}
						}
					}
				}
				break;
		}

		// Remove problems which are less important than $severity_min.
		if ($problems !== null && $severity_min > 0) {
			foreach ($problems as $sev => $probl) {
				if ($severity_min > $sev) {
					$problems[$sev] = 0;
				}
			}
		}

		return $problems;
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$error = null;

		// Get list of sysmapids.
		$sysmapids = [];
		$navtree_items = [];
		foreach ($fields['navtree'] as $id => $navtree_item) {
			$sysmapid = array_key_exists('sysmapid', $navtree_item) ? $navtree_item['sysmapid'] : 0;
			if ($sysmapid != 0) {
				$sysmapids[$sysmapid] = true;
			}

			$navtree_items[$id] = [
				'parent' => $navtree_item['parent'],
				'sysmapid' => $sysmapid,
				'child_sysmapids' => []
			];
		}

		// Propagate item mapids to all its parent items.
		foreach ($navtree_items as $navtree_item) {
			$parent = $navtree_item['parent'];

			while (array_key_exists($parent, $navtree_items)) {
				if ($navtree_item['sysmapid'] != 0) {
					$navtree_items[$parent]['child_sysmapids'][$navtree_item['sysmapid']] = true;
				}
				$parent = $navtree_items[$parent]['parent'];
			}
		}

		// Get severity levels and colors and select list of sysmapids to count problems per maps.
		$this->problems_per_severity_tpl = [];
		$config = select_config();
		$severity_config = [];

		$maps_accessible = $sysmapids
			? API::Map()->get([
				'output' => [],
				'sysmapids' => array_keys($sysmapids),
				'preservekeys' => true
			])
			: [];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$this->problems_per_severity_tpl[$severity] = 0;
			$severity_config[$severity] = [
				'name' => getSeverityName($severity, $config),
				'style_class' => getSeverityStatusStyle($severity)
			];
		}

		$widgetid = $this->getInput('widgetid', 0);
		$navtree_item_selected = 0;
		$navtree_items_opened = [];
		if ($widgetid) {
			$navtree_items_opened = CProfile::findByIdxPattern('web.dashbrd.navtree-%.toggle', $widgetid);
			// Keep only numerical value from idx key name.
			foreach ($navtree_items_opened as &$item_opened) {
				$item_opened = substr($item_opened, 20, -7);
			}
			unset($item_opened);
			$navtree_item_selected = CProfile::get('web.dashbrd.navtree.item.selected', 0, $widgetid);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'uniqueid' => $this->getInput('uniqueid'),
			'navtree' => $fields['navtree'],
			'navtree_item_selected' => $navtree_item_selected,
			'navtree_items_opened' => $navtree_items_opened,
			'problems' => $this->getNumberOfProblemsBySysmap($navtree_items),
			'show_unavailable' => $fields['show_unavailable'],
			'maps_accessible' => array_keys($maps_accessible),
			'severity_config' => $severity_config,
			'initial_load' => $this->getInput('initial_load', 0),
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Function is used to sum problems in 2 arrays.
	 *
	 * Example:
	 * $a1 = [1 => 0, 2 => 5, 3 => 10];
	 * $a2 = [1 => 1, 2 => 2, 3 => 3];
	 * self::sumArrayValues($a1, $a2); // returns [1 => 1, 2 => 7, 3 => 13]
	 *
	 * @param array $a1  Array containing severity as key and number of problems as value.
	 * @param array $a2  Array containing severity as key and number of problems as value.
	 *
	 * @return array  Array containing problems in both arrays summed.
	 */
	protected static function sumArrayValues(array $a1, array $a2) {
		foreach ($a1 as $key => &$value) {
			$value += $a2[$key];
		}
		unset($value);

		return $a1;
	}
}
