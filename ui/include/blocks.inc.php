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


require_once dirname(__FILE__).'/graphs.inc.php';
require_once dirname(__FILE__).'/maps.inc.php';
require_once dirname(__FILE__).'/users.inc.php';

/**
 * @param array  $filter
 * @param array  $filter['groupids']           (optional)
 * @param array  $filter['exclude_groupids']   (optional)
 * @param array  $filter['hostids']            (optional)
 * @param string $filter['problem']            (optional)
 * @param array  $filter['severities']         (optional)
 * @param int    $filter['show_suppressed']    (optional)
 * @param int    $filter['hide_empty_groups']  (optional)
 * @param int    $filter['ext_ack']            (optional)
 * @param int    $filter['show_opdata']        (optional)
 *
 * @return array
 */
function getSystemStatusData(array $filter) {
	$filter_groupids = (array_key_exists('groupids', $filter) && $filter['groupids']) ? $filter['groupids'] : null;
	$filter_hostids = (array_key_exists('hostids', $filter) && $filter['hostids']) ? $filter['hostids'] : null;
	$filter_severities = (array_key_exists('severities', $filter) && $filter['severities'])
		? $filter['severities']
		: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);
	$filter_ext_ack = array_key_exists('ext_ack', $filter)
		? $filter['ext_ack']
		: EXTACK_OPTION_ALL;
	$filter_evaltype = array_key_exists('evaltype', $filter) ? $filter['evaltype'] : TAG_EVAL_TYPE_AND_OR;
	$filter_tags = array_key_exists('tags', $filter) && $filter['tags'] ? $filter['tags'] : null;
	$show_opdata = array_key_exists('show_opdata', $filter) && $filter['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE;

	if (array_key_exists('exclude_groupids', $filter) && $filter['exclude_groupids']) {
		if ($filter_hostids === null) {
			// Get all groups if no selected groups defined.
			if ($filter_groupids === null) {
				$filter_groupids = array_keys(API::HostGroup()->get([
					'output' => [],
					'with_hosts' => true,
					'preservekeys' => true
				]));
			}

			$filter_groupids = array_diff($filter_groupids, $filter['exclude_groupids']);

			// Get available hosts.
			$filter_hostids = array_keys(API::Host()->get([
				'output' => [],
				'groupids' => $filter_groupids,
				'preservekeys' => true
			]));
		}

		$exclude_hostids = array_keys(API::Host()->get([
			'output' => [],
			'groupids' => $filter['exclude_groupids'],
			'preservekeys' => true
		]));

		$filter_hostids = array_diff($filter_hostids, $exclude_hostids);
	}

	$data = [
		'groups' => API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter_groupids,
			'hostids' => $filter_hostids,
			'with_monitored_hosts' => true,
			'preservekeys' => true
		]),
		'triggers' => [],
		'actions' => [],
		'stats' => [],
		'allowed' => [
			'ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
			'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
			'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
			'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
			'close' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS),
			'suppress' => CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS)
		]
	];

	CArrayHelper::sort($data['groups'], [['field' => 'name', 'order' => ZBX_SORT_UP]]);

	$default_stats = [];

	for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
		if (in_array($severity, $filter_severities)) {
			$default_stats[$severity] = ['count' => 0, 'problems' => [], 'count_unack' => 0, 'problems_unack' => []];
		}
	}

	$data['stats'] = $default_stats;

	foreach ($data['groups'] as &$group) {
		$group['stats'] = $default_stats;
		$group['has_problems'] = false;
	}
	unset($group);

	$options = [
		'output' => ['eventid', 'r_eventid', 'objectid', 'clock', 'ns', 'name', 'acknowledged', 'severity'],
		'selectAcknowledges' => ['action', 'clock', 'userid'],
		'groupids' => array_keys($data['groups']),
		'hostids' => $filter_hostids,
		'evaltype' => $filter_evaltype,
		'tags' => $filter_tags,
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'suppressed' => false,
		'symptom' => false,
		'sortfield' => ['eventid'],
		'sortorder' => ZBX_SORT_DOWN,
		'preservekeys' => true
	];

	if (array_key_exists('severities', $filter) && $filter['severities']) {
		$options['severities'] = $filter['severities'];
	}

	if (array_key_exists('show_suppressed', $filter) && $filter['show_suppressed']) {
		unset($options['suppressed']);
		$options['selectSuppressionData'] = ['maintenanceid', 'suppress_until', 'userid'];
	}

	if ($filter_ext_ack == EXTACK_OPTION_UNACK) {
		$options['acknowledged'] = false;
	}

	if (array_key_exists('problem', $filter) && $filter['problem'] !== '') {
		$options['search'] = ['name' => $filter['problem']];
	}

	$problems = API::Problem()->get($options);
	if ($problems) {
		$triggerids = [];

		foreach ($problems as $problem) {
			$triggerids[$problem['objectid']] = true;
		}

		$options = [
			'output' => ['priority', 'manual_close'],
			'selectHostGroups' => ['groupid'],
			'selectHosts' => ['name'],
			'triggerids' => array_keys($triggerids),
			'monitored' => true,
			'skipDependent' => true,
			'preservekeys' => true
		];

		if ($show_opdata) {
			$options['selectFunctions'] = ['itemid'];
			$options['output'] = array_merge($options['output'],
				['expression', 'recovery_mode', 'recovery_expression', 'opdata']
			);
		}

		$data['triggers'] = API::Trigger()->get($options);

		if ($show_opdata && $data['triggers']) {
			$items = CArrayHelper::renameObjectsKeys(API::Item()->get([
				'output' => ['itemid', 'hostid', 'name_resolved', 'key_', 'value_type', 'units'],
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

		foreach ($data['triggers'] as &$trigger) {
			CArrayHelper::sort($trigger['hosts'], [['field' => 'name', 'order' => ZBX_SORT_UP]]);
		}
		unset($trigger);

		foreach ($problems as $eventid => $problem) {
			if (!array_key_exists($problem['objectid'], $data['triggers'])) {
				unset($problems[$eventid]);
			}
		}

		$visible_problems = [];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		$problems = CScreenProblem::sortData(['problems' => $problems], $limit, 'clock', ZBX_SORT_DOWN)['problems'];

		foreach ($problems as $eventid => $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			$data['stats'][$problem['severity']]['count']++;
			if ($problem['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
				$data['stats'][$problem['severity']]['count_unack']++;
			}

			// groups
			foreach ($trigger['hostgroups'] as $trigger_group) {
				if (!array_key_exists($trigger_group['groupid'], $data['groups'])) {
					continue;
				}

				$group = &$data['groups'][$trigger_group['groupid']];

				if (in_array($filter_ext_ack, [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH])) {
					if ($group['stats'][$problem['severity']]['count'] < ZBX_WIDGET_ROWS) {
						$group['stats'][$problem['severity']]['problems'][] = $problem;
						$visible_problems[$eventid] = ['eventid' => $eventid];
					}

					$group['stats'][$problem['severity']]['count']++;
				}

				if (in_array($filter_ext_ack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH])
						&& $problem['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
					if ($group['stats'][$problem['severity']]['count_unack'] < ZBX_WIDGET_ROWS) {
						$group['stats'][$problem['severity']]['problems_unack'][] = $problem;
						$visible_problems[$eventid] = ['eventid' => $eventid];
					}

					$group['stats'][$problem['severity']]['count_unack']++;
				}

				$group['has_problems'] = true;
			}
			unset($group);
		}

		// actions & tags
		$problems_data = API::Problem()->get([
			'output' => ['eventid', 'r_eventid', 'clock', 'objectid', 'severity'],
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity',
				'suppress_until'
			],
			'selectTags' => ['tag', 'value'],
			'eventids' => array_keys($visible_problems),
			'preservekeys' => true
		]);

		// Remove problems that were resolved between requests or set tags.
		foreach ($data['groups'] as $groupid => &$group) {
			foreach ($group['stats'] as $severity => &$stat) {
				foreach (['problems', 'problems_unack'] as $key) {
					foreach ($stat[$key] as $event_no => &$problem) {
						if (array_key_exists($problem['eventid'], $problems_data)) {
							$problem['tags'] = $problems_data[$problem['eventid']]['tags'];
						}
						else {
							if ($key === 'problems') {
								$data['groups'][$groupid]['stats'][$severity]['count']--;
							}
							else {
								$data['groups'][$groupid]['stats'][$severity]['count_unack']--;
							}
							unset($data['groups'][$groupid]['stats'][$severity][$key][$event_no]);
						}
					}
					unset($problem);
				}
			}
			unset($stat);
		}
		unset($group);

		// actions
		// Possible performance improvement: one API call may be saved, if r_clock for problem will be used.
		$actions = getEventsActionsIconsData($problems_data, $data['triggers']);
		$data['actions'] = [
			'all_actions' => $actions['data'],
			'users' => API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => array_keys($actions['userids']),
				'preservekeys' => true
			])
		];

		if (array_key_exists('show_opdata', $filter) && $filter['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE) {
			$maked_data = CScreenProblem::makeData(
				['problems' => $problems_data, 'triggers' => $data['triggers']],
				['show' => 0, 'details' => 0, 'show_opdata' => $filter['show_opdata']]
			);
			$data['triggers'] = $maked_data['triggers'];
		}
	}

	return $data;
}

/**
 * @param array  $data
 * @param array  $data['groups']
 * @param string $data['groups'][]['groupid']
 * @param string $data['groups'][]['name']
 * @param bool   $data['groups'][]['has_problems']
 * @param array  $data['groups'][]['stats']
 * @param int    $data['groups'][]['stats'][]['count']
 * @param array  $data['groups'][]['stats'][]['problems']
 * @param int    $data['groups'][]['stats'][]['count_unack']
 * @param array  $data['groups'][]['stats'][]['problems_unack']
 *
 * @return array
 */
function getSystemStatusTotals(array $data) {
	$groups_totals = [
		0 => [
			'groupid' => 0,
			'stats' => []
		]
	];

	foreach ($data['stats'] as $severity => $value) {
		$groups_totals[0]['stats'][$severity] = [
			'count' => $value['count'],
			'problems' => [],
			'count_unack' => $value['count_unack'],
			'problems_unack' => []
		];
	}

	foreach ($data['groups'] as $group) {
		foreach ($group['stats'] as $severity => $stat) {
			foreach ($stat['problems'] as $problem) {
				$groups_totals[0]['stats'][$severity]['problems'][$problem['eventid']] = $problem;
			}
			foreach ($stat['problems_unack'] as $problem) {
				$groups_totals[0]['stats'][$severity]['problems_unack'][$problem['eventid']] = $problem;
			}
		}
	}

	foreach ($groups_totals[0]['stats'] as &$stat) {
		if ($stat['count'] > 0 || $stat['count_unack'] > 0) {
			$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

			if ($stat['count'] > 0) {
				$stat['problems'] = CScreenProblem::sortData(['problems' => $stat['problems']], $limit, 'clock',
					ZBX_SORT_DOWN
				)['problems'];
			}

			if ($stat['count_unack'] > 0) {
				$stat['problems_unack'] = CScreenProblem::sortData(['problems' => $stat['problems_unack']], $limit,
					'clock', ZBX_SORT_DOWN
				)['problems'];
			}
		}
	}
	unset($stat);

	return $groups_totals;
}

/**
 * @param array $data
 * @param array $data['data']
 * @param array $data['data']['groups']
 * @param array $data['data']['groups'][]['stats']
 * @param array $data['filter']
 * @param array $data['filter']['severities']
 * @param array $data['allowed']
 * @param bool  $data['allowed']['ui_problems']
 * @param bool  $data['allowed']['add_comments']
 * @param bool  $data['allowed']['change_severity']
 * @param bool  $data['allowed']['acknowledge']
 * @param bool  $data['allowed']['close']
 * @param bool  $data['allowed']['suppress']
 * @param bool  $hide_empty_groups
 * @param CUrl  $groupurl
 *
 * @return CTableInfo
 */
function makeSeverityTable(array $data, $hide_empty_groups = false, ?CUrl $groupurl = null) {
	$table = new CTableInfo();

	foreach ($data['data']['groups'] as $group) {
		if ($hide_empty_groups && !$group['has_problems']) {
			// Skip row.
			continue;
		}

		if ($data['allowed']['ui_problems']) {
			$groupurl->setArgument('groupids', [$group['groupid']]);
			$row = [new CLink($group['name'], $groupurl->getUrl())];
		}
		else {
			$row = [$group['name']];
		}

		foreach ($group['stats'] as $severity => $stat) {
			if ($data['filter']['severities'] && !in_array($severity, $data['filter']['severities'])) {
				// Skip cell.
				continue;
			}

			$row[] = getSeverityTableCell($severity, $data, $stat);
		}

		$table->addRow(
			(new CRow($row))->setAttribute('data-hostgroupid', $group['groupid'])
		);
	}

	return $table;
}

/**
 * @param array $data
 * @param array $data['data']
 * @param array $data['data']['groups']
 * @param array $data['data']['groups'][]['stats']
 * @param array $data['filter']
 * @param array $data['filter']['severities']
 * @param array $data['allowed']
 * @param bool  $data['allowed']['ui_problems']
 * @param bool  $data['allowed']['add_comments']
 * @param bool  $data['allowed']['change_severity']
 * @param bool  $data['allowed']['acknowledge']
 * @param bool  $data['allowed']['close']
 * @param bool  $data['allowed']['suppress']
 *
 * @return CDiv
 */
function makeSeverityTotals(array $data) {
	$table = new CDiv();

	foreach ($data['data']['groups'] as $group) {
		foreach ($group['stats'] as $severity => $stat) {
			if ($data['filter']['severities'] && !in_array($severity, $data['filter']['severities'])) {
				// Skip cell.
				continue;
			}
			$table->addItem(getSeverityTableCell($severity, $data, $stat, true));
		}
	}

	return $table;
}

/**
 * @param int   $severity
 * @param array $data
 * @param array $data['data']
 * @param array $data['data']['triggers']
 * @param array $data['data']['actions']
 * @param array $data['filter']
 * @param array $data['filter']['ext_ack']
 * @param array $data['severity_names']
 * @param array $data['allowed']
 * @param bool  $data['allowed']['ui_problems']
 * @param bool  $data['allowed']['add_comments']
 * @param bool  $data['allowed']['change_severity']
 * @param bool  $data['allowed']['acknowledge']
 * @param bool  $data['allowed']['close']
 * @param bool  $data['allowed']['suppress']
 * @param array $stat
 * @param int   $stat['count']
 * @param array $stat['problems']
 * @param int   $stat['count_unack']
 * @param array $stat['problems_unack']
 * @param bool  $is_total
 *
 * @return CCol|string
 */
function getSeverityTableCell($severity, array $data, array $stat, $is_total = false) {
	if (!$is_total && $stat['count'] == 0 && $stat['count_unack'] == 0) {
		return '';
	}

	$allTriggersNum = $stat['count'];
	if ($allTriggersNum) {
		$allTriggersNum = (new CLinkAction($allTriggersNum))
			->setHint(makeProblemsPopup($stat['problems'], $data['data']['triggers'], $data['data']['actions'],
				$data['filter'], $data['allowed']
			));
	}

	$unackTriggersNum = $stat['count_unack'];
	if ($unackTriggersNum) {
		$unackTriggersNum = (new CLinkAction($unackTriggersNum))
			->setHint(makeProblemsPopup($stat['problems_unack'], $data['data']['triggers'], $data['data']['actions'],
				$data['filter'], $data['allowed']
			));
	}

	$ext_ack = array_key_exists('ext_ack', $data['filter']) ? $data['filter']['ext_ack'] : EXTACK_OPTION_ALL;
	$severity_name = $is_total ? CSeverityHelper::getName($severity) : '';

	switch ($ext_ack) {
		case EXTACK_OPTION_ALL:
			return CSeverityHelper::makeSeverityCell($severity, [
				(new CSpan($allTriggersNum))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT),
				(new CSpan($severity_name))->addClass(ZBX_STYLE_TOTALS_LIST_NAME)->setTitle($severity_name)
			], false, $is_total);

		case EXTACK_OPTION_UNACK:
			return CSeverityHelper::makeSeverityCell($severity, [
				(new CSpan($unackTriggersNum))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT),
				(new CSpan($severity_name))->addClass(ZBX_STYLE_TOTALS_LIST_NAME)->setTitle($severity_name)
			], false, $is_total);

		case EXTACK_OPTION_BOTH:
			return CSeverityHelper::makeSeverityCell($severity, [
				(new CSpan([$unackTriggersNum, ' '._('of').' ', $allTriggersNum]))
					->addClass(ZBX_STYLE_TOTALS_LIST_COUNT),
				(new CSpan($severity_name))->addClass(ZBX_STYLE_TOTALS_LIST_NAME)->setTitle($severity_name)
			], false, $is_total);

		default:
			return '';
	}
}

