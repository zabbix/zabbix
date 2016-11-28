<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Get favourite graphs and simple graph data.
 *
 * @return array['graphs']
 * @return array['simpleGraphs']
 */
function getFavouriteGraphsData() {
	$graphs = $simpeGraphs = [];

	$favourites = CFavorite::get('web.favorite.graphids');

	if ($favourites) {
		$graphIds = $itemIds = $dbGraphs = $dbItems = [];

		foreach ($favourites as $favourite) {
			if ($favourite['source'] === 'itemid') {
				$itemIds[$favourite['value']] = $favourite['value'];
			}
			else {
				$graphIds[$favourite['value']] = $favourite['value'];
			}
		}

		if ($graphIds) {
			$dbGraphs = API::Graph()->get([
				'output' => ['graphid', 'name'],
				'selectHosts' => ['hostid', 'name'],
				'expandName' => true,
				'graphids' => $graphIds,
				'preservekeys' => true
			]);
		}

		if ($itemIds) {
			$dbItems = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_'],
				'selectHosts' => ['hostid', 'name'],
				'itemids' => $itemIds,
				'webitems' => true,
				'preservekeys' => true
			]);

			$dbItems = CMacrosResolverHelper::resolveItemNames($dbItems);
		}

		foreach ($favourites as $favourite) {
			$sourceId = $favourite['value'];

			if ($favourite['source'] === 'itemid') {
				if (isset($dbItems[$sourceId])) {
					$dbItem = $dbItems[$sourceId];
					$dbHost = reset($dbItem['hosts']);

					$simpeGraphs[] = [
						'id' => $sourceId,
						'label' => $dbHost['name'].NAME_DELIMITER.$dbItem['name_expanded']
					];
				}
			}
			else {
				if (isset($dbGraphs[$sourceId])) {
					$dbGraph = $dbGraphs[$sourceId];
					$dbHost = reset($dbGraph['hosts']);

					$graphs[] = [
						'id' => $sourceId,
						'label' => $dbHost['name'].NAME_DELIMITER.$dbGraph['name']
					];
				}
			}
		}
	}

	return [
		'graphs' => $graphs,
		'simpleGraphs' => $simpeGraphs
	];
}

/**
 * Get favourite graphs and simple graph.
 *
 * @return CTableInfo
 */
function getFavouriteGraphs() {
	$data = getFavouriteGraphsData();

	$favourites = (new CTableInfo())->setNoDataMessage(_('No graphs added.'));

	if ($data['graphs']) {
		foreach ($data['graphs'] as $graph) {
			$favourites->addRow([new CLink($graph['label'], 'charts.php?graphid='.$graph['id'])]);
		}
	}

	if ($data['simpleGraphs']) {
		foreach ($data['simpleGraphs'] as $item) {
			$favourites->addRow([new CLink($item['label'], 'history.php?action='.HISTORY_GRAPH.'&itemids[]='.$item['id'])]);
		}
	}

	return $favourites;
}

/**
 * Get favourite maps data.
 *
 * @return array
 */
function getFavouriteMapsData() {
	$maps = [];

	$favourites = CFavorite::get('web.favorite.sysmapids');

	if ($favourites) {
		$mapIds = [];

		foreach ($favourites as $favourite) {
			$mapIds[$favourite['value']] = $favourite['value'];
		}

		$dbMaps = API::Map()->get([
			'output' => ['sysmapid', 'name'],
			'sysmapids' => $mapIds
		]);

		foreach ($dbMaps as $dbMap) {
			$maps[] = [
				'id' => $dbMap['sysmapid'],
				'label' => $dbMap['name']
			];
		}
	}

	return $maps;
}

/**
 * Get favourite maps.
 *
 * @return CTableInfo
 */
