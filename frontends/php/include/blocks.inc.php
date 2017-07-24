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


require_once dirname(__FILE__).'/graphs.inc.php';
require_once dirname(__FILE__).'/screens.inc.php';
require_once dirname(__FILE__).'/maps.inc.php';
require_once dirname(__FILE__).'/users.inc.php';

/**
 * @param array  $filter['groupids']           (optional)
 * @param array  $filter['exclude_groupids']   (optional)
 * @param array  $filter['hostids']            (optional)
 * @param string $filter['problem']            (optional)
 * @param array  $filter['severities']         (optional)
 * @param int    $filter['maintenance']        (optional)
 * @param int    $filter['hide_empty_groups']  (optional)
 * @param int    $filter['ext_ack']            (optional)
 * @param string $backurl
 * @param int    $fullscreen
 *
 * @return CDiv
 */
function make_system_status($filter, $backurl, $fullscreen = 0) {
	$config = select_config();

	$filter_groupids = (array_key_exists('groupids', $filter) && $filter['groupids']) ? $filter['groupids'] : null;
	$filter_hostids = (array_key_exists('hostids', $filter) && $filter['hostids']) ? $filter['hostids'] : null;
	$filter_problem = array_key_exists('problem', $filter) ? $filter['problem'] : '';
	$filter_severities = (array_key_exists('severities', $filter) && $filter['severities'])
		? $filter['severities']
		: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);
	$filter_maintenance = array_key_exists('maintenance', $filter) ? $filter['maintenance'] : 1;
	$filter_hide_empty_groups = array_key_exists('hide_empty_groups', $filter) ? $filter['hide_empty_groups'] : 0;
	$filter_ext_ack = array_key_exists('ext_ack', $filter) ? $filter['ext_ack'] : EXTACK_OPTION_ALL;

	if (array_key_exists('exclude_groupids', $filter) && $filter['exclude_groupids']) {
		if ($filter_hostids === null) {
			// Get all groups if no selected groups defined.
			if ($filter_groupids === null) {
				$filter_groupids = array_keys(API::HostGroup()->get([
					'output' => [],
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

	// Set trigger severities as table header starting from highest severity.
	$header = [_('Host group')];
	$def_tab_priority = [];

	foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
		if (in_array($severity, $filter_severities)) {
			$header[] = getSeverityName($severity, $config);
			$def_tab_priority[$severity] = ['count' => 0, 'triggers' => [], 'count_unack' => 0, 'triggers_unack' => []];
		}
	}

	$table = (new CTableInfo())->setHeader($header);

	$groups = API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => $filter_groupids,
		'hostids' => $filter_hostids,
		'monitored_hosts' => true,
		'preservekeys' => true
	]);

	CArrayHelper::sort($groups, [
		['field' => 'name', 'order' => ZBX_SORT_UP]
	]);

	foreach ($groups as &$group) {
		$group['tab_priority'] = $def_tab_priority;
		$group['has_problems'] = false;
	}
	unset($group);

	// get triggers
	$triggers = API::Trigger()->get([
		'output' => ['triggerid', 'priority', 'state', 'description', 'error', 'value', 'lastchange', 'expression'],
		'selectGroups' => ['groupid'],
		'selectHosts' => ['name'],
		'withLastEventUnacknowledged' => ($filter_ext_ack == EXTACK_OPTION_UNACK) ? true : null,
		'skipDependent' => true,
		'groupids' => array_keys($groups),
		'hostids' => $filter_hostids,
		'monitored' => true,
		'maintenance' => ($filter_maintenance == 0) ? false : null,
		'search' => ($filter_problem !== '') ? ['description' => $filter_problem] : null,
		'filter' => [
			'priority' => $filter_severities,
			'value' => TRIGGER_VALUE_TRUE
		],
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'preservekeys' => true
	]);

	$problems = getTriggerLastProblems(array_keys($triggers), ['eventid', 'objectid', 'clock', 'acknowledged', 'ns']);

	$events = [];
	foreach ($problems as $problem) {
		$triggers[$problem['objectid']]['event'] = $problem;
		$events[$problem['eventid']] = ['eventid' => $problem['eventid']];
	}

	// get acknowledges
	if ($events) {
		$eventAcknowledges = API::Event()->get([
			'output' => ['eventid'],
			'eventids' => array_keys($events),
			'select_acknowledges' => ['eventid', 'clock', 'message', 'action', 'alias', 'name', 'surname'],
			'preservekeys' => true
		]);
	}

	// actions
	$actions = makeEventsActions($events);

	// triggers
	foreach ($triggers as $trigger) {
		// event
		if (array_key_exists('event', $trigger)) {
			$trigger['event']['acknowledges'] = isset($eventAcknowledges[$trigger['event']['eventid']])
				? $eventAcknowledges[$trigger['event']['eventid']]['acknowledges']
				: 0;
		}
		else {
			$trigger['event'] = [
				'acknowledged' => false,
				'clock' => $trigger['lastchange'],
				'ns' => '999999999',
				'value' => $trigger['value']
			];
		}

		// groups
		foreach ($trigger['groups'] as $group) {
			if (!isset($groups[$group['groupid']])) {
				continue;
			}

			if (in_array($filter_ext_ack, [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH])) {
				if ($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count'] < ZBX_WIDGET_ROWS) {
					$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers'][] = $trigger;
				}

				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count']++;
			}

			if (in_array($filter_ext_ack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH])
					&& isset($trigger['event']) && !$trigger['event']['acknowledged']) {
				if ($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack'] < ZBX_WIDGET_ROWS) {
					$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers_unack'][] = $trigger;
				}

				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack']++;
			}

			$groups[$group['groupid']]['has_problems'] = true;
		}
	}
	unset($triggers);

	$url_group = (new CUrl('zabbix.php'))
		->setArgument('action', 'problem.view')
		->setArgument('filter_set', 1)
		->setArgument('filter_show', TRIGGERS_OPTION_RECENT_PROBLEM)
		->setArgument('filter_groupids', null)
		->setArgument('filter_hostids', array_key_exists('hostids', $filter) ? $filter['hostids'] : null)
		->setArgument('filter_problem', $filter_problem)
		->setArgument('filter_maintenance', $filter_maintenance == 1 ? 1 : null);
	if ($fullscreen == 1) {
		$url_group->setArgument('fullscreen', '1');
	}

	foreach ($groups as $group) {
		if ($filter_hide_empty_groups && !$group['has_problems']) {
			continue;
		}

		$groupRow = new CRow();

		$url_group->setArgument('filter_groupids', [$group['groupid']]);
		$name = new CLink($group['name'], $url_group->getUrl());

		$groupRow->addItem($name);

		foreach ($group['tab_priority'] as $severity => $data) {
			if ($data['count'] == 0 && $data['count_unack'] == 0) {
				$groupRow->addItem('');
				continue;
			}

			$allTriggersNum = $data['count'];
			if ($allTriggersNum) {
				$allTriggersNum = (new CSpan($allTriggersNum))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->setHint(makeTriggersPopup($data['triggers'], $backurl, $actions, $config));
			}

			$unackTriggersNum = $data['count_unack'];
			if ($unackTriggersNum) {
				$unackTriggersNum = (new CSpan($unackTriggersNum))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->setHint(makeTriggersPopup($data['triggers_unack'], $backurl, $actions, $config));
			}

			switch ($filter_ext_ack) {
				case EXTACK_OPTION_ALL:
					$groupRow->addItem(getSeverityCell($severity, $config, $allTriggersNum));
					break;

				case EXTACK_OPTION_UNACK:
					$groupRow->addItem(getSeverityCell($severity, $config, $unackTriggersNum));
					break;

				case EXTACK_OPTION_BOTH:
					if ($data['count_unack'] != 0) {
						$groupRow->addItem(getSeverityCell($severity, $config, [
							$unackTriggersNum, ' '._('of').' ', $allTriggersNum
						]));
					}
					else {
						$groupRow->addItem(getSeverityCell($severity, $config, $allTriggersNum));
					}
					break;
			}
		}

		$table->addRow($groupRow);
	}

	return $table;
}

function make_status_of_zbx() {
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server_details = isset($ZBX_SERVER, $ZBX_SERVER_PORT)
			? $ZBX_SERVER.':'.$ZBX_SERVER_PORT
			: _('Zabbix server IP or port is not set!');
	}
	else {
		$server_details = '';
	}

	$table = (new CTableInfo())->setHeader([_('Parameter'), _('Value'), _('Details')]);

	show_messages(); // because in function get_status(); function clear_messages() is called when fsockopen() fails.
	$status = get_status();

	$table->addRow([
		_('Zabbix server is running'),
		(new CSpan($status !== false ? _('Yes') : _('No')))
			->addClass($status !== false ? ZBX_STYLE_GREEN : ZBX_STYLE_RED),
		$server_details
	]);
	$table->addRow([_('Number of hosts (enabled/disabled/templates)'), $status !== false ? $status['hosts_count'] : '',
		$status !== false
			? [
				(new CSpan($status['hosts_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
				(new CSpan($status['hosts_count_not_monitored']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['hosts_count_template']))->addClass(ZBX_STYLE_GREY)
			]
			: ''
	]);
	$title = (new CSpan(_('Number of items (enabled/disabled/not supported)')))
		->setAttribute('title', _('Only items assigned to enabled hosts are counted'));
	$table->addRow([$title, $status !== false ? $status['items_count'] : '',
		$status !== false
			? [
				(new CSpan($status['items_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
				(new CSpan($status['items_count_disabled']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['items_count_not_supported']))->addClass(ZBX_STYLE_GREY)
			]
			: ''
	]);
	$title = (new CSpan(_('Number of triggers (enabled/disabled [problem/ok])')))
		->setAttribute('title', _('Only triggers assigned to enabled hosts and depending on enabled items are counted'));
	$table->addRow([$title, $status !== false ? $status['triggers_count'] : '',
		$status !== false
			? [
				$status['triggers_count_enabled'], ' / ',
				$status['triggers_count_disabled'], ' [',
				(new CSpan($status['triggers_count_on']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['triggers_count_off']))->addClass(ZBX_STYLE_GREEN), ']'
			]
			: ''
	]);
	$table->addRow([_('Number of users (online)'), $status !== false ? $status['users_count'] : '',
		$status !== false ? (new CSpan($status['users_online']))->addClass(ZBX_STYLE_GREEN) : ''
	]);
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$table->addRow([_('Required server performance, new values per second'),
			($status !== false && array_key_exists('vps_total', $status)) ? round($status['vps_total'], 2) : '', ''
		]);
	}

	// check requirements
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		foreach ((new CFrontendSetup())->checkRequirements() as $req) {
			if ($req['result'] == CFrontendSetup::CHECK_FATAL) {
				$table->addRow(
					(new CRow([$req['name'], $req['current'], $req['error']]))->addClass(ZBX_STYLE_RED)
				);
			}
		}
	}

	return $table;
}

/**
 * Create DIV with latest problem triggers.
 *
 * If no sortfield and sortorder are defined, the sort indicater in the column name will not be displayed.
 *
 * @param array  $filter['groupids']
 * @param array  $filter['hostids']
 * @param array  $filter['maintenance']
 * @param int    $filter['extAck']
 * @param int    $filter['severity']
 * @param int    $filter['limit']
 * @param string $filter['sortfield']
 * @param string $filter['sortorder']
 * @param string $backurl
 *
 * @return CDiv
 */
function make_latest_issues(array $filter = [], $backurl) {
	// hide the sort indicator if no sortfield and sortorder are given
	$show_sort_indicator = isset($filter['sortfield']) || isset($filter['sortorder']);

	if (isset($filter['sortfield']) && $filter['sortfield'] !== 'lastchange') {
		$sort_field = [$filter['sortfield'], 'lastchange'];
		$sort_order = [$filter['sortorder'], ZBX_SORT_DOWN];
	}
	else {
		$sort_field = ['lastchange'];
		$sort_order = [ZBX_SORT_DOWN];
	}

	$options = [
		'groupids' => $filter['groupids'],
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored' => true,
		'maintenance' => $filter['maintenance'],
		'search' => ($filter['trigger_name'] !== '') ? ['description' => $filter['trigger_name']] : null,
		'filter' => [
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		]
	];

	$triggers = API::Trigger()->get(array_merge($options, [
		'output' => [
			'triggerid', 'expression', 'description', 'url', 'priority', 'lastchange', 'comments', 'error', 'state'
		],
		'selectHosts' => ['hostid'],
		'withLastEventUnacknowledged' => (isset($filter['extAck']) && $filter['extAck'] == EXTACK_OPTION_UNACK)
			? true
			: null,
		'skipDependent' => true,
		'sortfield' => $sort_field,
		'sortorder' => $sort_order,
		'limit' => $filter['limit'],
		'preservekeys' => true,
		'expandComment' => true
	]));

	$triggers = CMacrosResolverHelper::resolveTriggerUrls($triggers);

	// don't use withLastEventUnacknowledged and skipDependent because of performance issues
	$triggers_total_count = API::Trigger()->get(array_merge($options, [
		'countOutput' => true
	]));

	$problems = getTriggerLastProblems(array_keys($triggers), ['eventid', 'objectid', 'clock', 'acknowledged', 'ns']);

	$events = [];
	foreach ($problems as $problem) {
		$triggers[$problem['objectid']]['lastEvent'] = $problem;
		$events[$problem['eventid']] = ['eventid' => $problem['eventid']];
	}

	// get acknowledges
	$hostids = [];
	foreach ($triggers as $trigger) {
		foreach ($trigger['hosts'] as $host) {
			$hostids[$host['hostid']] = true;
		}
	}

	$config = select_config();

	if ($config['event_ack_enable'] && $events) {
		$event_acknowledges = API::Event()->get([
			'output' => ['eventid'],
			'eventids' => array_keys($events),
			'select_acknowledges' => ['clock', 'message', 'action', 'alias', 'name', 'surname'],
			'preservekeys' => true
		]);
	}

	// actions
	$actions = makeEventsActions($events);

	// indicator of sort field
	if ($show_sort_indicator) {
		$sort_div = (new CDiv())
			->addClass(($filter['sortorder'] === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);
	}

	$table = (new CTableInfo())
		->setHeader([
			($show_sort_indicator && $filter['sortfield'] === 'hostname') ? [_('Host'), $sort_div] : _('Host'),
			($show_sort_indicator && $filter['sortfield'] === 'priority') ? [_('Issue'), $sort_div] : _('Issue'),
			($show_sort_indicator && $filter['sortfield'] === 'lastchange')
				? [_('Last change'), $sort_div]
				: _('Last change'),
			_('Age'),
			_('Info'),
			$config['event_ack_enable'] ? _('Ack') : null,
			_('Actions')
		]);

	$hostids = array_keys($hostids);

	$scripts = API::Script()->getScriptsByHosts($hostids);

	// get hosts
	$hosts = API::Host()->get([
		'hostids' => $hostids,
		'output' => ['hostid', 'name', 'status', 'maintenance_status', 'maintenance_type', 'maintenanceid'],
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'preservekeys' => true
	]);

	$maintenanceids = [];
	foreach ($hosts as $host) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$maintenanceids[$host['maintenanceid']] = true;
		}
	}

	if ($maintenanceids) {
		$maintenances = API::Maintenance()->get([
			'maintenanceids' => array_keys($maintenanceids),
			'output' => ['name', 'description'],
			'preservekeys' => true
		]);
	}

	// triggers
	foreach ($triggers as $trigger) {
		$host_list = [];
		foreach ($trigger['hosts'] as $trigger_host) {
			$host = $hosts[$trigger_host['hostid']];

			$host_name = (new CSpan($host['name']))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts[$host['hostid']]));

			if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenance_icon = (new CSpan())
					->addClass(ZBX_STYLE_ICON_MAINT)
					->addClass(ZBX_STYLE_CURSOR_POINTER);

				if (array_key_exists($host['maintenanceid'], $maintenances)) {
					$maintenance = $maintenances[$host['maintenanceid']];

					$hint = $maintenance['name'].' ['.($host['maintenance_type']
						? _('Maintenance without data collection')
						: _('Maintenance with data collection')).']';

					if ($maintenance['description']) {
						$hint .= "\n".$maintenance['description'];
					}

					$maintenance_icon->setHint($hint);
				}

				$host_name = (new CSpan([$host_name, $maintenance_icon]))->addClass(ZBX_STYLE_REL_CONTAINER);
			}

			$host_list[] = $host_name;
			$host_list[] = ', ';
		}
		array_pop($host_list);

		// unknown triggers
		$info_icons = [];
		if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
			$info_icons[] = makeUnknownIcon($trigger['error']);
		}

		// trigger has events
		if (array_key_exists('lastEvent', $trigger)) {
			// description
			$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, [
				'clock' => $trigger['lastEvent']['clock'],
				'ns' => $trigger['lastEvent']['ns']
			]));
		}
		// trigger has no events
		else {
			// description
			$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, [
				'clock' => $trigger['lastchange'],
				'ns' => '999999999'
			]));
		}

		if ($config['event_ack_enable']) {
			if (array_key_exists('lastEvent', $trigger)) {
				$trigger['lastEvent']['acknowledges'] =
					$event_acknowledges[$trigger['lastEvent']['eventid']]['acknowledges'];

				$ack = getEventAckState($trigger['lastEvent'], $backurl);
			}
			else
				$ack = (new CSpan(_('No events')))->addClass(ZBX_STYLE_GREY);
		}
		else {
			$ack = null;
		}

		// description
		if (array_key_exists('lastEvent', $trigger) || $trigger['comments'] !== '' || $trigger['url'] !== '') {
			$eventid = array_key_exists('lastEvent', $trigger) ? $trigger['lastEvent']['eventid'] : 0;
			$description = (new CSpan($description))
				->setHint(make_popup_eventlist($trigger, $eventid, $backurl, $config),'', true, 'max-width: 500px')
				->addClass(ZBX_STYLE_LINK_ACTION);
		}
		$description = (new CCol($description))->addClass(getSeverityStyle($trigger['priority']));

		// clock
		$clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $trigger['lastchange']),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'problem.view')
				->setArgument('filter_triggerids[]', $trigger['triggerid'])
				->setArgument('filter_set', '1')
		);

		// actions
		$action_hint = (array_key_exists('lastEvent', $trigger) && isset($actions[$trigger['lastEvent']['eventid']]))
			? $actions[$trigger['lastEvent']['eventid']]
			: SPACE;

		$table->addRow([
			(new CCol($host_list)),
			$description,
			$clock,
			zbx_date2age($trigger['lastchange']),
			makeInformationList($info_icons),
			$ack,
			(new CCol($action_hint))->addClass(ZBX_STYLE_NOWRAP)
		]);
	}

	// initialize blinking
	zbx_add_post_js('jqBlink.blink();');

	$info = _n('%1$d of %2$d issue is shown', '%1$d of %2$d issues are shown', count($triggers), $triggers_total_count);

	return [$table, $info];
}