/**
 * Generate table for dashboard triggers popup.
 *
 * @param array  $problems
 * @param string $problems[]['objectid']
 * @param int    $problems[]['clock']
 * @param int    $problems[]['ns']
 * @param string $problems[]['r_eventid']
 * @param string $problems[]['acknowledged']
 * @param array  $problems[]['acknowledges']
 * @param string $problems[]['acknowledges'][]['action']
 * @param array  $problems[]['severity']
 * @param array  $problems[]['suppression_data']
 * @param array  $problems[]['tags']
 * @param string $problems[]['tags'][]['tag']
 * @param string $problems[]['tags'][]['value']
 * @param array  $triggers
 * @param string $triggers[<triggerid>]['expression']
 * @param string $triggers[<triggerid>]['description']
 * @param string $triggers[<triggerid>]['manual_close']
 * @param array  $triggers[<triggerid>]['hosts']
 * @param string $triggers[<triggerid>]['hosts'][]['name']
 * @param string $triggers[<triggerid>]['opdata']
 * @param array  $actions
 * @param array  $filter
 * @param array  $filter['show_suppressed']  (optional)
 * @param array  $filter['show_timeline']    (optional)
 * @param array  $filter['show_opdata']      (optional)
 * @param array  $allowed
 * @param bool   $allowed['ui_problems']
 * @param bool   $allowed['add_comments']
 * @param bool   $allowed['change_severity']
 * @param bool   $allowed['acknowledge']
 * @param bool   $allowed['close']
 * @param bool   $allowed['suppress']
 *
 * @return CTableInfo
 */