function getFavouriteMaps() {
	$data = getFavouriteMapsData();

	$favourites = (new CTableInfo())->setNoDataMessage(_('No maps added.'));

	if ($data) {
		foreach ($data as $map) {
			$favourites->addRow([new CLink($map['label'], 'zabbix.php?action=map.view&sysmapid='.$map['id'])]);
		}
	}

	return $favourites;
}

/**
 * Get favourite screens and slide shows data.
 *
 * @return array['screens']
 * @return array['slideshows']
 */
function getFavouriteScreensData() {
	$screens = $slideshows = [];

	$favourites = CFavorite::get('web.favorite.screenids');

	if ($favourites) {
		$screenIds = $slideshowIds = [];

		foreach ($favourites as $favourite) {
			if ($favourite['source'] === 'screenid') {
				$screenIds[$favourite['value']] = $favourite['value'];
			}
		}

		$dbScreens = API::Screen()->get([
			'output' => ['screenid', 'name'],
			'screenids' => $screenIds,
			'preservekeys' => true
		]);

		foreach ($favourites as $favourite) {
			$sourceId = $favourite['value'];

			if ($favourite['source'] === 'slideshowid') {
				if (slideshow_accessible($sourceId, PERM_READ)) {
					$dbSlideshow = get_slideshow_by_slideshowid($sourceId, PERM_READ);

					if ($dbSlideshow) {
						$slideshows[] = [
							'id' => $dbSlideshow['slideshowid'],
							'label' => $dbSlideshow['name']
						];
					}
				}
			}
			else {
				if (isset($dbScreens[$sourceId])) {
					$dbScreen = $dbScreens[$sourceId];

					$screens[] = [
						'id' => $dbScreen['screenid'],
						'label' => $dbScreen['name']
					];
				}
			}
		}
	}

	return [
		'screens' => $screens,
		'slideshows' => $slideshows
	];
}

/**
 * Get favourite screens and slide shows.
 *
 * @return CTableInfo
 */
function getFavouriteScreens() {
	$data = getFavouriteScreensData();

	$favourites = (new CTableInfo())->setNoDataMessage(_('No screens added.'));

	if ($data['screens']) {
		foreach ($data['screens'] as $screen) {
			$favourites->addRow([new CLink($screen['label'], 'screens.php?elementid='.$screen['id'])]);
		}
	}

	if ($data['slideshows']) {
		foreach ($data['slideshows'] as $slideshow) {
			$favourites->addRow([new CLink($slideshow['label'], 'slides.php?elementid='.$slideshow['id'])]);
		}
	}

	return $favourites;
}