/**
 * Generate table for dashboard triggers popup.
 *
 * @see make_system_status
 *
 * @param array $triggers
 * @param string $backurl
 * @param array $actions
 * @param array $config
 *
 * @return CTableInfo
 */
function makeTriggersPopup(array $triggers, $backurl, array $actions, array $config) {
	$popupTable = (new CTableInfo())
		->setHeader([
			_('Host'),
			_('Issue'),
			_('Age'),
			_('Info'),
			$config['event_ack_enable'] ? _('Ack') : null,
			_('Actions')
		]);

	CArrayHelper::sort($triggers, [['field' => 'lastchange', 'order' => ZBX_SORT_DOWN]]);

	foreach ($triggers as $trigger) {
		$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, [
			'clock' => $trigger['event']['clock'],
			'ns' => $trigger['event']['ns']
		]));

		// unknown triggers
		$info_icons = [];
		if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
			$info_icons[] = makeUnknownIcon($trigger['error']);
		}

		// ack
		if ($config['event_ack_enable']) {
			$ack = isset($trigger['event']['eventid'])
				? getEventAckState($trigger['event'], $backurl)
				: (new CSpan(_('No events')))->addClass(ZBX_STYLE_GREY);
		}
		else {
			$ack = null;
		}

		// action
		$action = (isset($trigger['event']['eventid']) && isset($actions[$trigger['event']['eventid']]))
			? $actions[$trigger['event']['eventid']]
			: '';

		$popupTable->addRow([
			$trigger['hosts'][0]['name'],
			getSeverityCell($trigger['priority'], $config, $description),
			zbx_date2age($trigger['lastchange']),
			makeInformationList($info_icons),
			$ack,
			(new CCol($action))->addClass(ZBX_STYLE_NOWRAP)
		]);
	}

	return $popupTable;
}
