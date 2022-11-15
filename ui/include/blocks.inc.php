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


require_once dirname(__FILE__).'/graphs.inc.php';
require_once dirname(__FILE__).'/screens.inc.php';
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
	$filter_tags = array_key_exists('tags', $filter) ? $filter['tags'] : [];

	if (array_key_exists('exclude_groupids', $filter) && $filter['exclude_groupids']) {
		if ($filter_hostids === null) {
			// Get all groups if no selected groups defined.
			if ($filter_groupids === null) {
				$filter_groupids = array_keys(API::HostGroup()->get([
					'output' => [],
					'real_hosts' => true,
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
			'monitored_hosts' => true,
			'preservekeys' => true
		]),
		'triggers' => [],
		'actions' => [],
		'stats' => []
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
		'output' => ['eventid', 'objectid', 'clock', 'ns', 'name', 'acknowledged', 'severity'],
		'groupids' => array_keys($data['groups']),
		'hostids' => $filter_hostids,
		'evaltype' => $filter_evaltype,
		'tags' => $filter_tags,
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'suppressed' => false,
		'sortfield' => ['eventid'],
		'sortorder' => ZBX_SORT_DOWN,
		'preservekeys' => true
	];

	if (array_key_exists('severities', $filter)) {
		$filter_severities = implode(',', $filter['severities']);
		$all_severities = implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1));

		if ($filter_severities !== '' && $filter_severities !== $all_severities) {
			$options['severities'] = $filter['severities'];
		}
	}

	if (array_key_exists('show_suppressed', $filter) && $filter['show_suppressed']) {
		unset($options['suppressed']);
		$options['selectSuppressionData'] = ['maintenanceid', 'suppress_until'];
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
			'output' => ['priority'],
			'selectGroups' => ['groupid'],
			'selectHosts' => ['name'],
			'selectItems' => ['itemid', 'hostid', 'name', 'key_', 'value_type', 'units', 'valuemapid'],
			'triggerids' => array_keys($triggerids),
			'monitored' => true,
			'skipDependent' => true,
			'preservekeys' => true
		];

		if (array_key_exists('show_opdata', $filter) && $filter['show_opdata'] != OPERATIONAL_DATA_SHOW_NONE) {
			$options['output'] = array_merge(
				$options['output'],
				['url', 'expression', 'recovery_mode', 'recovery_expression', 'opdata']
			);
		}

		$data['triggers'] = API::Trigger()->get($options);

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

		foreach ($problems as $eventid => $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			$data['stats'][$problem['severity']]['count']++;
			if ($problem['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
				$data['stats'][$problem['severity']]['count_unack']++;
			}

			// groups
			foreach ($trigger['groups'] as $trigger_group) {
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
			'eventids' => array_keys($visible_problems),
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity'],
			'selectTags' => ['tag', 'value'],
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
				'output' => ['alias', 'name', 'surname'],
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
 * @param array  $filter
 * @param array  $filter['hostids']            (optional)
 * @param string $filter['problem']            (optional)
 * @param array  $filter['severities']         (optional)
 * @param int    $filter['show_suppressed']    (optional)
 * @param int    $filter['hide_empty_groups']  (optional)
 * @param int    $filter['ext_ack']            (optional)
 * @param int    $filter['show_timeline']      (optional)
 * @param int    $filter['show_opdata']        (optional)
 * @param array  $data
 * @param array  $data['groups']
 * @param string $data['groups'][]['groupid']
 * @param string $data['groups'][]['name']
 * @param bool   $data['groups'][]['has_problems']
 * @param array  $data['groups'][]['stats']
 * @param int    $data['groups'][]['stats']['count']
 * @param array  $data['groups'][]['stats']['problems']
 * @param string $data['groups'][]['stats']['problems'][]['eventid']
 * @param string $data['groups'][]['stats']['problems'][]['objectid']
 * @param int    $data['groups'][]['stats']['problems'][]['clock']
 * @param int    $data['groups'][]['stats']['problems'][]['ns']
 * @param int    $data['groups'][]['stats']['problems'][]['acknowledged']
 * @param array  $data['groups'][]['stats']['problems'][]['tags']
 * @param string $data['groups'][]['stats']['problems'][]['tags'][]['tag']
 * @param string $data['groups'][]['stats']['problems'][]['tags'][]['value']
 * @param int    $data['groups'][]['stats']['count_unack']
 * @param array  $data['groups'][]['stats']['problems_unack']
 * @param array  $data['triggers']
 * @param string $data['triggers'][<triggerid>]['expression']
 * @param string $data['triggers'][<triggerid>]['description']
 * @param array  $data['triggers'][<triggerid>]['hosts']
 * @param string $data['triggers'][<triggerid>]['hosts'][]['name']
 * @param array  $data['triggers'][<triggerid>]['opdata']
 * @param array  $config
 * @param string $config['severity_name_*']
 *
 * @return CDiv
 */
function makeSystemStatus(array $filter, array $data, array $config) {
	$filter_severities = (array_key_exists('severities', $filter) && $filter['severities'])
		? $filter['severities']
		: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);
	$filter_hide_empty_groups = array_key_exists('hide_empty_groups', $filter) ? $filter['hide_empty_groups'] : 0;
	$filter_ext_ack = array_key_exists('ext_ack', $filter)
		? $filter['ext_ack']
		: EXTACK_OPTION_ALL;

	// indicator of sort field
	$sort_div = (new CSpan())->addClass(ZBX_STYLE_ARROW_UP);

	// Set trigger severities as table header starting from highest severity.
	$header = [[_('Host group'), $sort_div]];

	for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
		if (in_array($severity, $filter_severities)) {
			$header[] = getSeverityName($severity, $config);
		}
	}

	$table = (new CTableInfo())
		->setHeader($header)
		->setHeadingColumn(0);

	$url_group = (new CUrl('zabbix.php'))
		->setArgument('action', 'problem.view')
		->setArgument('filter_set', 1)
		->setArgument('filter_show', TRIGGERS_OPTION_RECENT_PROBLEM)
		->setArgument('filter_groupids', null)
		->setArgument('filter_hostids', array_key_exists('hostids', $filter) ? $filter['hostids'] : null)
		->setArgument('filter_name', array_key_exists('problem', $filter) ? $filter['problem'] : null)
		->setArgument('filter_show_suppressed',
			(array_key_exists('show_suppressed', $filter) && $filter['show_suppressed'] == 1)
				? 1
				: null
		);

	foreach ($data['groups'] as $group) {
		if ($filter_hide_empty_groups && !$group['has_problems']) {
			continue;
		}

		$url_group->setArgument('filter_groupids', [$group['groupid']]);
		$row = [new CLink($group['name'], $url_group->getUrl())];

		foreach ($group['stats'] as $severity => $stat) {
			if ($stat['count'] == 0 && $stat['count_unack'] == 0) {
				$row[] = '';
				continue;
			}

			$allTriggersNum = $stat['count'];
			if ($allTriggersNum) {
				$allTriggersNum = (new CLinkAction($allTriggersNum))
					->setHint(makeProblemsPopup($stat['problems'], $data['triggers'], $data['actions'], $config,
						$filter
					));
			}

			$unackTriggersNum = $stat['count_unack'];
			if ($unackTriggersNum) {
				$unackTriggersNum = (new CLinkAction($unackTriggersNum))
					->setHint(makeProblemsPopup($stat['problems_unack'], $data['triggers'], $data['actions'], $config,
						$filter
					));
			}

			switch ($filter_ext_ack) {
				case EXTACK_OPTION_ALL:
					$row[] = getSeverityCell($severity, null, $allTriggersNum);
					break;

				case EXTACK_OPTION_UNACK:
					$row[] = getSeverityCell($severity, null, $unackTriggersNum);
					break;

				case EXTACK_OPTION_BOTH:
					if ($stat['count_unack'] != 0) {
						$row[] = getSeverityCell($severity, $config, [
							$unackTriggersNum, ' '._('of').' ', $allTriggersNum
						]);
					}
					else {
						$row[] = getSeverityCell($severity, $config, $allTriggersNum);
					}
					break;
			}
		}

		$table->addRow($row);
	}

	return $table;
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

	return $groups_totals;
}

/**
 * @param array      $data
 * @param array      $data['data']
 * @param array      $data['data']['groups']
 * @param array      $data['data']['groups'][]['stats']
 * @param array      $data['filter']
 * @param array      $data['filter']['severities']
 * @param boolean    $hide_empty_groups
 * @param CUrl       $groupurl
 *
 * @return CTableInfo
 */
function makeSeverityTable(array $data, $hide_empty_groups = false, CUrl $groupurl = null) {
	$table = new CTableInfo();

	foreach ($data['data']['groups'] as $group) {
		if ($hide_empty_groups && !$group['has_problems']) {
			// Skip row.
			continue;
		}

		$groupurl->setArgument('filter_groupids', [$group['groupid']]);
		$row = [new CLink($group['name'], $groupurl->getUrl())];

		foreach ($group['stats'] as $severity => $stat) {
			if ($data['filter']['severities'] && !in_array($severity, $data['filter']['severities'])) {
				// Skip cell.
				continue;
			}

			$row[] = getSeverityTableCell($severity, $data, $stat);
		}

		$table->addRow($row);
	}

	return $table;
}

/**
 * @param array      $data
 * @param array      $data['data']
 * @param array      $data['data']['groups']
 * @param array      $data['data']['groups'][]['stats']
 * @param array      $data['filter']
 * @param array      $data['filter']['severities']
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
 * @param int     $severity
 * @param array   $data
 * @param array   $data['data']
 * @param array   $data['data']['triggers']
 * @param array   $data['data']['actions']
 * @param array   $data['filter']
 * @param array   $data['filter']['ext_ack']
 * @param array   $data['severity_names']
 * @param array   $stat
 * @param int     $stats['count']
 * @param array   $stats['problems']
 * @param int     $stats['count_unack']
 * @param array   $stats['problems_unack']
 * @param boolean $is_total
 *
 * @return CCol|string
 */
function getSeverityTableCell($severity, array $data, array $stat, $is_total = false) {
	if (!$is_total && $stat['count'] == 0 && $stat['count_unack'] == 0) {
		return '';
	}

	$severity_name = $is_total ? ' '.getSeverityName($severity, $data['severity_names']) : '';
	$ext_ack = array_key_exists('ext_ack', $data['filter']) ? $data['filter']['ext_ack'] : EXTACK_OPTION_ALL;

	$allTriggersNum = $stat['count'];
	if ($allTriggersNum) {
		$allTriggersNum = (new CLinkAction($allTriggersNum))
			->setHint(makeProblemsPopup($stat['problems'], $data['data']['triggers'], $data['data']['actions'],
				$data['severity_names'], $data['filter']
			));
	}

	$unackTriggersNum = $stat['count_unack'];
	if ($unackTriggersNum) {
		$unackTriggersNum = (new CLinkAction($unackTriggersNum))
			->setHint(makeProblemsPopup($stat['problems_unack'], $data['data']['triggers'], $data['data']['actions'],
				$data['severity_names'], $data['filter']
			));
	}

	switch ($ext_ack) {
		case EXTACK_OPTION_ALL:
			return getSeverityCell($severity, null, [
				(new CSpan($allTriggersNum))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT),
				$severity_name
			], false, $is_total);

		case EXTACK_OPTION_UNACK:
			return getSeverityCell($severity, null, [
				(new CSpan($unackTriggersNum))->addClass(ZBX_STYLE_TOTALS_LIST_COUNT),
				$severity_name
			], false, $is_total);

		case EXTACK_OPTION_BOTH:
			return getSeverityCell($severity, $data['severity_names'], [
				(new CSpan([$unackTriggersNum, ' '._('of').' ', $allTriggersNum]))
					->addClass(ZBX_STYLE_TOTALS_LIST_COUNT),
				$severity_name
			], false, $is_total);

		default:
			return '';
	}
}

function make_status_of_zbx() {
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server_details = $ZBX_SERVER.':'.$ZBX_SERVER_PORT;
	}
	else {
		$server_details = '';
	}

	$table = (new CTableInfo())
		->setHeader([_('Parameter'), _('Value'), _('Details')])
		->setHeadingColumn(0);

	$status = get_status();

	$table
		->addRow([_('Zabbix server is running'),
			(new CSpan($status['is_running'] ? _('Yes') : _('No')))
				->addClass($status['is_running'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED),
			$server_details
		])
		->addRow([_('Number of hosts (enabled/disabled)'),
			$status['has_status'] ? $status['hosts_count'] : '',
			$status['has_status']
				? [
					(new CSpan($status['hosts_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
					(new CSpan($status['hosts_count_not_monitored']))->addClass(ZBX_STYLE_RED)
				]
				: ''
		])
		->addRow([_('Number of templates'),
			$status['has_status'] ? $status['hosts_count_template'] : '', ''
		]);

	$title = (new CSpan(_('Number of items (enabled/disabled/not supported)')))
		->setTitle(_('Only items assigned to enabled hosts are counted'));
	$table->addRow([$title, $status['has_status'] ? $status['items_count'] : '',
		$status['has_status']
			? [
				(new CSpan($status['items_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
				(new CSpan($status['items_count_disabled']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['items_count_not_supported']))->addClass(ZBX_STYLE_GREY)
			]
			: ''
	]);
	$title = (new CSpan(_('Number of triggers (enabled/disabled [problem/ok])')))
		->setTitle(_('Only triggers assigned to enabled hosts and depending on enabled items are counted'));
	$table->addRow([$title, $status['has_status'] ? $status['triggers_count'] : '',
		$status['has_status']
			? [
				$status['triggers_count_enabled'], ' / ',
				$status['triggers_count_disabled'], ' [',
				(new CSpan($status['triggers_count_on']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['triggers_count_off']))->addClass(ZBX_STYLE_GREEN), ']'
			]
			: ''
	]);
	$table->addRow([_('Number of users (online)'), $status['has_status'] ? $status['users_count'] : '',
		$status['has_status'] ? (new CSpan($status['users_online']))->addClass(ZBX_STYLE_GREEN) : ''
	]);
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$table->addRow([_('Required server performance, new values per second'),
			($status['has_status'] && array_key_exists('vps_total', $status)) ? round($status['vps_total'], 2) : '', ''
		]);
	}

	// Check requirements.
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$setup = new CFrontendSetup();
		$reqs = $setup->checkRequirements();
		$reqs[] = $setup->checkSslFiles();

		foreach ($reqs as $req) {
			if ($req['result'] == CFrontendSetup::CHECK_FATAL) {
				$table->addRow(
					(new CRow([$req['name'], $req['current'], $req['error']]))->addClass(ZBX_STYLE_RED)
				);
			}
		}

		$db = DB::getDbBackend();

		if (!$db->checkEncoding()) {
			$table->addRow(
				(new CRow((new CCol($db->getWarning()))->setAttribute('colspan', 3)))->addClass(ZBX_STYLE_RED)
			);
		}
	}

	// Warn if database history tables have not been upgraded.
	global $DB;

	if (!$DB['DOUBLE_IEEE754']) {
		$table->addRow([
			_('Database history tables upgraded'),
			(new CSpan(_('No')))->addClass(ZBX_STYLE_RED),
			''
		]);
	}

	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN && $DB['TYPE'] == ZBX_DB_POSTGRESQL) {
		$config = select_config();

		if ($config['db_extension'] === ZBX_DB_EXTENSION_TIMESCALEDB && $config['compression_availability'] == 1) {
			if ($config['hk_history_mode'] == 1 && $config['hk_history_global'] == 0) {
				if (PostgresqlDbBackend::isCompressed([
					'history', 'history_log', 'history_str', 'history_text', 'history_uint'
				])) {
					$table->addRow((new CRow([
						_('Housekeeping'),
						_('Override item history period'),
						(new CCol([
							_('This setting should be enabled, because history tables contain compressed chunks.'),
							' ',
							new CLink(_('Configuration').'&hellip;',
								(new CUrl('zabbix.php'))->setArgument('action', 'housekeeping.edit')
							)
						]))->addClass(ZBX_STYLE_RED)
					])));
				}
			}

			if ($config['hk_trends_mode'] == 1 && $config['hk_trends_global'] == 0) {
				if (PostgresqlDbBackend::isCompressed(['trends', 'trends_uint'])) {
					$table->addRow((new CRow([
						_('Housekeeping'),
						_('Override item trend period'),
						(new CCol([
							_('This setting should be enabled, because trend tables contain compressed chunks.'),
							' ',
							new CLink(_('Configuration').'&hellip;',
								(new CUrl('zabbix.php'))->setArgument('action', 'housekeeping.edit')
							)
						]))->addClass(ZBX_STYLE_RED)
					])));
				}
			}
		}
	}

	return $table;
}

/**
 * Generate table for dashboard triggers popup.
 *
 * @see makeSystemStatus
 *
 * @param array  $problems
 * @param string $problems[]['objectid']
 * @param int    $problems[]['clock']
 * @param int    $problems[]['ns']
 * @param array  $problems[]['acknowledged']
 * @param array  $problems[]['severity']
 * @param array  $problems[]['suppression_data']
 * @param array  $problems[]['tags']
 * @param string $problems[]['tags'][]['tag']
 * @param string $problems[]['tags'][]['value']
 * @param array  $triggers
 * @param string $triggers[<triggerid>]['expression']
 * @param string $triggers[<triggerid>]['description']
 * @param array  $triggers[<triggerid>]['hosts']
 * @param string $triggers[<triggerid>]['hosts'][]['name']
 * @param string $triggers[<triggerid>]['opdata']
 * @param array  $actions
 * @param array  $config
 * @param array  $filter
 * @param array  $filter['show_suppressed']  (optional)
 * @param array  $filter['show_timeline']    (optional)
 * @param array  $filter['show_opdata']      (optional)
 *
 * @return CTableInfo
 */
function makeProblemsPopup(array $problems, array $triggers, array $actions, array $config, array $filter) {
	$url_details = (new CUrl('tr_events.php'))
		->setArgument('triggerid', '')
		->setArgument('eventid', '');

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
			_('Ack'),
			_('Actions'),
			_('Tags')
		]));

	$today = strtotime('today');
	$last_clock = 0;

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
		CScreenProblem::addMaintenanceNames($problems);
	}

	foreach ($problems as $problem) {
		$trigger = $triggers[$problem['objectid']];

		$url_details
			->setArgument('triggerid', $problem['objectid'])
			->setArgument('eventid', $problem['eventid']);

		$cell_clock = ($problem['clock'] >= $today)
			? zbx_date2str(TIME_FORMAT_SECONDS, $problem['clock'])
			: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']);
		$cell_clock = new CCol(new CLink($cell_clock, $url_details));

		if ($show_timeline) {
			if ($last_clock != 0) {
				CScreenProblem::addTimelineBreakpoint($table, $last_clock, $problem['clock'], ZBX_SORT_DOWN);
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

		$info_icons = [];
		if (array_key_exists('suppression_data', $problem) && $problem['suppression_data']) {
			$info_icons[] = makeSuppressedProblemIcon($problem['suppression_data']);
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

		// Create acknowledge link.
		$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
		$problem_update_link = (new CLink($is_acknowledged ? _('Yes') : _('No')))
			->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
			->addClass(ZBX_STYLE_LINK_ALT)
			->onClick('acknowledgePopUp('.json_encode(['eventids' => [$problem['eventid']]]).', this);');

		$table->addRow(array_merge($row, [
			makeInformationList($info_icons),
			$triggers_hosts[$trigger['triggerid']],
			getSeverityCell($problem['severity'], null,
				(($show_opdata == OPERATIONAL_DATA_SHOW_WITH_PROBLEM && $opdata)
					? [$problem['name'], ' (', $opdata, ')']
					: $problem['name']
				)
			),
			($show_opdata == OPERATIONAL_DATA_SHOW_SEPARATELY) ? $opdata : null,
			zbx_date2age($problem['clock']),
			$problem_update_link,
			makeEventActionsIcons($problem['eventid'], $actions['all_actions'], $actions['users'], $config),
			$tags[$problem['eventid']]
		]));
	}

	return $table;
}