function makeProblemsPopup(array $problems, array $triggers, array $actions, array $filter, array $allowed) {
	$url_details = $allowed['ui_problems']
		? (new CUrl('tr_events.php'))
			->setArgument('triggerid', '')
			->setArgument('eventid', '')
		: null;

	$header_time = new CColHeader([_('Time'), (new CSpan())->addClass(ZBX_STYLE_ARROW_DOWN)]);

	$show_timeline = (array_key_exists('show_timeline', $filter) && $filter['show_timeline']);
	$show_opdata = (array_key_exists('show_opdata', $filter)) ? $filter['show_opdata'] : OPERATIONAL_DATA_SHOW_NONE;

	if ($show_timeline) {
		$header = [
			$header_time->addClass(ZBX_STYLE_RIGHT),
			(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH),
			(new CColHeader())->addClass(ZBX_STYLE_TIMELINE_TH)
		];
	}
	else {
		$header = [$header_time];
	}

	$table = (new CTableInfo())
		->setHeader(array_merge($header, [
			_('Info'),
			_('Host'),
			_('Problem'),
			($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) ? _('Operational data') : null,
			_('Duration'),
			_('Update'),
			_('Actions'),
			_('Tags')
		]));

	$data = [
		'last_clock' => 0,
		'sortorder' => ZBX_SORT_DOWN,
		'show_three_columns' => false,
		'show_two_columns' => false
	];

	$today = strtotime('today');

	// Unset triggers, which missing in problems array.
	if ($problems) {
		$objectids = [];

		foreach ($problems as $problem) {
			$objectids[$problem['objectid']] = true;
		}

		$triggers = array_intersect_key($triggers, $objectids);
	}

	$triggers_hosts = getTriggersHostsList($triggers);
	$triggers_hosts = makeTriggersHostsList($triggers_hosts);

	$tags = makeTags($problems);

	if (array_key_exists('show_suppressed', $filter) && $filter['show_suppressed']) {
		CScreenProblem::addSuppressionNames($problems);
	}

	foreach ($problems as $problem) {
		$trigger = $triggers[$problem['objectid']];

		$cell_clock = ($problem['clock'] >= $today)
			? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
			: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);

		if ($url_details !== null) {
			$url_details
				->setArgument('triggerid', $problem['objectid'])
				->setArgument('eventid', $problem['eventid']);
			$cell_clock = new CCol(new CLink($cell_clock, $url_details));
		}
		else {
			$cell_clock = new CCol($cell_clock);
		}

		if ($show_timeline) {
			if ($data['last_clock'] != 0) {
				CScreenProblem::addTimelineBreakpoint($table, $data, $problem, false, false);
			}
			$data['last_clock'] = $problem['clock'];

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

		$info_icons = [];
		if (array_key_exists('suppression_data', $problem)) {
			if (count($problem['suppression_data']) == 1
					&& $problem['suppression_data'][0]['maintenanceid'] == 0
					&& isEventRecentlyUnsuppressed($problem['acknowledges'], $unsuppression_action)) {
				// Show blinking button if the last manual suppression was recently revoked.
				$user_unsuppressed = array_key_exists($unsuppression_action['userid'], $actions['users'])
					? getUserFullname($actions['users'][$unsuppression_action['userid']])
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
					'username' => array_key_exists($suppression_action['userid'], $actions['users'])
						? getUserFullname($actions['users'][$suppression_action['userid']])
						: _('Inaccessible user')
				]], true);
			}
		}

		// operational data
		$opdata = null;
		if ($show_opdata != OPERATIONAL_DATA_SHOW_NONE) {

			if ($trigger['opdata'] === '') {
				if ($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) {
					$opdata = (new CCol(CScreenProblem::getLatestValues($trigger['items'])))->addClass('latest-values');
				}
			}
			else {
				$opdata = CMacrosResolverHelper::resolveTriggerOpdata(
					[
						'triggerid' => $trigger['triggerid'],
						'expression' => $trigger['expression'],
						'opdata' => $trigger['opdata'],
						'clock' => $problem['clock'],
						'ns' => $problem['ns']
					],
					[
						'events' => true,
						'html' => true
					]
				);

				if ($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) {
					$opdata = (new CCol($opdata))
						->addClass('opdata')
						->addClass(ZBX_STYLE_WORDWRAP);
				}
			}
		}

		$can_be_closed = ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED && $allowed['close']);

		if ($problem['r_eventid'] != 0) {
			$can_be_closed = false;
		}
		else {
			foreach ($problem['acknowledges'] as $acknowledge) {
				if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
					$can_be_closed = false;
					break;
				}
			}
		}

		$problem_update_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'acknowledge.edit')
			->setArgument('eventids[]', $problem['eventid'])
			->getUrl();

		// Create acknowledge link.
		$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
		$problem_update_link = ($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
				|| $can_be_closed || $allowed['suppress'])
			? (new CLink(_('Update'), $problem_update_url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->setAttribute('data-eventids[]', $problem['eventid'])
				->setAttribute('data-action', 'acknowledge.edit')
			: new CSpan(_('Update'));

		$table->addRow(array_merge($row, [
			makeInformationList($info_icons),
			$triggers_hosts[$trigger['triggerid']],
			CSeverityHelper::makeSeverityCell((int) $problem['severity'],
				(($show_opdata == OPERATIONAL_DATA_SHOW_WITH_PROBLEM && $opdata)
					? [$problem['name'], ' (', $opdata, ')']
					: $problem['name']
				)
			)->addClass(ZBX_STYLE_WORDBREAK),
			($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) ? $opdata : null,
			zbx_date2age($problem['clock']),
			$problem_update_link,
			makeEventActionsIcons($problem['eventid'], $actions['all_actions'], $actions['users'], $is_acknowledged),
			$tags[$problem['eventid']]
		]));
	}

	return $table;
}