function make_system_status($filter, $backurl) {
	$config = select_config();

	$table = new CTableInfo();

	// set trigger severities as table header starting from highest severity
	$header = [];

	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$header[] = ($filter['severity'] === null || isset($filter['severity'][$severity]))
			? getSeverityName($severity, $config)
			: null;
	}
	krsort($header);
	array_unshift($header, _('Host group'));

	$table->setHeader($header);

	// get host groups
	$groups = API::HostGroup()->get([
		'groupids' => $filter['groupids'],
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored_hosts' => true,
		'output' => ['groupid', 'name'],
		'preservekeys' => true
	]);

	CArrayHelper::sort($groups, [
		['field' => 'name', 'order' => ZBX_SORT_UP]
	]);

	$groupIds = [];
	foreach ($groups as $group) {
		$groupIds[$group['groupid']] = $group['groupid'];

		$group['tab_priority'] = [
			TRIGGER_SEVERITY_DISASTER => ['count' => 0, 'triggers' => [], 'count_unack' => 0, 'triggers_unack' => []],
			TRIGGER_SEVERITY_HIGH => ['count' => 0, 'triggers' => [], 'count_unack' => 0, 'triggers_unack' => []],
			TRIGGER_SEVERITY_AVERAGE => ['count' => 0, 'triggers' => [], 'count_unack' => 0, 'triggers_unack' => []],
			TRIGGER_SEVERITY_WARNING => ['count' => 0, 'triggers' => [], 'count_unack' => 0, 'triggers_unack' => []],
			TRIGGER_SEVERITY_INFORMATION => ['count' => 0, 'triggers' => [], 'count_unack' => 0, 'triggers_unack' => []],
			TRIGGER_SEVERITY_NOT_CLASSIFIED => ['count' => 0, 'triggers' => [], 'count_unack' => 0, 'triggers_unack' => []]
		];
		$groups[$group['groupid']] = $group;
	}

	// get triggers
	$triggers = API::Trigger()->get([
		'output' => ['triggerid', 'priority', 'state', 'description', 'error', 'value', 'lastchange', 'expression'],
		'selectGroups' => ['groupid'],
		'selectHosts' => ['name'],
		'withLastEventUnacknowledged' => ($filter['extAck'] == EXTACK_OPTION_UNACK) ? true : null,
		'skipDependent' => true,
		'groupids' => $groupIds,
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored' => true,
		'maintenance' => $filter['maintenance'],
		'search' => ($filter['trigger_name'] !== '') ? ['description' => $filter['trigger_name']] : null,
		'filter' => [
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		],
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'preservekeys' => true
	]);

	$problem_events = getTriggerLastProblem(array_keys($triggers));

	$events = [];
	foreach ($problem_events as $problem_event) {
		if (!array_key_exists('event', $triggers[$problem_event['objectid']])) {
			$triggers[$problem_event['objectid']]['event'] = $problem_event;
			$events[$problem_event['eventid']] = ['eventid' => $problem_event['eventid']];
		}
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
		if ($trigger['event']) {
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

			if (in_array($filter['extAck'], [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH])) {
				if ($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count'] < ZBX_WIDGET_ROWS) {
					$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers'][] = $trigger;
				}

				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count']++;
			}

			if (in_array($filter['extAck'], [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH])
					&& isset($trigger['event']) && !$trigger['event']['acknowledged']) {
				if ($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack'] < ZBX_WIDGET_ROWS) {
					$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers_unack'][] = $trigger;
				}

				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack']++;
			}
		}
	}
	unset($triggers);

	foreach ($groups as $group) {
		$groupRow = new CRow();

		$name = new CLink($group['name'], 'tr_status.php?filter_set=1&groupid='.$group['groupid'].'&hostid=0'.
			'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
		);

		$groupRow->addItem($name);

		foreach ($group['tab_priority'] as $severity => $data) {
			if (!is_null($filter['severity']) && !isset($filter['severity'][$severity])) {
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

			switch ($filter['extAck']) {
				case EXTACK_OPTION_ALL:
					$groupRow->addItem(getSeverityCell($severity, $config, $allTriggersNum, $data['count'] == 0));
					break;

				case EXTACK_OPTION_UNACK:
					$groupRow->addItem(getSeverityCell($severity, $config, $unackTriggersNum,
						$data['count_unack'] == 0
					));
					break;

				case EXTACK_OPTION_BOTH:
					if ($data['count_unack'] != 0) {
						$groupRow->addItem(getSeverityCell($severity, $config, [
							$unackTriggersNum, ' '._('of').' ', $allTriggersNum
						]));
					}
					else {
						$groupRow->addItem(getSeverityCell($severity, $config, $allTriggersNum, $data['count'] == 0));
					}
					break;
			}
		}

		$table->addRow($groupRow);
	}

	return $table;
}

