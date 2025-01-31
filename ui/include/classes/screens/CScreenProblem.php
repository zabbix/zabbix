<?php
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


/**
 * A class to display problems as a screen element.
 */
class CScreenProblem extends CScreenBase {

	/**
	 * Data
	 *
	 * @var array
	 */
	public $data;

	/**
	 * Init screen data.
	 *
	 * @param array $options
	 * @param array $options['data']
	 */
	public function __construct(array $options = []) {
		parent::__construct($options);
		$this->data = array_key_exists('data', $options) ? $options['data'] : null;

		if ($this->data['filter']['show'] == TRIGGERS_OPTION_ALL) {
			$this->data['filter']['from'] = $this->timeline['from_ts'];
			$this->data['filter']['to'] = $this->timeline['to_ts'];
		}
	}

	/**
	 * Get problems from "events" table.
	 *
	 * @param array       $options
	 * @param array|null  $options['groupids']
	 * @param array|null  $options['hostids']
	 * @param array|null  $options['objectids']
	 * @param string|null $options['eventid_till']
	 * @param int|null    $options['time_from']
	 * @param int|null    $options['time_till']
	 * @param array       $options['severities']      (optional)
	 * @param bool        $options['acknowledged']    (optional)
	 * @param array       $options['tags']            (optional)
	 * @param int         $options['limit']
	 *
	 * @return array
	 */
	private static function getDataEvents(array $options) {
		return API::Event()->get([
			'output' => ['eventid', 'objectid', 'clock', 'ns', 'name', 'severity', 'cause_eventid'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'preservekeys' => true
		] + $options);
	}

	/**
	 * Get problems from "problem" table.
	 *
	 * @param array       $options
	 * @param array|null  $options['groupids']
	 * @param array|null  $options['hostids']
	 * @param array|null  $options['objectids']
	 * @param string|null $options['eventid_till']
	 * @param bool        $options['recent']
	 * @param array       $options['severities']      (optional)
	 * @param bool        $options['acknowledged']    (optional)
	 * @param int         $options['time_from']       (optional)
	 * @param array       $options['tags']            (optional)
	 * @param int         $options['limit']
	 *
	 * @return array
	 */
	private static function getDataProblems(array $options) {
		return API::Problem()->get([
			'output' => ['eventid', 'objectid', 'clock', 'ns', 'name', 'severity', 'cause_eventid'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'preservekeys' => true
		] + $options);
	}

	/**
	 * Get problems from "problem" table. Return:
	 * [
	 *     'problems' => [...],
	 *     'triggers' => [...]
	 * ]
	 *
	 * @param array  $filter
	 * @param array  $filter['groupids']              (optional)
	 * @param array  $filter['exclude_groupids']      (optional)
	 * @param array  $filter['hostids']               (optional)
	 * @param array  $filter['triggerids']            (optional)
	 * @param array  $filter['inventory']             (optional)
	 * @param string $filter['inventory'][]['field']
	 * @param string $filter['inventory'][]['value']
	 * @param string $filter['name']                  (optional)
	 * @param int    $filter['show']                  TRIGGERS_OPTION_*
	 * @param int    $filter['from']                  (optional) usable together with 'to' and only for
	 *                                                           TRIGGERS_OPTION_ALL, timestamp.
	 * @param int    $filter['to']                    (optional) usable together with 'from' and only for
	 *                                                           TRIGGERS_OPTION_ALL, timestamp.
	 * @param int    $filter['age_state']             (optional) usable together with 'age' and only for
	 *                                                           TRIGGERS_OPTION_(RECENT|IN)_PROBLEM
	 * @param int    $filter['age']                   (optional) usable together with 'age_state' and only for
	 *                                                           TRIGGERS_OPTION_(RECENT|IN)_PROBLEM
	 * @param array  $filter['severities']            (optional)
	 * @param int    $filter['acknowledgement_status'] (optional)
	 * @param array  $filter['tags']                  (optional)
	 * @param string $filter['tags'][]['tag']
	 * @param string $filter['tags'][]['value']
	 * @param int    $filter['show_symptoms']         (optional)
	 * @param int    $filter['show_suppressed']       (optional)
	 * @param int    $filter['show_opdata']           (optional)
	 * @param array  $filter['cause_eventid']         (optional)
	 * @param int    $limit
	 * @param bool   $resolve_comments
	 *
	 * @return mixed
	 */
	public static function getData(array $filter, int $limit, bool $resolve_comments = false) {
		$filter_groupids = array_key_exists('groupids', $filter) && $filter['groupids']
			? getSubGroups($filter['groupids'])
			: null;
		$filter_hostids = array_key_exists('hostids', $filter) && $filter['hostids'] ? $filter['hostids'] : null;
		$filter_triggerids = array_key_exists('triggerids', $filter) && $filter['triggerids']
			? $filter['triggerids']
			: null;
		$show_opdata = array_key_exists('show_opdata', $filter) && $filter['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE;

		if (array_key_exists('exclude_groupids', $filter) && $filter['exclude_groupids']) {
			$exclude_groupids = getSubGroups($filter['exclude_groupids']);

			if ($filter_hostids === null) {
				// get all groups if no selected groups defined
				if ($filter_groupids === null) {
					$filter_groupids = array_keys(API::HostGroup()->get([
						'output' => [],
						'with_hosts' => true,
						'preservekeys' => true
					]));
				}

				$filter_groupids = array_diff($filter_groupids, $exclude_groupids);

				// get available hosts
				$filter_hostids = array_keys(API::Host()->get([
					'output' => [],
					'groupids' => $filter_groupids,
					'preservekeys' => true
				]));
			}

			$exclude_hostids = array_keys(API::Host()->get([
				'output' => [],
				'groupids' => $exclude_groupids,
				'preservekeys' => true
			]));

			$filter_hostids = array_diff($filter_hostids, $exclude_hostids);
		}

		if (array_key_exists('inventory', $filter) && $filter['inventory']) {
			$options = [
				'output' => [],
				'groupids' => $filter_groupids,
				'hostids' => $filter_hostids,
				'preservekeys' => true
			];
			foreach ($filter['inventory'] as $field) {
				$options['searchInventory'][$field['field']][] = $field['value'];
			}

			$hostids = array_keys(API::Host()->get($options));

			$filter_hostids = ($filter_hostids !== null) ? array_intersect($filter_hostids, $hostids) : $hostids;
		}

		$data = [
			'problems' => [],
			'triggers' => []
		];

		$seen_triggerids = [];
		$eventid_till = null;

		do {
			$options = [
				'groupids' => $filter_groupids,
				'hostids' => $filter_hostids,
				'objectids' => $filter_triggerids,
				'eventid_till' => $eventid_till,
				'suppressed' => false,
				'symptom' => false,
				'limit' => $limit + 1
			];

			if (array_key_exists('name', $filter) && $filter['name'] !== '') {
				$options['search']['name'] = $filter['name'];
			}

			if ($filter['show'] == TRIGGERS_OPTION_ALL) {
				if (array_key_exists('from', $filter) && array_key_exists('to', $filter)) {
					$options['time_from'] = $filter['from'];
					$options['time_till'] = $filter['to'];
				}
			}
			else {
				$options['recent'] = ($filter['show'] == TRIGGERS_OPTION_RECENT_PROBLEM);
				if (array_key_exists('age_state', $filter) && array_key_exists('age', $filter)
						&& $filter['age_state'] == 1) {
					$options['time_from'] = time() - $filter['age'] * SEC_PER_DAY + 1;
				}
			}
			if (array_key_exists('severities', $filter) && $filter['severities']) {
				$options['severities'] = $filter['severities'];
			}
			if (array_key_exists('evaltype', $filter)) {
				$options['evaltype'] = $filter['evaltype'];
			}
			if (array_key_exists('tags', $filter) && $filter['tags']) {
				$options['tags'] = $filter['tags'];
			}
			if (array_key_exists('show_suppressed', $filter) && $filter['show_suppressed']) {
				unset($options['suppressed']);
			}

			// Show both cause and symptom problems or only cause problems depending on filter setting in view/widget.
			if (array_key_exists('show_symptoms', $filter) && $filter['show_symptoms']) {
				unset($options['symptom']);
			}

			if (array_key_exists('cause_eventid', $filter) && $filter['cause_eventid']) {
				$options['filter']['cause_eventid'] = $filter['cause_eventid'];
			}

			if (array_key_exists('acknowledgement_status', $filter)) {
				switch ($filter['acknowledgement_status']) {
					case ZBX_ACK_STATUS_UNACK:
						$options['acknowledged'] = false;
						break;

					case ZBX_ACK_STATUS_ACK:
						$options['acknowledged'] = true;

						if (array_key_exists('acknowledged_by_me', $filter) && $filter['acknowledged_by_me'] == 1) {
							$options += [
								'action' => ZBX_PROBLEM_UPDATE_ACKNOWLEDGE,
								'action_userids' => CUser::$userData['userid']
							];
						}
						break;
				}
			}

			$problems = ($filter['show'] == TRIGGERS_OPTION_ALL)
				? self::getDataEvents($options)
				: self::getDataProblems($options);

			$end_of_data = (count($problems) < $limit + 1);

			if ($problems) {
				$eventid_till = end($problems)['eventid'] - 1;
				$triggerids = [];

				foreach ($problems as $problem) {
					if (!array_key_exists($problem['objectid'], $seen_triggerids)) {
						$triggerids[$problem['objectid']] = true;
					}
				}

				if ($triggerids) {
					$seen_triggerids += $triggerids;

					$options = [
						'output' => ['priority', 'manual_close'],
						'selectHosts' => ['hostid'],
						'triggerids' => array_keys($triggerids),
						'monitored' => true,
						'skipDependent' => ($filter['show'] == TRIGGERS_OPTION_ALL) ? null : true,
						'preservekeys' => true
					];

					$details = (array_key_exists('details', $filter) && $filter['details'] == 1);

					if ($show_opdata) {
						$options['output'][] = 'opdata';
						$options['selectFunctions'] = ['itemid'];
					}

					if ($resolve_comments || $show_opdata || $details) {
						$options['output'][] = 'expression';
					}

					if ($show_opdata || $details) {
						$options['output'] = array_merge($options['output'], ['recovery_mode', 'recovery_expression']);
					}

					if ($resolve_comments) {
						$options['output'][] = 'comments';
					}

					$data['triggers'] += API::Trigger()->get($options);
				}

				foreach ($problems as $eventid => $problem) {
					if (!array_key_exists($problem['objectid'], $data['triggers'])) {
						unset($problems[$eventid]);
					}
				}

				$data['problems'] += $problems;
			}
		}
		while (count($data['problems']) < $limit + 1 && !$end_of_data);

		$data['problems'] = array_slice($data['problems'], 0, $limit + 1, true);

		if ($show_opdata && $data['triggers']) {
			$items = CArrayHelper::renameObjectsKeys(API::Item()->get([
				'output' => ['itemid', 'name_resolved', 'value_type', 'units'],
				'selectValueMap' => ['mappings'],
				'triggerids' => array_keys($data['triggers']),
				'webitems' => true,
				'preservekeys' => true
			]), ['name_resolved' => 'name']);

			foreach ($data['triggers'] as &$trigger) {
				foreach ($trigger['functions'] as $function) {
					$trigger['items'][] = $items[$function['itemid']];
				}
				unset($trigger['functions']);
			}
			unset($trigger);
		}

		return $data;
	}

	/**
	 * Adds a user or maintenance names of suppressed problems.
	 *
	 * @param array $problems
	 * @param array $problems[]['suppression_data']
	 * @param int   $problems[]['suppression_data'][]['maintenanceid']
	 * @param int   $problems[]['suppression_data'][]['userid']
	 */
	public static function addSuppressionNames(array &$problems) {
		$maintenanceids = [];
		$userids = [];

		foreach ($problems as $problem) {
			foreach ($problem['suppression_data'] as $data) {
				if ($data['maintenanceid'] != 0) {
					$maintenanceids[] = $data['maintenanceid'];
				}
				elseif ($data['userid'] != 0) {
					$userids[] = $data['userid'];
				}
			}
		}

		if ($maintenanceids) {
			$maintenances = API::Maintenance()->get([
				'output' => ['name'],
				'maintenanceids' => $maintenanceids,
				'preservekeys' => true
			]);
		}

		if ($userids) {
			$users = API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => $userids,
				'preservekeys' => true
			]);
		}

		foreach ($problems as &$problem) {
			foreach ($problem['suppression_data'] as &$data) {
				if ($data['maintenanceid'] != 0) {
					$data['maintenance_name'] = array_key_exists($data['maintenanceid'], $maintenances)
						? $maintenances[$data['maintenanceid']]['name']
						: _('Inaccessible maintenance');
				}

				if ($data['userid'] != 0) {
					$data['username'] = array_key_exists($data['userid'], $users)
						? getUserFullname($users[$data['userid']])
						: _('Inaccessible user');
				}
			}
			unset($data);
		}
		unset($problem);
	}

	/**
	 * Sort the problem list.
	 *
	 * @param array  $data              Problems and triggers data.
	 * @param array  $data['problems']  List of problems.
	 * @param array  $data['triggers']  List of triggers.
	 * @param int    $limit             Global search limit.
	 * @param string $sort              Sort field.
	 * @param string $sortorder         Sort order.
	 *
	 * @return array
	 */
	public static function sortData(array $data, int $limit, $sort, $sortorder): array {
		if (!$data['problems']) {
			return $data;
		}

		$last_problem = end($data['problems']);
		$data['problems'] = array_slice($data['problems'], 0, $limit, true);

		switch ($sort) {
			case 'host':
				$triggers_hosts_list = [];
				foreach (getTriggersHostsList($data['triggers']) as $triggerid => $trigger_hosts) {
					$triggers_hosts_list[$triggerid] = implode(', ', zbx_objectValues($trigger_hosts, 'name'));
				}

				foreach ($data['problems'] as &$problem) {
					$problem['host'] = $triggers_hosts_list[$problem['objectid']];
				}
				unset($problem);

				$sort_fields = [
					['field' => 'host', 'order' => $sortorder],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			case 'severity':
				$sort_fields = [
					['field' => 'severity', 'order' => $sortorder],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			case 'name':
				$sort_fields = [
					['field' => 'name', 'order' => $sortorder],
					['field' => 'objectid', 'order' => $sortorder],
					['field' => 'clock', 'order' => ZBX_SORT_DOWN],
					['field' => 'ns', 'order' => ZBX_SORT_DOWN]
				];
				break;

			default:
				$sort_fields = [
					['field' => 'clock', 'order' => $sortorder],
					['field' => 'ns', 'order' => $sortorder]
				];
		}
		CArrayHelper::sort($data['problems'], $sort_fields);

		$data['problems'][$last_problem['eventid']] = $last_problem;

		return $data;
	}

	/**
	 * @param array $eventids
	 *
	 * @return array
	 */
	private static function getExDataEvents(array $eventids) {
		$events = API::Event()->get([
			'output' => ['eventid', 'r_eventid', 'acknowledged'],
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity',
				'suppress_until', 'taskid'
			],
			'selectSuppressionData' => ['maintenanceid', 'userid', 'suppress_until'],
			'selectTags' => ['tag', 'value'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventids' => $eventids,
			'preservekeys' => true
		]);

		$r_eventids = [];

		foreach ($events as $event) {
			$r_eventids[$event['r_eventid']] = true;
		}
		unset($r_eventids[0]);

		$r_events = $r_eventids
			? API::Event()->get([
				'output' => ['clock', 'ns', 'correlationid', 'userid'],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'eventids' => array_keys($r_eventids),
				'preservekeys' => true
			])
			: [];

		foreach ($events as &$event) {
			if (array_key_exists($event['r_eventid'], $r_events)) {
				$event['r_clock'] = $r_events[$event['r_eventid']]['clock'];
				$event['r_ns'] = $r_events[$event['r_eventid']]['ns'];
				$event['correlationid'] = $r_events[$event['r_eventid']]['correlationid'];
				$event['userid'] = $r_events[$event['r_eventid']]['userid'];
			}
			else {
				$event['r_clock'] = 0;
				$event['r_ns'] = 0;
				$event['correlationid'] = 0;
				$event['userid'] = 0;
			}
		}
		unset($event);

		return $events;
	}

	/**
	 * @param array $eventids
	 *
	 * @return array
	 */
	private static function getExDataProblems(array $eventids) {
		return API::Problem()->get([
			'output' => ['eventid', 'r_eventid', 'r_clock', 'r_ns', 'correlationid', 'userid', 'acknowledged'],
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity',
				'suppress_until', 'taskid'
			],
			'selectSuppressionData' => ['maintenanceid', 'userid', 'suppress_until'],
			'selectTags' => ['tag', 'value'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'eventids' => $eventids,
			'recent' => true,
			'preservekeys' => true
		]);
	}

	/**
	 * @param array $data
	 * @param array $data['problems']
	 * @param array $data['triggers']
	 * @param array $filter
	 * @param int   $filter['details']
	 * @param int   $filter['show']
	 * @param int   $filter['show_opdata']
	 * @param bool  $resolve_comments
	 *
	 * @return array
	 */
	public static function makeData(array $data, array $filter, bool $resolve_comments = false) {
		// unset unused triggers
		$triggerids = [];

		foreach ($data['problems'] as $problem) {
			$triggerids[$problem['objectid']] = true;
		}

		foreach ($data['triggers'] as $triggerid => $trigger) {
			if (!array_key_exists($triggerid, $triggerids)) {
				unset($data['triggers'][$triggerid]);
			}
		}

		if (!$data['problems']) {
			return $data;
		}

		// resolve macros
		if ($filter['details'] == 1 || $filter['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE) {
			foreach ($data['triggers'] as &$trigger) {
				$trigger['expression_html'] = $trigger['expression'];
				$trigger['recovery_expression_html'] = $trigger['recovery_expression'];
			}
			unset($trigger);

			$data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['triggers'], [
				'html' => true,
				'resolve_usermacros' => true,
				'resolve_macros' => true,
				'sources' => ['expression_html', 'recovery_expression_html']
			]);

			// Sort items.
			if ($filter['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE) {
				$data['triggers'] = CMacrosResolverHelper::sortItemsByExpressionOrder($data['triggers']);
			}
		}

		if ($resolve_comments) {
			foreach ($data['problems'] as &$problem) {
				$trigger = $data['triggers'][$problem['objectid']];
				$problem['comments'] = CMacrosResolverHelper::resolveTriggerDescription(
					[
						'triggerid' => $problem['objectid'],
						'expression' => $trigger['expression'],
						'comments' => $trigger['comments'],
						'clock' => $problem['clock'],
						'ns' => $problem['ns']
					],
					['events' => true]
				);
			}
			unset($problem);

			foreach ($data['triggers'] as &$trigger) {
				unset($trigger['comments']);
			}
			unset($trigger);
		}

		// get additional data
		$eventids = array_keys($data['problems']);

		$problems_data = $filter['show'] == TRIGGERS_OPTION_ALL
			? self::getExDataEvents($eventids)
			: self::getExDataProblems($eventids);

		$correlationids = [];
		$userids = [];

		foreach ($data['problems'] as $eventid => &$problem) {
			if (array_key_exists($eventid, $problems_data)) {
				$problem_data = $problems_data[$eventid];

				$problem['r_eventid'] = $problem_data['r_eventid'];
				$problem['r_clock'] = $problem_data['r_clock'];
				$problem['r_ns'] = $problem_data['r_ns'];
				$problem['acknowledges'] = $problem_data['acknowledges'];
				$problem['tags'] = $problem_data['tags'];
				$problem['correlationid'] = $problem_data['correlationid'];
				$problem['userid'] = $problem_data['userid'];
				$problem['acknowledged'] = $problem_data['acknowledged'];
				$problem['suppression_data'] = $problem_data['suppression_data'];

				if ($problem['correlationid'] != 0) {
					$correlationids[$problem['correlationid']] = true;
				}
				if ($problem['userid'] != 0) {
					$userids[$problem['userid']] = true;
				}
			}
			else {
				unset($data['problems'][$eventid]);
			}
		}
		unset($problem);

		self::addSuppressionNames($data['problems']);

		// Possible performance improvement: one API call may be saved, if r_clock for problem will be used.
		$actions = getEventsActionsIconsData($data['problems'], $data['triggers']);
		$data['actions'] = $actions['data'];

		$data['correlations'] = $correlationids
			? API::Correlation()->get([
				'output' => ['name'],
				'correlationids' => array_keys($correlationids),
				'preservekeys' => true
			])
			: [];

		$userids = $userids + $actions['userids'];
		$data['users'] = $userids
			? API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => array_keys($userids + $actions['userids']),
				'preservekeys' => true
			])
			: [];

		return $data;
	}

	/**
	 * Add timeline breakpoint to a table if needed.
	 *
	 * @param CTableInfo $table
	 * @param array  $data                        Various table data.
	 * @param int    $data['last_clock']          Timestamp of the previous record.
	 * @param string $data['sortorder']           Order by which column is sorted.
	 * @param bool   $data['show_three_columns']  True if 3 columns should be displayed.
	 * @param bool   $data['show_two_columns']    True if 2 columns should be displayed.
	 * @param array  $problem                     Problem data.
	 * @param int    $problem['clock']            Timestamp of the current record.
	 * @param int    $problem['symptom_count']    Problem symptom count.
	 * @param bool   $nested                      True if this is a nested block.
	 * @param bool   $has_checkboxes              True if this is block is represented in Problem view with checkboxes.
	 *                                            It will add additional colspan for timeline breakpoint.
	 */
	public static function addTimelineBreakpoint(CTableInfo $table, $data, $problem, $nested, $has_checkboxes): void {
		if ($data['sortorder'] === ZBX_SORT_UP) {
			[$problem['clock'], $data['last_clock']] = [$data['last_clock'], $problem['clock']];
		}

		$breakpoint = null;
		$today = strtotime('today');
		$yesterday = strtotime('yesterday');
		$this_year = strtotime('first day of January '.date('Y', $today));

		if ($data['last_clock'] >= $today) {
			if ($problem['clock'] < $today) {
				$breakpoint = _('Today');
			}
			elseif (date('H', $data['last_clock']) != date('H', $problem['clock'])) {
				$breakpoint = date('H:00', $data['last_clock']);
			}
		}
		elseif ($data['last_clock'] >= $yesterday) {
			if ($problem['clock'] < $yesterday) {
				$breakpoint = _('Yesterday');
			}
		}
		elseif ($data['last_clock'] >= $this_year && $problem['clock'] < $this_year) {
			$breakpoint = date('Y', $data['last_clock']);
		}
		elseif (date('Ym', $data['last_clock']) != date('Ym', $problem['clock'])) {
			$breakpoint = getMonthCaption(date('m', $data['last_clock']));
		}

		if ($breakpoint !== null) {
			$colspan = 1;

			if ($data['show_three_columns']) {
				// Checkbox, symptom count, collapse/expand button and date column.
				$colspan = 3;
			}
			elseif ($data['show_two_columns']) {
				// Checkbox, symptom icon and date column.
				$colspan = 2;
			}

			if ($has_checkboxes) {
				$colspan++;
			}

			$breakpoint_col = (new CCol(new CTag('h4', true, $breakpoint)))->addClass(ZBX_STYLE_TIMELINE_DATE);

			if ($colspan > 1) {
				$breakpoint_col->setColSpan($colspan);
			}

			$row = (new CRow([
				$breakpoint_col,
				(new CCol())
					->addClass(ZBX_STYLE_TIMELINE_AXIS)
					->addClass(ZBX_STYLE_TIMELINE_DOT_BIG),
				(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD),
				(new CCol())->setColSpan($table->getNumCols() - $colspan - 2)
			]))->addClass(ZBX_STYLE_HOVER_NOBG);

			// Hide row and show when expanded for nested symptom problems.
			if ($nested && $problem['cause_eventid'] != 0) {
				$row
					->addClass('hidden')
					->setAttribute('data-cause-eventid', $problem['cause_eventid']);
			}

			$table->addRow($row);
		}
	}

	/**
	 * Process screen.
	 *
	 * @return string|CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'problem';

		$url = (new CUrl('zabbix.php'))->setArgument('action', 'problem.view');
		$args = [
			'sort' => $this->data['sort'],
			'sortorder' => $this->data['sortorder']
		] + $this->data['filter'];

		if ($this->data['filter']['show'] == TRIGGERS_OPTION_ALL) {
			$args['from'] = $this->timeline['from'];
			$args['to'] = $this->timeline['to'];
		}

		if (array_key_exists('severities', $args)) {
			$args['severities'] = array_combine($args['severities'], $args['severities']);
		}

		array_map([$url, 'setArgument'], array_keys($args), $args);

		$data = self::getData($this->data['filter'], $this->data['limit'], true);
		$data = self::sortData($data, $this->data['limit'], $this->data['sort'], $this->data['sortorder']);

		$paging = null;

		if ($this->data['action'] === 'problem.view' || $this->data['action'] === 'problem.view.refresh') {
			$paging = CPagerHelper::paginate($this->page, $data['problems'], ZBX_SORT_UP, $url);
		}

		$data = self::makeData($data, $this->data['filter'], true);

		if ($data['triggers']) {
			$triggerids = array_keys($data['triggers']);

			$db_triggers = API::Trigger()->get([
				'output' => [],
				'selectDependencies' => ['triggerid'],
				'triggerids' => $triggerids,
				'preservekeys' => true
			]);

			foreach ($data['triggers'] as $triggerid => &$trigger) {
				$trigger['dependencies'] = array_key_exists($triggerid, $db_triggers)
					? $db_triggers[$triggerid]['dependencies']
					: [];
			}
			unset($trigger);
		}

		$symptom_cause_eventids = [];
		$cause_eventids_with_symptoms = [];
		$do_causes_have_symptoms = false;
		$symptom_data = ['problems' => []];

		if ($data['problems']) {
			$triggers_hosts = getTriggersHostsList($data['triggers']);

			// Get symptom count for each problem.
			foreach ($data['problems'] as &$problem) {
				$problem['symptom_count'] = 0;
				$problem['symptoms'] = [];

				if ($problem['cause_eventid'] == 0) {
					$options = [
						'output' => ['objectid'],
						'filter' => ['cause_eventid' => $problem['eventid']]
					];

					$symptom_events = $this->data['filter']['show'] == TRIGGERS_OPTION_ALL
						? API::Event()->get($options)
						: API::Problem()->get($options + [
							'recent' => $this->data['filter']['show'] == TRIGGERS_OPTION_RECENT_PROBLEM
						]);

					if ($symptom_events) {
						$enabled_triggers = API::Trigger()->get([
							'output' => [],
							'triggerids' => array_column($symptom_events, 'objectid'),
							'filter' => ['status' => TRIGGER_STATUS_ENABLED],
							'preservekeys' => true
						]);

						$symptom_events = array_filter($symptom_events,
							static fn($event) => array_key_exists($event['objectid'], $enabled_triggers)
						);
						$problem['symptom_count'] = count($symptom_events);
					}

					if ($problem['symptom_count'] > 0) {
						$do_causes_have_symptoms = true;
						$cause_eventids_with_symptoms[] = $problem['eventid'];
					}
				}

				if ($problem['cause_eventid'] != 0) {
					// For CSV get cause names for these symptom events.
					$symptom_cause_eventids[] = $problem['cause_eventid'];
				}
			}
			unset($problem);
		}

		if ($cause_eventids_with_symptoms) {
			foreach ($cause_eventids_with_symptoms as $cause_eventid) {
				// Get all symptoms for given cause event ID.
				$_symptom_data = self::getData([
					'show_symptoms' => true,
					'show_suppressed' => true,
					'cause_eventid' => $cause_eventid,
					'show' => $this->data['filter']['show'],
					'details' => $this->data['filter']['details'],
					'show_opdata' => $this->data['filter']['show_opdata']
				], ZBX_PROBLEM_SYMPTOM_LIMIT, true);

				if ($_symptom_data['problems']) {
					$_symptom_data = self::sortData($_symptom_data, ZBX_PROBLEM_SYMPTOM_LIMIT, $this->data['sort'],
						$this->data['sortorder']
					);

					/*
					 * Since getData returns +1 more in order to show the "+" sign for paging or sortData should not cut
					 * off any excess problems, in order to display actual limit of symptoms, one more slice is
					 * necessary.
					 */
					$_symptom_data['problems'] = array_slice($_symptom_data['problems'], 0, ZBX_PROBLEM_SYMPTOM_LIMIT,
						true
					);

					// Filter does not matter.
					$_symptom_data = self::makeData($_symptom_data, [
						'show' => $this->data['filter']['show'],
						'details' => $this->data['filter']['details'],
						'show_opdata' => $this->data['filter']['show_opdata']
					], true);

					$data['users'] += $_symptom_data['users'];
					$data['correlations'] += $_symptom_data['correlations'];

					foreach ($_symptom_data['actions'] as $key => $actions) {
						$data['actions'][$key] += $actions;
					}

					if ($_symptom_data['triggers']) {
						$triggerids = array_keys($_symptom_data['triggers']);

						$db_triggers = API::Trigger()->get([
							'output' => [],
							'selectDependencies' => ['triggerid'],
							'triggerids' => $triggerids,
							'preservekeys' => true
						]);

						foreach ($_symptom_data['triggers'] as $triggerid => &$trigger) {
							$trigger['dependencies'] = array_key_exists($triggerid, $db_triggers)
								? $db_triggers[$triggerid]['dependencies']
								: [];
						}
						unset($trigger);

						// Add hosts from symptoms to the list.
						$triggers_hosts += getTriggersHostsList($_symptom_data['triggers']);

						// Store all known triggers in one place.
						$data['triggers'] += $_symptom_data['triggers'];
					}

					foreach ($data['problems'] as &$problem) {
						foreach ($_symptom_data['problems'] as $symptom) {
							if (bccomp($symptom['cause_eventid'], $problem['eventid']) == 0) {
								$problem['symptoms'][] = $symptom;
							}
						}
					}
					unset($problem);

					// Combine symptom problems, to show tags later at some point.
					$symptom_data['problems'] += $_symptom_data['problems'];
				}
			}
		}

		$show_opdata = $this->data['filter']['compact_view']
			? OPERATIONAL_DATA_SHOW_NONE
			: $this->data['filter']['show_opdata'];

		if ($this->data['action'] === 'problem.view' || $this->data['action'] === 'problem.view.refresh') {
			$form = (new CForm('post', 'zabbix.php'))
				->setId('problem_form')
				->setName('problem');

			$header_check_box = (new CColHeader(
				(new CCheckBox('all_eventids'))
					->onClick("checkAll('".$form->getName()."', 'all_eventids', 'eventids');")
			));

			$this->data['filter']['compact_view']
				? $header_check_box->addStyle('width: 16px;')
				: $header_check_box->addClass(ZBX_STYLE_CELL_WIDTH);

			$link = $url->getUrl();

			$show_timeline = ($this->data['sort'] === 'clock' && !$this->data['filter']['compact_view']
				&& $this->data['filter']['show_timeline']);

			$show_recovery_data = in_array($this->data['filter']['show'], [
				TRIGGERS_OPTION_RECENT_PROBLEM,
				TRIGGERS_OPTION_ALL
			]);

			$header = [$header_check_box];

			// There are cause events displayed on page that have symptoms. Maximum column count.
			if ($do_causes_have_symptoms) {
				$col_header_1 = (new CColHeader())->addClass(ZBX_STYLE_SECOND_COL);
				$col_header_2 = new CColHeader();

				if ($this->data['filter']['compact_view']) {
					$header[] = $col_header_1->addStyle('width: 18px;');
					$header[] = $col_header_2->addStyle('width: 18px;');
				}
				else {
					$header[] = $col_header_1->addStyle(ZBX_STYLE_CELL_WIDTH);
					$header[] = $col_header_2->addClass(ZBX_STYLE_CELL_WIDTH);
				}
			}
			// There might be cause events without symptoms or only symptoms.
			elseif ($symptom_cause_eventids) {
				$col_header = new CColHeader();

				if ($this->data['filter']['compact_view']) {
					$header[] = $col_header->addStyle('width: 16px;');
				}
				else {
					$header[] = $col_header->addClass(ZBX_STYLE_CELL_WIDTH);
				}
			}

			$header_clock = make_sorting_header(_('Time'), 'clock', $this->data['sort'], $this->data['sortorder'],
				$link
			);

			$this->data['filter']['compact_view']
				? $header_clock->addStyle('width: 132px;')
				: $header_clock->addClass(ZBX_STYLE_CELL_WIDTH);

			if ($show_timeline) {
				$header[] = $header_clock->addClass(ZBX_STYLE_RIGHT);
				$header[] = (new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH);
				$header[] = (new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH);
			}
			else {
				$header[] = $header_clock;
			}

			$table = (new CTableInfo())->setPageNavigation($paging);

			// Create table.
			if ($this->data['filter']['compact_view']) {
				if ($this->data['filter']['show_tags'] == SHOW_TAGS_NONE) {
					$tags_header = null;
				}
				else {
					$tags_header = (new CColHeader(_('Tags')));

					switch ($this->data['filter']['show_tags']) {
						case SHOW_TAGS_1:
							$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_1);
							break;
						case SHOW_TAGS_2:
							$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_2);
							break;
						case SHOW_TAGS_3:
							$tags_header->addClass(ZBX_STYLE_COLUMN_TAGS_3);
							break;
					}
				}

				$table->setHeader(array_merge($header, [
					make_sorting_header(_('Severity'), 'severity', $this->data['sort'], $this->data['sortorder'],
						$link
					)->addStyle('width: 120px;'),
					$show_recovery_data ? (new CColHeader(_('Recovery time')))->addStyle('width: 132px;') : null,
					$show_recovery_data ? (new CColHeader(_('Status')))->addStyle('width: 70px;') : null,
					(new CColHeader(_('Info')))->addStyle('width: 24px;'),
					make_sorting_header(_('Host'), 'host', $this->data['sort'], $this->data['sortorder'], $link)
						->addStyle('width: 42%;'),
					make_sorting_header(_('Problem'), 'name', $this->data['sort'], $this->data['sortorder'], $link)
						->addStyle('width: 58%;'),
					(new CColHeader(_('Duration')))->addStyle('width: 73px;'),
					(new CColHeader(_('Update')))->addStyle('width: 40px;'),
					(new CColHeader(_('Actions')))->addStyle('width: 89px;'),
					$tags_header
				]))
					->addClass(ZBX_STYLE_COMPACT_VIEW)
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);
			}
			else {
				$table->setHeader(array_merge($header, [
					make_sorting_header(_('Severity'), 'severity', $this->data['sort'], $this->data['sortorder'],
						$link
					),
					$show_recovery_data
						? (new CColHeader(_('Recovery time')))->addClass(ZBX_STYLE_CELL_WIDTH)
						: null,
					$show_recovery_data ? _('Status') : null,
					_('Info'),
					make_sorting_header(_('Host'), 'host', $this->data['sort'], $this->data['sortorder'], $link),
					make_sorting_header(_('Problem'), 'name', $this->data['sort'], $this->data['sortorder'], $link),
					($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY)
						? _('Operational data')
						: null,
					_('Duration'),
					_('Update'),
					_('Actions'),
					$this->data['filter']['show_tags'] ? _('Tags') : null
				]));
			}

			$tags = $this->data['filter']['show_tags']
				? makeTags($data['problems'] + $symptom_data['problems'], true, 'eventid',
					$this->data['filter']['show_tags'], array_key_exists('tags', $this->data['filter'])
						? $this->data['filter']['tags']
						: [],
					null, $this->data['filter']['tag_name_format'], $this->data['filter']['tag_priority']
				)
				: [];

			$triggers_hosts = $data['problems'] ? makeTriggersHostsList($triggers_hosts) : [];

			// Make trigger dependencies.
			$dependencies = $data['triggers'] ? getTriggerDependencies($data['triggers']) : [];

			$allowed = [
				'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
				'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
				'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
				'close' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS),
				'suppress_problems' => CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS),
				'rank_change' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_PROBLEM_RANKING)
			];

			$data += [
				'today' => strtotime('today'),
				'allowed' => $allowed,
				'dependencies' => $dependencies,
				'show_opdata' =>  $show_opdata,
				'show_three_columns' => $do_causes_have_symptoms,
				'show_two_columns' => (bool) $symptom_cause_eventids,
				'show_timeline' => $show_timeline,
				'last_clock' => 0,
				'show_recovery_data' => $show_recovery_data,
				'tags' => $tags,
				'triggers_hosts' => $triggers_hosts,
				'sortorder' => $this->data['sortorder'],
				'filter' => $this->data['filter']
			];

			// Add problems to table.
			self::addProblemsToTable($table, $data['problems'], $data, false);

			$footer = new CActionButtonList('action', 'eventids', [
				'acknowledge.edit' => [
					'content' => (new CSimpleButton(_('Mass update')))
						->addClass(ZBX_STYLE_BTN_ALT)
						->addClass('js-massupdate-problem')
						->addClass('js-no-chkbxrange')
						->setEnabled($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
							|| $allowed['close'] || $allowed['suppress_problems'] || $allowed['rank_change']
						)
				]
			], 'problem');

			return $this->getOutput($form->addItem([$table, $footer]), false, $this->data);
		}

		/*
		 * Search limit performs +1 selection to know if limit was exceeded, this will assure that CSV has
		 * "search_limit" records at most.
		 */
		array_splice($data['problems'], $this->data['limit']);

		$csv = [];

		$csv[] = array_filter([
			_('Severity'),
			_('Time'),
			_('Recovery time'),
			_('Status'),
			_('Host'),
			_('Problem'),
			$symptom_cause_eventids ? _('Cause') : null,
			($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) ? _('Operational data') : null,
			_('Duration'),
			_('Ack'),
			_('Actions'),
			_('Tags')
		]);

		// Make tags from all events.
		$tags = makeTags($data['problems'] + $symptom_data['problems'], false);

		// Get cause event names for symptoms.
		$causes = [];
		if ($symptom_cause_eventids) {
			$options = [
				'output' => ['cause_eventid', 'name'],
				'eventids' => $symptom_cause_eventids,
				'preservekeys' => true
			];

			$causes = ($this->data['filter']['show'] == TRIGGERS_OPTION_ALL)
				? API::Event()->get($options)
				: API::Problem()->get($options);
		}

		foreach ($data['problems'] as $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			$in_closing = false;
			if ($problem['r_eventid'] == 0) {
				$in_closing = hasEventCloseAction($problem['acknowledges']);
			}

			$value_str = getEventStatusString($in_closing, $problem);

			$hosts = [];
			foreach ($triggers_hosts[$trigger['triggerid']] as $trigger_host) {
				$hosts[] = $trigger_host['name'];
			}

			// operational data
			$opdata = null;
			if ($show_opdata != OPERATIONAL_DATA_SHOW_NONE) {
				if ($trigger['opdata'] === '') {
					if ($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) {
						$opdata = self::getLatestValues($trigger['items'], false);
					}
				}
				else {
					$opdata = CMacrosResolverHelper::resolveTriggerOpdata(
						[
							'triggerid' => $trigger['triggerid'],
							'expression' => $trigger['expression'],
							'opdata' => $trigger['opdata'],
							'clock' => ($problem['r_eventid'] != 0) ? $problem['r_clock'] : $problem['clock'],
							'ns' => ($problem['r_eventid'] != 0) ? $problem['r_ns'] : $problem['ns']
						],
						['events' => true]
					);
				}
			}

			$actions_performed = [];
			if ($data['actions']['messages'][$problem['eventid']]['count'] > 0) {
				$actions_performed[] = _('Messages').
					' ('.$data['actions']['messages'][$problem['eventid']]['count'].')';
			}
			if ($data['actions']['severities'][$problem['eventid']]['count'] > 0) {
				$actions_performed[] = _('Severity changes');
			}

			if ((bool) array_column($problem['suppression_data'], 'userid')) {
				$actions_performed[] = _('Suppressed');
			}
			elseif ($data['actions']['suppressions'][$problem['eventid']]['count'] > 0) {
				$actions_performed[] = _('Unsuppressed');
			}

			if ($data['actions']['actions'][$problem['eventid']]['count'] > 0) {
				$actions_performed[] = _('Actions').' ('.$data['actions']['actions'][$problem['eventid']]['count'].')';
			}

			$row = [];

			$row[] = CSeverityHelper::getName((int) $problem['severity']);
			$row[] = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);
			$row[] = ($problem['r_eventid'] != 0) ? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']) : '';
			$row[] = $value_str;
			$row[] = implode(', ', $hosts);
			$row[] = ($show_opdata == OPERATIONAL_DATA_SHOW_WITH_PROBLEM && $trigger['opdata'] !== '')
				? $problem['name'].' ('.$opdata.')'
				: $problem['name'];

			if ($symptom_cause_eventids) {
				$row[] = $problem['cause_eventid'] != 0 ? $causes[$problem['cause_eventid']]['name'] : '';
			}

			if ($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) {
				$row[] = $opdata;
			}

			$row[] = ($problem['r_eventid'] != 0)
				? zbx_date2age($problem['clock'], $problem['r_clock'])
				: zbx_date2age($problem['clock']);
			$row[] = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED) ? _('Yes') : _('No');
			$row[] = implode(', ', $actions_performed);
			$row[] = implode(', ', $tags[$problem['eventid']]);

			$csv[] = $row;
		}

		return zbx_toCSV($csv);
	}

	/**
	 * Add problems and symptoms to table.
	 *
	 * @param CTableInfo $table                                 Table object to which problems are added to.
	 * @param array      $problems                              List of problems.
	 * @param array      $data                                  Additional data to build the table.
	 * @param array      $data['triggers']                      List of triggers.
	 * @param int        $data['today']                         Timestamp of today's date.
	 * @param array      $data['users']                         List of users.
	 * @param array      $data['correlations']                  List of event correlations.
	 * @param array      $data['dependencies']                  List of trigger dependencies.
	 * @param array      $data['filter']                        Problem filter.
	 * @param int        $data['filter']['show']                "Show" filter option.
	 * @param int        $data['filter']['show_suppressed']     "Show suppressed problems" filter option.
	 * @param int        $data['filter']['highlight_row']       "Highlight whole row" filter option.
	 * @param int        $data['filter']['show_tags']           "Show tags" filter option.
	 * @param int        $data['filter']['compact_view']        "Compact view" filter option.
	 * @param int        $data['filter']['details']             "Show details" filter option.
	 * @param int        $data['show_opdata']                   "Show operational data" filter option.
	 * @param bool       $data['show_timeline']                 "Show timeline" filter option.
	 * @param bool       $data['show_three_columns']            True if 3 columns should be displayed.
	 * @param bool       $data['show_two_columns']              True if 2 columns should be displayed.
	 * @param int        $data['last_clock']                    Problem time. Used to show timeline breaks.
	 * @param int        $data['sortorder']                     Sort problems in ascending or descending order.
	 * @param array      $data['allowed']                       An array of user role rules.
	 * @param bool       $data['allowed']['close']              Whether user is allowed to close problems.
	 * @param bool       $data['allowed']['add_comments']       Whether user is allowed to add problems comments.
	 * @param bool       $data['allowed']['change_severity']    Whether user is allowed to change problems severity.
	 * @param bool       $data['allowed']['acknowledge']        Whether user is allowed to acknowledge problems.
	 * @param bool       $data['allowed']['suppress_problems']  Whether user is allowed to manually suppress/unsuppress
	 *                                                          problems.
	 * @param bool       $data['allowed']['rank_change']        Whether user is allowed to change problem ranking.
	 * @param bool       $data['show_recovery_data']            True if filter "Show" option is "Recent problems"
	 *                                                          or History.
	 * @param array      $data['triggers_hosts']                List of trigger hosts.
	 * @param array      $data['actions']                       List of actions.
	 * @param array      $data['tags']                          List of tags.
	 * @param bool       $nested                                If true, show the symptom rows with indentation.
	 */
	private static function addProblemsToTable(CTableInfo $table, array $problems, array $data, $nested): void {
		foreach ($problems as $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			$cell_clock = ($problem['clock'] >= $data['today'])
				? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
				: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);
			$cell_clock = new CCol(new CLink($cell_clock,
				(new CUrl('tr_events.php'))
					->setArgument('triggerid', $problem['objectid'])
					->setArgument('eventid', $problem['eventid'])
			));

			if ($problem['r_eventid'] != 0) {
				$cell_r_clock = ($problem['r_clock'] >= $data['today'])
					? zbx_date2str(TIME_FORMAT_SECONDS, $problem['r_clock'])
					: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['r_clock']);
				$cell_r_clock = (new CCol(new CLink($cell_r_clock,
					(new CUrl('tr_events.php'))
						->setArgument('triggerid', $problem['objectid'])
						->setArgument('eventid', $problem['eventid'])
				)))
					->addClass(ZBX_STYLE_NOWRAP)
					->addClass(ZBX_STYLE_RIGHT);
			}
			else {
				$cell_r_clock = '';
			}

			$in_closing = false;

			if ($problem['r_eventid'] != 0) {
				$value = TRIGGER_VALUE_FALSE;
				$value_clock = $problem['r_clock'];
				$can_be_closed = false;
			}
			else {
				$in_closing = hasEventCloseAction($problem['acknowledges']);
				$can_be_closed = ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
					&& $data['allowed']['close'] && !$in_closing
				);
				$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
				$value_clock = $in_closing ? time() : $problem['clock'];
			}

			$value_str = getEventStatusString($in_closing, $problem);
			$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
			$cell_status = new CSpan($value_str);

			if (isEventUpdating($in_closing, $problem)) {
				$cell_status->addClass('js-blink');
			}

			// Add colors and blinking to span depending on configuration and trigger parameters.
			addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

			// Info.
			$info_icons = [];

			if ($data['filter']['show'] == TRIGGERS_OPTION_IN_PROBLEM) {
				$info_icons[] = getEventStatusUpdateIcon($problem);
			}

			if ($problem['r_eventid'] != 0) {
				if ($problem['correlationid'] != 0) {
					$info_icons[] = makeInformationIcon(
						array_key_exists($problem['correlationid'], $data['correlations'])
							? _s('Resolved by event correlation rule "%1$s".',
								$data['correlations'][$problem['correlationid']]['name']
							)
							: _('Resolved by event correlation rule.')
					);
				}
				elseif ($problem['userid'] != 0) {
					$info_icons[] = makeInformationIcon(
						array_key_exists($problem['userid'], $data['users'])
							? _s('Resolved by user "%1$s".', getUserFullname($data['users'][$problem['userid']]))
							: _('Resolved by inaccessible user.')
					);
				}
			}

			if (array_key_exists('suppression_data', $problem)) {
				if (count($problem['suppression_data']) == 1
						&& $problem['suppression_data'][0]['maintenanceid'] == 0
						&& isEventRecentlyUnsuppressed($problem['acknowledges'], $unsuppression_action)) {
					// Show blinking button if the last manual suppression was recently revoked.
					$user_unsuppressed = array_key_exists($unsuppression_action['userid'], $data['users'])
						? getUserFullname($data['users'][$unsuppression_action['userid']])
						: _('Inaccessible user');

					$info_icons[] = (new CButtonIcon(ZBX_ICON_EYE))
						->addClass(ZBX_STYLE_COLOR_ICON)
						->addClass('js-blink')
						->setHint(_s('Unsuppressed by: %1$s', $user_unsuppressed));
				}
				elseif ($problem['suppression_data']) {
					$info_icons[] = makeSuppressedProblemIcon($problem['suppression_data'], false);
				}
				elseif (isEventRecentlySuppressed($problem['acknowledges'], $suppression_action)) {
					// Show blinking button if suppression was made but is not yet processed by server.
					$info_icons[] = makeSuppressedProblemIcon([[
						'suppress_until' => $suppression_action['suppress_until'],
						'username' => array_key_exists($suppression_action['userid'], $data['users'])
							? getUserFullname($data['users'][$suppression_action['userid']])
							: _('Inaccessible user')
					]], true);
				}
			}

			if ($data['filter']['compact_view'] && $data['filter']['show_suppressed'] && count($info_icons) > 1) {
				$cell_info = (new CButtonIcon(ZBX_ICON_MORE))->setHint(makeInformationList($info_icons));
			}
			else {
				$cell_info = makeInformationList($info_icons);
			}

			$description = array_key_exists($trigger['triggerid'], $data['dependencies'])
				? makeTriggerDependencies($data['dependencies'][$trigger['triggerid']])
				: [];
			$description[] = (new CLinkAction($problem['name']))
				->addClass(ZBX_STYLE_WORDBREAK)
				->setMenuPopup(CMenuPopupHelper::getTrigger([
					'triggerid' => $trigger['triggerid'],
					'backurl' => (new CUrl('zabbix.php'))
						->setArgument('action', 'problem.view')
						->getUrl(),
					'eventid' => $problem['eventid'],
					'show_rank_change_cause' => true,
					'show_rank_change_symptom' => true
				]));

			$opdata = null;

			if ($data['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE) {
				if ($trigger['opdata'] === '') {
					if ($data['show_opdata'] == OPERATIONAL_DATA_SHOW_SEPARATELY) {
						$opdata = (new CCol(self::getLatestValues($trigger['items'])))->addClass('latest-values');
					}
				}
				else {
					$opdata = (new CSpan(CMacrosResolverHelper::resolveTriggerOpdata(
						[
							'triggerid' => $trigger['triggerid'],
							'expression' => $trigger['expression'],
							'opdata' => $trigger['opdata'],
							'clock' => ($problem['r_eventid'] != 0) ? $problem['r_clock'] : $problem['clock'],
							'ns' => ($problem['r_eventid'] != 0) ? $problem['r_ns'] : $problem['ns']
						],
						[
							'events' => true,
							'html' => true
						]
					)))->addClass('opdata');

					if ($data['show_opdata'] == OPERATIONAL_DATA_SHOW_WITH_PROBLEM) {
						$description[] = ' (';
						$description[] = $opdata;
						$description[] = ')';
					}
				}
			}

			$description[] = ($problem['comments'] !== '') ? makeDescriptionIcon($problem['comments']) : null;

			if ($data['filter']['details'] == 1) {
				$description[] = BR();

				if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
					$description[] = [_('Problem'), ': ', (new CDiv($trigger['expression_html']))
						->addClass(ZBX_STYLE_WORDBREAK), BR()];
					$description[] = [_('Recovery'), ': ', (new CDiv($trigger['recovery_expression_html']))
						->addClass(ZBX_STYLE_WORDBREAK)];
				}
				else {
					$description[] = (new CDiv($trigger['expression_html']))->addClass(ZBX_STYLE_WORDBREAK);
				}
			}

			$checkbox_col = new CCol(new CCheckBox('eventids['.$problem['eventid'].']', $problem['eventid']));
			$empty_col = new CCol();
			$symptom_col = (new CCol(new CIcon(ZBX_ICON_ARROW_TOP_RIGHT, _('Symptom'))));

			if ($data['show_timeline']) {
				$checkbox_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
				$empty_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
				$symptom_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
			}

			// Build rows and columns.
			if ($problem['cause_eventid'] == 0) {
				// First column checkbox for cause event.
				$row = new CRow($checkbox_col);

				if ($problem['symptom_count'] > 0) {
					// Show symptom counter and collapse/expand button.
					$symptom_count_span = (new CSpan($problem['symptom_count']))
						->addClass(ZBX_STYLE_ENTITY_COUNT)
						->addStyle('max-width: 3ch;');

					if ($problem['symptom_count'] >= 1000) {
						$symptom_count_span->setHint($problem['symptom_count']);
					}

					$symptom_count_col = (new CCol($symptom_count_span))->addClass(ZBX_STYLE_SECOND_COL);

					$collapse_expand_col = new CCol(
						(new CButtonIcon(ZBX_ICON_CHEVRON_DOWN, _('Expand')))
							->addClass(ZBX_STYLE_COLLAPSED)
							->setAttribute('data-eventid', $problem['eventid'])
							->setAttribute('data-action', 'show_symptoms')
					);

					if ($data['show_timeline']) {
						$symptom_count_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
						$collapse_expand_col->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);
					}

					$row
						->addClass('problem-row')
						->addItem([$symptom_count_col, $collapse_expand_col]);
				}
				else {
					if ($data['show_three_columns']) {
						/*
						 * Page has cause events and some of them had collapse/expand button. This event does not. So
						 * instead of number and icon show two more empty columns where the middle column has no padding
						 * from both sides. Retain zero the paddings even if columns are empty, they too cause extra
						 * width.
						 */
						$row
							->addClass('problem-row')
							->addItem([
								$empty_col->addClass(ZBX_STYLE_SECOND_COL),
								$empty_col
							]);
					}
					elseif ($data['show_two_columns']) {
						/*
						 * Page has cause events but none of them had collapse/expand button. But page has
						 * "Show symptoms" filter enabled, so stand-alone events are shown. So only one empty column is
						 * required which has no padding from both sides.
						 */
						$row
							->addClass('problem-row')
							->addItem($empty_col->addClass(ZBX_STYLE_SECOND_COL));
					}
					// Otherwise page has only cause events with no symptoms at all.
				}
			}
			else {
				if ($nested) {
					/*
					 * If this is a nested block (when collapse/expand button is pressed), the first column should be
					 * empty. Second column is checkbox for the nested symptom event. After that, the third column is
					 * Symptom icon. The row is hidden by default.
					 */
					$checkbox_col->addClass(ZBX_STYLE_SECOND_COL);

					$row = (new CRow([
						$empty_col,
						$checkbox_col,
						$symptom_col
					]))
						->addClass(ZBX_STYLE_PROBLEM_NESTED)
						->addClass(ZBX_STYLE_PROBLEM_NESTED_SMALL)
						->addClass('hidden')
						->setAttribute('data-cause-eventid', $problem['cause_eventid']);
				}
				else {
					// This is a stand-alone symptom event. First column is checkbox, followed by a Symptom icon.
					$row = (new CRow([
						$checkbox_col,
						$symptom_col->addClass(ZBX_STYLE_SECOND_COL)
					]))->addClass('problem-row');
				}

				/*
				 * Page has cause events and some of them had collapse/expand button and page also has "Show symptoms"
				 * filter enabled, but this event is stand-alone symptom event, so after the symptom icon, show empty
				 * column.
				 */
				if (!$nested && $data['show_three_columns']) {
					$row->addItem($empty_col);
				}
			}

			if ($data['show_timeline']) {
				if ($data['last_clock'] != 0) {
					self::addTimelineBreakpoint($table, $data, $problem, $nested, true);
				}
				$data['last_clock'] = $problem['clock'];

				$row->addItem([
					$cell_clock->addClass(ZBX_STYLE_TIMELINE_DATE),
					(new CCol())
						->addClass(ZBX_STYLE_TIMELINE_AXIS)
						->addClass(ZBX_STYLE_TIMELINE_DOT),
					(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD)
				]);
			}
			else {
				$row->addItem($cell_clock
						->addClass(ZBX_STYLE_NOWRAP)
						->addClass(ZBX_STYLE_RIGHT)
				);
			}

			$problem_update_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'acknowledge.edit')
				->setArgument('eventids[]', $problem['eventid'])
				->getUrl();

			// Create acknowledge link.
			$problem_update_link = ($data['allowed']['add_comments'] || $data['allowed']['change_severity']
					|| $data['allowed']['acknowledge'] || $can_be_closed || $data['allowed']['suppress_problems']
					|| $data['allowed']['rank_change'])
				? (new CLink(_('Update'), $problem_update_url))->addClass(ZBX_STYLE_LINK_ALT)
				: new CSpan(_('Update'));

			$row->addItem([
				CSeverityHelper::makeSeverityCell((int) $problem['severity'], null, $value == TRIGGER_VALUE_FALSE),
				$data['show_recovery_data'] ? $cell_r_clock : null,
				$data['show_recovery_data'] ? $cell_status : null,
				$cell_info,
				$data['filter']['compact_view']
					? (new CDiv($data['triggers_hosts'][$trigger['triggerid']]))->addClass(ZBX_STYLE_ACTION_CONTAINER)
					: $data['triggers_hosts'][$trigger['triggerid']],
				$data['filter']['compact_view']
					? (new CDiv($description))->addClass(ZBX_STYLE_ACTION_CONTAINER)
					: $description,
				($data['show_opdata'] == OPERATIONAL_DATA_SHOW_SEPARATELY)
					? $opdata->addClass(ZBX_STYLE_WORDBREAK)
					: null,
				($problem['r_eventid'] != 0)
					? zbx_date2age($problem['clock'], $problem['r_clock'])
					: zbx_date2age($problem['clock']),
				$problem_update_link,
				makeEventActionsIcons($problem['eventid'], $data['actions'], $data['users'], $is_acknowledged),
				$data['filter']['show_tags'] ? $data['tags'][$problem['eventid']] : null
			]);

			// Add table row.
			$table->addRow($row, ($data['filter']['highlight_row'] && $value == TRIGGER_VALUE_TRUE)
					? self::getSeverityFlhStyle($problem['severity'])
					: null
			);

			if ($problem['cause_eventid'] == 0 && $problem['symptoms']) {
				self::addProblemsToTable($table, $problem['symptoms'], $data, true);

				self::addSymptomLimitToTable($table, $problem, $data);
			}
		}
	}

	/**
	 * Add symptom limit row at the end of symptom block.
	 *
	 * @param CTableInfo $table                                 Table object to which problems are added to.
	 * @param array      $problem                               Problem data.
	 * @param string     $problem['eventid']                    Problem ID.
	 * @param int        $problem['symptom_count']              Problem symptom count.
	 * @param array      $data                                  Additional data.
	 * @param bool       $data['show_timeline']                 "Show timeline" filter option.
	 * @param bool       $data['show_three_columns']            True if 3 columns should be displayed.
	 * @param bool       $data['show_two_columns']              True if 2 columns should be displayed.
	 */
	public static function addSymptomLimitToTable(CTableInfo $table, array $problem, array $data): void {
		if ($problem['symptom_count'] > ZBX_PROBLEM_SYMPTOM_LIMIT) {
			$row = (new CRow())
				->addClass(ZBX_STYLE_NO_HOVER_PROBLEM_NESTED)
				->addClass('hidden')
				->setAttribute('data-cause-eventid', $problem['eventid']);

			$symptom_limit_col = (new CCol(
				(new CDiv(
					(new CDiv(
						_s('Displaying %1$s of %2$s found', ZBX_PROBLEM_SYMPTOM_LIMIT, $problem['symptom_count'])
					))->addClass(ZBX_STYLE_TABLE_STATS)
				))->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
			))->addClass(ZBX_STYLE_PROBLEM_NESTED_SMALL);

			if ($data['show_timeline']) {
				$colspan = 1;
				if ($data['show_three_columns']) {
					$colspan = 3;
				}
				elseif ($data['show_two_columns']) {
					$colspan = 3;
				}

				if (!($table instanceof widgets\problems\includes\WidgetProblems)) {
					$colspan++;
				}

				$empty_col = (new CCol())->addClass(ZBX_STYLE_PROBLEM_EXPAND_TD);

				if ($colspan > 1) {
					$empty_col->setColSpan($colspan);
				}

				$row->addItem([
					$empty_col,
					(new CCol())->addClass(ZBX_STYLE_TIMELINE_AXIS),
					(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD),
					$symptom_limit_col->setColSpan($table->getNumCols() - $colspan - 2)
				]);
			}
			else {
				$row->addItem(
					$symptom_limit_col->setColSpan($table->getNumCols())
				);
			}

			$table->addRow($row);
		}
	}

	/**
	 * Get item latest values.
	 *
	 * @param array $items    An array of trigger items.
	 * @param bool  $html
	 *
	 * @return array|string
	 */
	public static function getLatestValues(array $items, bool $html = true) {
		$latest_values = [];

		$items = zbx_toHash($items, 'itemid');
		$history_values = Manager::History()->getLastValues($items, 1, timeUnitToSeconds(CSettingsHelper::get(
			CSettingsHelper::HISTORY_PERIOD
		)));

		if ($html) {
			$hint_table = (new CTable())->addClass(ZBX_STYLE_LIST_TABLE);
		}

		foreach ($items as $itemid => $item) {
			if (array_key_exists($itemid, $history_values)) {
				$last_value = reset($history_values[$itemid]);
				$last_value['original_value'] = $last_value['value'];

				if ($item['value_type'] != ITEM_VALUE_TYPE_BINARY) {
					$last_value['value'] = formatHistoryValue(str_replace(["\r\n", "\n"], [" "], $last_value['value']),
						$item
					);
				}
			}
			else {
				$last_value = [
					'itemid' => null,
					'clock' => null,
					'value' => UNRESOLVED_MACRO_STRING,
					'original_value' => UNRESOLVED_MACRO_STRING,
					'ns' => null
				];
			}

			if ($html) {
				$hint_table->addRow([
					(new CCol($item['name']))->addStyle('max-width: '.ZBX_OPDATA_HINTBOX_COLUMN_MAX_WIDTH.'px'),
					(new CCol(
						($last_value['clock'] !== null)
							? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $last_value['clock'])
							: UNRESOLVED_MACRO_STRING
					))->addClass(ZBX_STYLE_NOWRAP),
					(new CCol($item['value_type'] == ITEM_VALUE_TYPE_BINARY
						? italic(_('binary value'))->addClass(ZBX_STYLE_GREY)
						: $last_value['original_value']
					))->addStyle('max-width: '.ZBX_OPDATA_HINTBOX_COLUMN_MAX_WIDTH.'px'),
					(new CCol(
						($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64)
							? (CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
							? new CLink(_('Graph'), (new CUrl('history.php'))
								->setArgument('action', HISTORY_GRAPH)
								->setArgument('itemids[]', $itemid)
								->getUrl()
							)
							: _('Graph'))
							: (CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
							? new CLink(_('History'), (new CUrl('history.php'))
								->setArgument('action', HISTORY_VALUES)
								->setArgument('itemids[]', $itemid)
								->getUrl()
							)
							: _('History'))
					))->addClass(ZBX_STYLE_NOWRAP)
				]);

				$latest_values[] = (new CLinkAction(
					$item['value_type'] == ITEM_VALUE_TYPE_BINARY
						? _('binary value')
						: $last_value['value']
				))
					->addClass('hint-item')
					->setAttribute('data-hintbox', '1');
				$latest_values[] = ', ';
			}
			else {
				$latest_values[] = $item['value_type'] == ITEM_VALUE_TYPE_BINARY
					? UNRESOLVED_MACRO_STRING
					: $last_value['original_value'];
			}
		}

		if ($html) {
			$hint_container = (new CDiv())
				->addClass(ZBX_STYLE_HINTBOX_WRAP)
				->addItem($hint_table);

			array_pop($latest_values);
			array_unshift($latest_values, (new CDiv())
				->addClass('main-hint')
				->setHint($hint_container)
			);

			return $latest_values;
		}

		return implode(', ', $latest_values);
	}

	/**
	 * Get trigger severity full line height css style name.
	 *
	 * @param int $severity  Trigger severity.
	 *
	 * @return string|null
	 */
	private static function getSeverityFlhStyle($severity) {
		switch ($severity) {
			case TRIGGER_SEVERITY_DISASTER:
				return ZBX_STYLE_FLH_DISASTER_BG;
			case TRIGGER_SEVERITY_HIGH:
				return ZBX_STYLE_FLH_HIGH_BG;
			case TRIGGER_SEVERITY_AVERAGE:
				return ZBX_STYLE_FLH_AVERAGE_BG;
			case TRIGGER_SEVERITY_WARNING:
				return ZBX_STYLE_FLH_WARNING_BG;
			case TRIGGER_SEVERITY_INFORMATION:
				return ZBX_STYLE_FLH_INFO_BG;
			case TRIGGER_SEVERITY_NOT_CLASSIFIED:
				return ZBX_STYLE_FLH_NA_BG;
			default:
				return null;
		}
	}
}
