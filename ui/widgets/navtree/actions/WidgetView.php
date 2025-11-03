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


namespace Widgets\NavTree\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CProfile,
	CSeverityHelper;

class WidgetView extends CControllerDashboardWidgetView {

	private array $problems_per_severity_tpl;

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'name' => 'string',
			'widgetid' => 'db widget.widgetid',
			'fields' => 'array'
		]);
	}

	protected function doAction(): void {
		// Get list of sysmapids.
		$sysmapids = [];
		$navtree_items = [];

		foreach ($this->fields_values['navtree'] as $id => $navtree_item) {
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
		$severity_config = [];

		$maps_accessible = $sysmapids
			? API::Map()->get([
				'output' => [],
				'sysmapids' => array_keys($sysmapids),
				'preservekeys' => true
			])
			: [];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$this->problems_per_severity_tpl[$severity] = [];
			$severity_config[$severity] = [
				'name' => CSeverityHelper::getName($severity),
				'style_class' => CSeverityHelper::getStatusStyle($severity)
			];
		}

		$widgetid = $this->getInput('widgetid', 0);
		$navtree_item_selected = 0;
		$navtree_items_opened = [];

		if ($widgetid) {
			$pattern = 'web.dashboard.widget.navtree.item-%.toggle';
			$discard_from_start = strpos($pattern, '%');
			$discard_from_end = strlen($pattern) - $discard_from_start - 1;

			foreach (CProfile::findByIdxPattern($pattern, $widgetid) as $item_opened) {
				$navtree_items_opened[] = substr($item_opened, $discard_from_start, -$discard_from_end);
			}

			$navtree_item_selected = CProfile::get('web.dashboard.widget.navtree.item.selected', 0, $widgetid);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'navtree' => $this->fields_values['navtree'],
			'navtree_item_selected' => $navtree_item_selected,
			'navtree_items_opened' => $navtree_items_opened,
			'problems' => $this->getNumberOfProblemsBySysmap($navtree_items),
			'show_unavailable' => $this->fields_values['show_unavailable'],
			'maps_accessible' => array_keys($maps_accessible),
			'severity_config' => $severity_config,
			'initial_load' => $this->getInput('initial_load', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	private function getNumberOfProblemsBySysmap(array $navtree_items = []): array {
		$response = [];
		$sysmapids = [];

		foreach ($navtree_items as $navtree_item) {
			$sysmapids[$navtree_item['sysmapid']] = true;
		}
		unset($sysmapids[0]);

		$sysmaps = $sysmapids
			? API::Map()->get([
				'output' => ['sysmapid', 'severity_min', 'show_suppressed', 'show_unack'],
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
					'output' => ['sysmapid', 'severity_min', 'show_suppressed', 'show_unack'],
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
							foreach (array_column($selement['elements'], 'triggerid') as $triggerid) {
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

			// Drop all disabled and inaccessible triggers.
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
			$severity_min = min(array_column($sysmaps, 'severity_min'));

			// Get triggers related to host groups.
			if ($host_groups) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid'],
					'selectHostGroups' => ['groupid'],
					'groupids' => array_keys($host_groups),
					'preservekeys' => true
				]);

				foreach ($triggers as $trigger) {
					foreach ($trigger['hostgroups'] as $host_group) {
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
					'output' => ['objectid', 'severity', 'suppressed', 'acknowledged'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'objectids' => array_keys($problems_per_trigger),
					'severities' => range($severity_min, TRIGGER_SEVERITY_COUNT - 1),
					'symptom' => false,
					'preservekeys' => true
				]);

				if ($problems) {
					foreach ($problems as $problem) {
						$problems_per_trigger[$problem['objectid']][$problem['severity']][] = $problem;
					}
				}
			}

			// Count problems in each submap included in navigation tree:
			foreach ($navtree_items as $nav_id => $navtree_item) {
				$maps_need_to_count_in = $navtree_item['child_sysmapids'];
				if ($navtree_item['sysmapid'] != 0) {
					$maps_need_to_count_in[$navtree_item['sysmapid']] = true;
				}

				$response[$nav_id] = $this->problems_per_severity_tpl;

				foreach (array_keys($maps_need_to_count_in) as $sysmapid) {
					if (array_key_exists($sysmapid, $sysmaps)) {
						$map = $sysmaps[$sysmapid];

						// Count problems occurred in linked elements.
						foreach ($map['selements'] as $selement) {
							if ($selement['permission'] >= PERM_READ) {
								$problems = $this->getElementProblems($selement, $problems_per_trigger, $sysmaps,
									$submaps_relations, $triggers_per_hosts, $triggers_per_host_groups
								);

								if ($problems !== null) {
									$response[$nav_id] = self::sumProblems($response[$nav_id], $problems);
								}
							}
						}

						// Count problems occurred in triggers which are related to the links.
						foreach ($map['links'] as $link) {
							foreach (array_column($link['linktriggers'], 'triggerid', 'triggerid') as $triggerid) {
								// Skip disabled and inaccessible triggers.
								if (!array_key_exists($triggerid, $problems_per_trigger)) {
									continue;
								}

								$response[$nav_id] = self::sumProblems($response[$nav_id],
									$problems_per_trigger[$triggerid]
								);
							}
						}

						$response[$nav_id] = $this->filterProblemsByMapSettings($response[$nav_id], $map);
					}
				}
			}
		}

		foreach ($response as &$row) {
			// Reduce the amount of data transferred over Ajax.
			if ($row === $this->problems_per_severity_tpl) {
				$row = 0;

				continue;
			}

			foreach ($row as &$problems) {
				$problems = count($problems);
			}
			unset($problems);
		}
		unset($row);

		return $response;
	}

	private function getElementProblems(array $selement, array $problems_per_trigger, array $sysmaps,
			array $submaps_relations, array $triggers_per_hosts = [], array $triggers_per_host_groups = []): ?array {
		$problems = $this->problems_per_severity_tpl;
		$triggerids = [];

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				if (($element = reset($selement['elements'])) !== false
						&& array_key_exists($element['groupid'], $triggers_per_host_groups)) {
					$triggerids = array_keys($triggers_per_host_groups[$element['groupid']]);
				}
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$triggerids = array_column($selement['elements'], 'triggerid', 'triggerid');
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				if (($element = reset($selement['elements'])) !== false
						&& array_key_exists($element['hostid'], $triggers_per_hosts)) {
					$triggerids = array_keys($triggers_per_hosts[$element['hostid']]);
				}
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				if (($submap_element = reset($selement['elements'])) !== false) {
					// Recursively find all submaps in any depth and put them into an array.
					$maps_to_process[$submap_element['sysmapid']] = false;

					while (array_filter($maps_to_process, static fn ($item) => !$item)) {
						foreach (array_keys($maps_to_process) as $linked_map) {
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
					foreach (array_keys($maps_to_process) as $sysmapid) {
						// Count problems in elements assigned to selements.
						if (array_key_exists($sysmapid, $sysmaps)) {
							foreach ($sysmaps[$sysmapid]['selements'] as $submap_selement) {
								if ($submap_selement['permission'] < PERM_READ) {
									continue;
								}

								$problems_in_submap = $this->getElementProblems($submap_selement,
									$problems_per_trigger, $sysmaps, $submaps_relations, $triggers_per_hosts,
									$triggers_per_host_groups
								);

								if ($problems_in_submap !== null) {
									$problems = self::sumProblems($problems, $problems_in_submap);
								}
							}
						}

						// Count problems in triggers assigned to linked.
						if (array_key_exists($sysmapid, $sysmaps)) {
							foreach ($sysmaps[$sysmapid]['links'] as $link) {
								if ($link['permission'] < PERM_READ) {
									continue;
								}

								foreach (array_column($link['linktriggers'], 'triggerid', 'triggerid') as $triggerid) {
									// Skip disabled and inaccessible triggers.
									if (array_key_exists($triggerid, $problems_per_trigger)) {
										$problems = self::sumProblems($problems, $problems_per_trigger[$triggerid]);
									}
								}
							}
						}
					}
				}
				return $problems;

			default:
				return null;
		}

		foreach ($triggerids as $triggerid) {
			// Skip disabled and inaccessible triggers.
			if (array_key_exists($triggerid, $problems_per_trigger)) {
				$problems = self::sumProblems($problems, $problems_per_trigger[$triggerid]);
			}
		}

		return $problems;
	}

	/**
	 * Filter problems by minimum severity, suppression and acknowledge.
	 *
	 * @param array $problems  Array containing severity as key and array of problems as value.
	 * @param array $map       Map settings
	 *
	 * @return array  Filtered array containing severity as key and array of problems as value.
	 */
	private function filterProblemsByMapSettings(array $problems, array $map): array {
		if ($map['severity_min'] == 0 && $map['show_suppressed'] != ZBX_PROBLEM_SUPPRESSED_FALSE
				&& $map['show_unack'] != EXTACK_OPTION_UNACK) {
			return $problems;
		}

		foreach (array_keys($problems) as $severity) {
			if ($map['severity_min'] > $severity) {
				$problems[$severity] = [];
			}

			foreach ($problems[$severity] as $key => $problem) {
				if (($map['show_unack'] == EXTACK_OPTION_UNACK && $problem['acknowledged'] == EVENT_ACKNOWLEDGED)
						|| ($map['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_FALSE
							&& $problem['suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)) {
					unset($problems[$severity][$key]);
				}
			}
		}

		return $problems;
	}

	/**
	 * Function is used to sum problems in 2 arrays.
	 *
	 * @param array $a1  Array containing severity as key and array of problems as value.
	 * @param array $a2  Array containing severity as key and array of problems as value.
	 *
	 * @return array  Array containing unique problems from both arrays.
	 */
	private static function sumProblems(array $a1, array $a2): array {
		foreach (array_keys($a1) as $severity) {
			$seen_problems = array_flip(array_column($a1[$severity], 'objectid'));

			foreach ($a2[$severity] as $problem) {
				if (!array_key_exists($problem['objectid'], $seen_problems)) {
					$a1[$severity][] = $problem;
				}
			}
		}

		return $a1;
	}
}