function make_status_of_zbx() {
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	$table = (new CTableInfo())
		->setHeader([
			_('Parameter'),
			_('Value'),
			_('Details')
		]);

	show_messages(); // because in function get_status(); function clear_messages() is called when fsockopen() fails.
	$status = get_status();

	$table->addRow([
		_('Zabbix server is running'),
		(new CSpan($status['zabbix_server']))->addClass($status['zabbix_server'] == _('Yes') ? ZBX_STYLE_GREEN : ZBX_STYLE_RED),
		isset($ZBX_SERVER, $ZBX_SERVER_PORT) ? $ZBX_SERVER.':'.$ZBX_SERVER_PORT : _('Zabbix server IP or port is not set!')
	]);
	$title = (new CSpan(_('Number of hosts (enabled/disabled/templates)')))->setAttribute('title', 'asdad');
	$table->addRow([_('Number of hosts (enabled/disabled/templates)'), $status['hosts_count'],
		[
			(new CSpan($status['hosts_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
			(new CSpan($status['hosts_count_not_monitored']))->addClass(ZBX_STYLE_RED), ' / ',
			(new CSpan($status['hosts_count_template']))->addClass(ZBX_STYLE_GREY)
		]
	]);
	$title = (new CSpan(_('Number of items (enabled/disabled/not supported)')))
		->setAttribute('title', _('Only items assigned to enabled hosts are counted'));
	$table->addRow([$title, $status['items_count'],
		[
			(new CSpan($status['items_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
			(new CSpan($status['items_count_disabled']))->addClass(ZBX_STYLE_RED), ' / ',
			(new CSpan($status['items_count_not_supported']))->addClass(ZBX_STYLE_GREY)
		]
	]);
	$title = (new CSpan(_('Number of triggers (enabled/disabled [problem/ok])')))
		->setAttribute('title', _('Only triggers assigned to enabled hosts and depending on enabled items are counted'));
	$table->addRow([$title, $status['triggers_count'],
		[
			$status['triggers_count_enabled'], ' / ',
			$status['triggers_count_disabled'], ' [',
			(new CSpan($status['triggers_count_on']))->addClass(ZBX_STYLE_RED), ' / ',
			(new CSpan($status['triggers_count_off']))->addClass(ZBX_STYLE_GREEN), ']'
		]
	]);
	$table->addRow([_('Number of users (online)'), $status['users_count'], (new CSpan($status['users_online']))->addClass(ZBX_STYLE_GREEN)]);
	$table->addRow([_('Required server performance, new values per second'), $status['qps_total'], '']);

	// check requirements
	if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN) {
		foreach ((new CFrontendSetup())->checkRequirements() as $req) {
			if ($req['result'] != CFrontendSetup::CHECK_OK) {
				$class = ($req['result'] == CFrontendSetup::CHECK_WARNING) ? ZBX_STYLE_ORANGE : ZBX_STYLE_RED;
				$table->addRow(
					(new CRow([$req['name'], $req['current'], $req['error']]))->addClass($class)
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
		'limit' => isset($filter['limit']) ? $filter['limit'] : DEFAULT_LATEST_ISSUES_CNT,
		'preservekeys' => true,
		'expandComment' => true
	]));

	$triggers = CMacrosResolverHelper::resolveTriggerUrls($triggers);

	// don't use withLastEventUnacknowledged and skipDependent because of performance issues
	$triggers_total_count = API::Trigger()->get(array_merge($options, [
		'countOutput' => true
	]));

	$problem_events = getTriggerLastProblem(array_keys($triggers));

	$events = [];
	foreach ($problem_events as $problem_event) {
		if (!array_key_exists('lastEvent', $triggers[$problem_event['objectid']])) {
			$triggers[$problem_event['objectid']]['lastEvent'] = $problem_event;
			$events[$problem_event['eventid']] = ['eventid' => $problem_event['eventid']];
		}
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
		if ($trigger['lastEvent']) {
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
			if ($trigger['lastEvent']) {
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
		if ($trigger['lastEvent'] || $trigger['comments'] !== '' || $trigger['url'] !== '') {
			$description = (new CSpan($description))
				->setHint(make_popup_eventlist($trigger, $backurl), '', true, 'max-width: 500px')
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
		$action_hint = ($trigger['lastEvent'] && isset($actions[$trigger['lastEvent']['eventid']]))
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
		$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, array(
			'clock' => $trigger['event']['clock'],
			'ns' => $trigger['event']['ns']
		)));

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
