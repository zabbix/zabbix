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
	 * @static
	 *
	 * @return array
	 */
	private static function getDataEvents(array $options) {
		return API::Event()->get([
			'output' => ['eventid', 'objectid', 'clock', 'ns', 'name', 'severity'],
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
	 * @static
	 *
	 * @return array
	 */
	private static function getDataProblems(array $options) {
		return API::Problem()->get([
			'output' => ['eventid', 'objectid', 'clock', 'ns', 'name', 'severity'],
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
	 * @param int    $filter['unacknowledged']        (optional)
	 * @param array  $filter['tags']                  (optional)
	 * @param string $filter['tags'][]['tag']
	 * @param string $filter['tags'][]['value']
	 * @param int    $filter['show_suppressed']       (optional)
	 * @param int    $filter['show_opdata']           (optional)
	 * @param bool   $resolve_comments
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function getData(array $filter, bool $resolve_comments = false) {
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
				'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
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
			if (array_key_exists('severities', $filter)) {
				$filter_severities = implode(',', $filter['severities']);
				$all_severities = implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1));

				if ($filter_severities !== '' && $filter_severities !== $all_severities) {
					$options['severities'] = $filter['severities'];
				}
			}
			if (array_key_exists('unacknowledged', $filter) && $filter['unacknowledged']) {
				$options['acknowledged'] = false;
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

			$problems = ($filter['show'] == TRIGGERS_OPTION_ALL)
				? self::getDataEvents($options)
				: self::getDataProblems($options);

			$end_of_data = (count($problems) < CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1);

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
		while (count($data['problems']) < CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1 && !$end_of_data);

		$data['problems'] = array_slice($data['problems'], 0, CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
			true
		);

		if ($show_opdata && $data['triggers']) {
			$items = API::Item()->get([
				'output' => ['itemid', 'name', 'value_type', 'units'],
				'selectValueMap' => ['mappings'],
				'triggerids' => array_keys($data['triggers']),
				'webitems' => true,
				'preservekeys' => true
			]);

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
	 *
	 * @static
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
					$data['maintenance_name'] = $maintenances[$data['maintenanceid']]['name'];
				}
				elseif ($data['userid'] != 0 && array_key_exists($data['userid'], $users)) {
					$data['username'] = getUserFullname($users[$data['userid']]);
				}
				else {
					$data['username'] = _('Inaccessible user');
				}
			}
			unset($data);
		}
		unset($problem);
	}

	/**
	 * @param array  $data
	 * @param array  $data['problems']
	 * @param array  $data['triggers']
	 * @param string $sort
	 * @param string $sortorder
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function sortData(array $data, $sort, $sortorder) {
		if (!$data['problems']) {
			return $data;
		}

		$last_problem = end($data['problems']);
		$data['problems'] = array_slice($data['problems'], 0, CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT),
			true
		);

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
	 * @static
	 *
	 * @return array
	 */
	private static function getExDataEvents(array $eventids) {
		$events = API::Event()->get([
			'output' => ['eventid', 'r_eventid', 'acknowledged'],
			'selectTags' => ['tag', 'value'],
			'select_acknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity',
				'suppress_until'
			],
			'selectSuppressionData' => ['maintenanceid', 'userid', 'suppress_until'],
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
	 * @static
	 *
	 * @return array
	 */
	private static function getExDataProblems(array $eventids) {
		return API::Problem()->get([
			'output' => ['eventid', 'r_eventid', 'r_clock', 'r_ns', 'correlationid', 'userid', 'acknowledged'],
			'selectTags' => ['tag', 'value'],
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity',
				'suppress_until'
			],
			'selectSuppressionData' => ['maintenanceid', 'userid', 'suppress_until'],
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
	 * @static
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

		$problems_data = ($filter['show'] == TRIGGERS_OPTION_ALL)
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
	 * @param int        $last_clock  timestamp of the previous record
	 * @param int        $clock       timestamp of the current record
	 * @param string     $sortorder
	 *
	 * @static
	 */
	public static function addTimelineBreakpoint(CTableInfo $table, $last_clock, $clock, $sortorder) {
		if ($sortorder === ZBX_SORT_UP) {
			list($clock, $last_clock) = [$last_clock, $clock];
		}

		$breakpoint = null;
		$today = strtotime('today');
		$yesterday = strtotime('yesterday');
		$this_year = strtotime('first day of January '.date('Y', $today));

		if ($last_clock >= $today) {
			if ($clock < $today) {
				$breakpoint = _('Today');
			}
			elseif (date('H', $last_clock) != date('H', $clock)) {
				$breakpoint = date('H:00', $last_clock);
			}
		}
		elseif ($last_clock >= $yesterday) {
			if ($clock < $yesterday) {
				$breakpoint = _('Yesterday');
			}
		}
		elseif ($last_clock >= $this_year && $clock < $this_year) {
			$breakpoint = date('Y', $last_clock);
		}
		elseif (date('Ym', $last_clock) != date('Ym', $clock)) {
			$breakpoint = getMonthCaption(date('m', $last_clock));
		}

		if ($breakpoint !== null) {
			$table->addRow((new CRow([
				(new CCol(new CTag('h4', true, $breakpoint)))->addClass(ZBX_STYLE_TIMELINE_DATE),
				(new CCol())
					->addClass(ZBX_STYLE_TIMELINE_AXIS)
					->addClass(ZBX_STYLE_TIMELINE_DOT_BIG),
				(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD),
				(new CCol())->setColSpan($table->getNumCols() - 3)
			]))->addClass(ZBX_STYLE_HOVER_NOBG));
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

		$data = self::getData($this->data['filter'], true);
		$data = self::sortData($data, $this->data['sort'], $this->data['sortorder']);

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

		if ($data['problems']) {
			$triggers_hosts = getTriggersHostsList($data['triggers']);
		}

		$show_opdata = $this->data['filter']['compact_view']
			? OPERATIONAL_DATA_SHOW_NONE
			: $this->data['filter']['show_opdata'];

		if ($this->data['action'] === 'problem.view' || $this->data['action'] === 'problem.view.refresh') {
			$form = (new CForm('post', 'zabbix.php'))
				->setId('problem_form')
				->setName('problem')
				->cleanItems();

			$header_check_box = (new CColHeader(
				(new CCheckBox('all_eventids'))
					->onClick("checkAll('".$form->getName()."', 'all_eventids', 'eventids');")
			));

			$this->data['filter']['compact_view']
				? $header_check_box->addStyle('width: 20px;')
				: $header_check_box->addClass(ZBX_STYLE_CELL_WIDTH);

			$link = $url->getUrl();

			$show_timeline = ($this->data['sort'] === 'clock' && !$this->data['filter']['compact_view']
				&& $this->data['filter']['show_timeline']);

			$show_recovery_data = in_array($this->data['filter']['show'], [
				TRIGGERS_OPTION_RECENT_PROBLEM,
				TRIGGERS_OPTION_ALL
			]);
			$header_clock =
				make_sorting_header(_('Time'), 'clock', $this->data['sort'], $this->data['sortorder'], $link);

			$this->data['filter']['compact_view']
				? $header_clock->addStyle('width: 115px;')
				: $header_clock->addClass(ZBX_STYLE_CELL_WIDTH);

			if ($show_timeline) {
				$header = [
					$header_clock->addClass(ZBX_STYLE_RIGHT),
					(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH),
					(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH)
				];
			}
			else {
				$header = [$header_clock];
			}

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

				$table = (new CTableInfo())
					->setHeader(array_merge($header, [
						$header_check_box,
						make_sorting_header(_('Severity'), 'severity', $this->data['sort'], $this->data['sortorder'],
							$link
						)->addStyle('width: 120px;'),
						$show_recovery_data ? (new CColHeader(_('Recovery time')))->addStyle('width: 115px;') : null,
						$show_recovery_data ? (new CColHeader(_('Status')))->addStyle('width: 70px;') : null,
						(new CColHeader(_('Info')))->addStyle('width: 24px;'),
						make_sorting_header(_('Host'), 'host', $this->data['sort'], $this->data['sortorder'], $link)
							->addStyle('width: 42%;'),
						make_sorting_header(_('Problem'), 'name', $this->data['sort'], $this->data['sortorder'], $link)
							->addStyle('width: 58%;'),
						(new CColHeader(_('Duration')))->addStyle('width: 73px;'),
						(new CColHeader(_('Ack')))->addStyle('width: 36px;'),
						(new CColHeader(_('Actions')))->addStyle('width: 64px;'),
						$tags_header
					]))
						->addClass(ZBX_STYLE_COMPACT_VIEW)
						->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS);
			}
			else {
				$table = (new CTableInfo())
					->setHeader(array_merge($header, [
						$header_check_box,
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
						_('Ack'),
						_('Actions'),
						$this->data['filter']['show_tags'] ? _('Tags') : null
					]));
			}

			if ($this->data['filter']['show_tags']) {
				$tags = makeTags($data['problems'], true, 'eventid', $this->data['filter']['show_tags'],
					array_key_exists('tags', $this->data['filter']) ? $this->data['filter']['tags'] : [], null,
					$this->data['filter']['tag_name_format'], $this->data['filter']['tag_priority']
				);
			}

			if ($data['problems']) {
				$triggers_hosts = makeTriggersHostsList($triggers_hosts);
			}

			$last_clock = 0;
			$today = strtotime('today');

			// Make trigger dependencies.
			if ($data['triggers']) {
				$dependencies = getTriggerDependencies($data['triggers']);
			}

			$allowed = [
				'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
				'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
				'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
				'close' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS),
				'suppress' => CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS)
			];

			// Add problems to table.
			foreach ($data['problems'] as $eventid => $problem) {
				$trigger = $data['triggers'][$problem['objectid']];

				$cell_clock = ($problem['clock'] >= $today)
					? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
					: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);
				$cell_clock = new CCol(new CLink($cell_clock,
					(new CUrl('tr_events.php'))
						->setArgument('triggerid', $problem['objectid'])
						->setArgument('eventid', $problem['eventid'])
				));

				if ($problem['r_eventid'] != 0) {
					$cell_r_clock = ($problem['r_clock'] >= $today)
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

				if ($problem['r_eventid'] != 0) {
					$value = TRIGGER_VALUE_FALSE;
					$value_str = _('RESOLVED');
					$value_clock = $problem['r_clock'];
					$can_be_closed = false;
				}
				else {
					$in_closing = hasEventCloseAction($problem['acknowledges']);
					$can_be_closed = ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED && $allowed['close']
						&& !$in_closing
					);
					$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
					$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
					$value_clock = $in_closing ? time() : $problem['clock'];
				}

				$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
				$cell_status = new CSpan($value_str);

				// Add colors and blinking to span depending on configuration and trigger parameters.
				addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

				// Info.
				$info_icons = [];
				if ($problem['r_eventid'] != 0) {
					if ($problem['correlationid'] != 0) {
						$info_icons[] = makeInformationIcon(
							array_key_exists($problem['correlationid'], $data['correlations'])
								? _s('Resolved by correlation rule "%1$s".',
									$data['correlations'][$problem['correlationid']]['name']
								)
								: _('Resolved by correlation rule.')
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

						$info_icons[] = (new CSimpleButton())
							->addClass(ZBX_STYLE_ACTION_ICON_UNSUPPRESS)
							->addClass('blink')
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

				if ($this->data['filter']['compact_view'] && $this->data['filter']['show_suppressed']
						&& count($info_icons) > 1) {
					$cell_info = (new CButton(null))
						->addClass(ZBX_STYLE_ICON_WIZARD_ACTION)
						->addStyle('margin-left: -3px;')
						->setHint(makeInformationList($info_icons));
				}
				else {
					$cell_info = makeInformationList($info_icons);
				}

				$description = array_key_exists($trigger['triggerid'], $dependencies)
					? makeTriggerDependencies($dependencies[$trigger['triggerid']])
					: [];
				$description[] = (new CLinkAction($problem['name']))
					->setMenuPopup(CMenuPopupHelper::getTrigger($trigger['triggerid'], $problem['eventid']))
					->addClass(ZBX_STYLE_WORDBREAK);

				$opdata = null;

				if ($show_opdata != OPERATIONAL_DATA_SHOW_NONE) {
					if ($trigger['opdata'] === '') {
						if ($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) {
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

						if ($show_opdata == OPERATIONAL_DATA_SHOW_WITH_PROBLEM) {
							$description[] = ' (';
							$description[] = $opdata;
							$description[] = ')';
						}
					}
				}

				$description[] = ($problem['comments'] !== '') ? makeDescriptionIcon($problem['comments']) : null;

				if ($this->data['filter']['details'] == 1) {
					$description[] = BR();

					if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
						$description[] = [_('Problem'), ': ', (new CDiv($trigger['expression_html']))
							->addClass(ZBX_STYLE_WORDWRAP), BR()];
						$description[] = [_('Recovery'), ': ', (new CDiv($trigger['recovery_expression_html']))
							->addClass(ZBX_STYLE_WORDWRAP)];
					}
					else {
						$description[] = (new CDiv($trigger['expression_html']))->addClass(ZBX_STYLE_WORDWRAP);
					}
				}

				if ($show_timeline) {
					if ($last_clock != 0) {
						self::addTimelineBreakpoint($table, $last_clock, $problem['clock'], $this->data['sortorder']);
					}
					$last_clock = $problem['clock'];

					$row = [
						$cell_clock->addClass(ZBX_STYLE_TIMELINE_DATE),
						(new CCol())
							->addClass(ZBX_STYLE_TIMELINE_AXIS)
							->addClass(ZBX_STYLE_TIMELINE_DOT),
						(new CCol())->addClass(ZBX_STYLE_TIMELINE_TD)
					];
				}
				else {
					$row = [
						$cell_clock
							->addClass(ZBX_STYLE_NOWRAP)
							->addClass(ZBX_STYLE_RIGHT)
					];
				}

				// Create acknowledge link.
				$problem_update_link = ($allowed['add_comments'] || $allowed['change_severity']
						|| $allowed['acknowledge'] || $can_be_closed || $allowed['suppress'])
					? (new CLink($is_acknowledged ? _('Yes') : _('No')))
						->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
						->addClass(ZBX_STYLE_LINK_ALT)
						->setAttribute('data-eventid', $problem['eventid'])
						->onClick('acknowledgePopUp({eventids: [this.dataset.eventid]}, this);')
					: (new CSpan($is_acknowledged ? _('Yes') : _('No')))->addClass(
						$is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED
					);

				// Add table row.
				$table->addRow(array_merge($row, [
					new CCheckBox('eventids['.$problem['eventid'].']', $problem['eventid']),
					CSeverityHelper::makeSeverityCell((int) $problem['severity'], null, $value == TRIGGER_VALUE_FALSE),
					$show_recovery_data ? $cell_r_clock : null,
					$show_recovery_data ? $cell_status : null,
					$cell_info,
					$this->data['filter']['compact_view']
						? (new CDiv($triggers_hosts[$trigger['triggerid']]))->addClass(ZBX_STYLE_ACTION_CONTAINER)
						: $triggers_hosts[$trigger['triggerid']],
					$this->data['filter']['compact_view']
						? (new CDiv($description))->addClass(ZBX_STYLE_ACTION_CONTAINER)
						: $description,
					($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) ? $opdata->addClass(ZBX_STYLE_WORDBREAK) : null,
					($problem['r_eventid'] != 0)
						? zbx_date2age($problem['clock'], $problem['r_clock'])
						: zbx_date2age($problem['clock']),
					$problem_update_link,
					makeEventActionsIcons($problem['eventid'], $data['actions'], $data['users']),
					$this->data['filter']['show_tags'] ? $tags[$problem['eventid']] : null
				]), ($this->data['filter']['highlight_row'] && $value == TRIGGER_VALUE_TRUE)
					? self::getSeverityFlhStyle($problem['severity'])
					: null
				);
			}

			$footer = new CActionButtonList('action', 'eventids', [
				'popup.acknowledge.edit' => [
					'name' => _('Mass update'),
					'disabled' => !($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
							|| $allowed['close'] || $allowed['suppress']
					)
				]
			], 'problem');

			return $this->getOutput($form->addItem([$table, $paging, $footer]), false, $this->data);
		}

		/*
		 * Search limit performs +1 selection to know if limit was exceeded, this will assure that csv has
		 * "search_limit" records at most.
		 */
		array_splice($data['problems'], CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT));

		$csv = [];

		$csv[] = array_filter([
			_('Severity'),
			_('Time'),
			_('Recovery time'),
			_('Status'),
			_('Host'),
			_('Problem'),
			($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) ? _('Operational data') : null,
			_('Duration'),
			_('Ack'),
			_('Actions'),
			_('Tags')
		]);

		$tags = makeTags($data['problems'], false);

		foreach ($data['problems'] as $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			if ($problem['r_eventid'] != 0) {
				$value_str = _('RESOLVED');
			}
			else {
				$in_closing = false;

				foreach ($problem['acknowledges'] as $acknowledge) {
					if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
						$in_closing = true;
						break;
					}
				}

				$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
			}

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
	 * Get item latest values.
	 *
	 * @static
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
			$hint_table = (new CTable())->addClass('list-table');
		}

		foreach ($items as $itemid => $item) {
			if (array_key_exists($itemid, $history_values)) {
				$last_value = reset($history_values[$itemid]);
				$last_value['value'] = formatHistoryValue(str_replace(["\r\n", "\n"], [" "], $last_value['value']),
					$item
				);
			}
			else {
				$last_value = [
					'itemid' => null,
					'clock' => null,
					'value' => UNRESOLVED_MACRO_STRING,
					'ns' => null
				];
			}

			if ($html) {
				$hint_table->addRow([
					new CCol($item['name']),
					new CCol(
						($last_value['clock'] !== null)
							? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $last_value['clock'])
							: UNRESOLVED_MACRO_STRING
					),
					new CCol($last_value['value']),
					new CCol(
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
					)
				]);

				$latest_values[] = (new CLinkAction($last_value['value']))
					->addClass('hint-item')
					->setAttribute('data-hintbox', '1');
				$latest_values[] = ', ';
			}
			else {
				$latest_values[] = $last_value['value'];
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
