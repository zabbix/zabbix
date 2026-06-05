<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
	 * @return array|string
	 */
	private static function getDataEvents(array $options): array|string {
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
	 * @return array|string
	 */
	private static function getDataProblems(array $options): array|string {
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
	 *        array  $filter['groupids']              (optional)
	 *        array  $filter['exclude_groupids']      (optional)
	 *        array  $filter['hostids']               (optional)
	 *        array  $filter['triggerids']            (optional)
	 *        array  $filter['inventory']             (optional)
	 *        string $filter['inventory'][]['field']
	 *        string $filter['inventory'][]['value']
	 *        string $filter['name']                  (optional)
	 *        int    $filter['show']                  TRIGGERS_OPTION_*
	 *        int    $filter['from']                  (optional) usable together with 'to' and only for
	 *                                                           TRIGGERS_OPTION_ALL, timestamp.
	 *        int    $filter['to']                    (optional) usable together with 'from' and only for
	 *                                                           TRIGGERS_OPTION_ALL, timestamp.
	 *        int    $filter['age_state']             (optional) usable together with 'age' and only for
	 *                                                           TRIGGERS_OPTION_(RECENT|IN)_PROBLEM
	 *        int    $filter['age']                   (optional) usable together with 'age_state' and only for
	 *                                                           TRIGGERS_OPTION_(RECENT|IN)_PROBLEM
	 *        array  $filter['severities']            (optional)
	 *        int    $filter['acknowledgement_status'] (optional)
	 *        array  $filter['tags']                  (optional)
	 *        string $filter['tags'][]['tag']
	 *        string $filter['tags'][]['value']
	 *        int    $filter['show_symptoms']         (optional)
	 *        int    $filter['show_suppressed']       (optional)
	 *        array  $filter['cause_eventid']         (optional)
	 * @param array  $column_options
	 *        int    $column_options ['show_opdata']  (optional)
	 *        int    $column_options ['details']      (optional)
	 * @param int    $limit
	 * @param bool   $resolve_comments
	 *
	 * @return array
	 */
	public static function getData(array $filter, array $column_options, int $limit,
			bool $resolve_comments = false): array {

		$filter_groupids = array_key_exists('groupids', $filter) && $filter['groupids']
			? getSubGroups($filter['groupids'])
			: null;
		$filter_hostids = array_key_exists('hostids', $filter) && $filter['hostids'] ? $filter['hostids'] : null;
		$filter_triggerids = array_key_exists('triggerids', $filter) && $filter['triggerids']
			? $filter['triggerids']
			: null;
		$show_opdata = $column_options['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE;

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

			$problems = $filter['show'] == TRIGGERS_OPTION_ALL
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
						'skipDependent' => $filter['show'] == TRIGGERS_OPTION_ALL ? null : true,
						'preservekeys' => true
					];

					$details = array_key_exists('details', $column_options) && $column_options['details'] == 1;
					$custom_text = array_key_exists('custom_text', $column_options);

					if ($show_opdata) {
						$options['output'][] = 'opdata';
						$options['selectFunctions'] = ['itemid'];
					}

					if ($resolve_comments || $show_opdata || $details || $custom_text) {
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
	 *        array $data['problems']
	 *        array $data['triggers']
	 * @param array $filter
	 *        int   $filter['show']
	 * @param array $column_options
	 *        int   $column_options['details']
	 *        int   $column_options['show_opdata']
	 * @param bool  $resolve_comments
	 *
	 * @return array
	 */
	public static function makeData(array $data, array $filter, array $column_options = [],
			bool $resolve_comments = false): array {

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

		$show_opdata = array_key_exists('show_opdata', $column_options)
			&& $column_options['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE;

		// resolve macros
		if ($column_options['details'] == 1 || $show_opdata) {
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
			if ($show_opdata) {
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
					->setAttribute('data-hintbox', '1')
					->addClass(ZBX_STYLE_NO_INDENT);
				$latest_values[] = ', ';
			}
			else {
				$latest_values[] = $item['value_type'] == ITEM_VALUE_TYPE_BINARY
					? UNRESOLVED_MACRO_STRING
					: $last_value['original_value'];
			}
		}

		if ($html) {
			array_pop($latest_values);
			array_unshift($latest_values, (new CDiv())
				->addClass('main-hint')
				->setHint($hint_table)
			);
			return $latest_values;
		}

		return implode(', ', $latest_values);
	}
}
