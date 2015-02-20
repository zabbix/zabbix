<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	$graphs = $simpeGraphs = array();

	$favourites = CFavorite::get('web.favorite.graphids');

	if ($favourites) {
		$graphIds = $itemIds = $dbGraphs = $dbItems = array();

		foreach ($favourites as $favourite) {
			if ($favourite['source'] === 'itemid') {
				$itemIds[$favourite['value']] = $favourite['value'];
			}
			else {
				$graphIds[$favourite['value']] = $favourite['value'];
			}
		}

		if ($graphIds) {
			$dbGraphs = API::Graph()->get(array(
				'output' => array('graphid', 'name'),
				'selectHosts' => array('hostid', 'name'),
				'expandName' => true,
				'graphids' => $graphIds,
				'preservekeys' => true
			));
		}

		if ($itemIds) {
			$dbItems = API::Item()->get(array(
				'output' => array('itemid', 'hostid', 'name', 'key_'),
				'selectHosts' => array('hostid', 'name'),
				'itemids' => $itemIds,
				'webitems' => true,
				'preservekeys' => true
			));

			$dbItems = CMacrosResolverHelper::resolveItemNames($dbItems);
		}

		foreach ($favourites as $favourite) {
			$sourceId = $favourite['value'];

			if ($favourite['source'] === 'itemid') {
				if (isset($dbItems[$sourceId])) {
					$dbItem = $dbItems[$sourceId];
					$dbHost = reset($dbItem['hosts']);

					$simpeGraphs[] = array(
						'id' => $sourceId,
						'label' => $dbHost['name'].NAME_DELIMITER.$dbItem['name_expanded']
					);
				}
			}
			else {
				if (isset($dbGraphs[$sourceId])) {
					$dbGraph = $dbGraphs[$sourceId];
					$dbHost = reset($dbGraph['hosts']);

					$graphs[] = array(
						'id' => $sourceId,
						'label' => $dbHost['name'].NAME_DELIMITER.$dbGraph['name']
					);
				}
			}
		}
	}

	return array(
		'graphs' => $graphs,
		'simpleGraphs' => $simpeGraphs
	);
}

/**
 * Get favourite graphs and simple graph.
 *
 * @return CList
 */
function getFavouriteGraphs() {
	$data = getFavouriteGraphsData();

	$favourites = new CList(null, 'favorites', _('No graphs added.'));

	if ($data['graphs']) {
		foreach ($data['graphs'] as $graph) {
			$favourites->addItem(new CLink($graph['label'], 'charts.php?graphid='.$graph['id']), 'nowrap');
		}
	}

	if ($data['simpleGraphs']) {
		foreach ($data['simpleGraphs'] as $item) {
			$favourites->addItem(new CLink($item['label'], 'history.php?action='.HISTORY_GRAPH.'&itemids[]='.$item['id']), 'nowrap');
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
	$maps = array();

	$favourites = CFavorite::get('web.favorite.sysmapids');

	if ($favourites) {
		$mapIds = array();

		foreach ($favourites as $favourite) {
			$mapIds[$favourite['value']] = $favourite['value'];
		}

		$dbMaps = API::Map()->get(array(
			'output' => array('sysmapid', 'name'),
			'sysmapids' => $mapIds
		));

		foreach ($dbMaps as $dbMap) {
			$maps[] = array(
				'id' => $dbMap['sysmapid'],
				'label' => $dbMap['name']
			);
		}
	}

	return $maps;
}

/**
 * Get favourite maps.
 *
 * @return CList
 */
function getFavouriteMaps() {
	$data = getFavouriteMapsData();

	$favourites = new CList(null, 'favorites', _('No maps added.'));

	if ($data) {
		foreach ($data as $map) {
			$favourites->addItem(new CLink($map['label'], 'zabbix.php?action=map.view&sysmapid='.$map['id']), 'nowrap');
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
	$screens = $slideshows = array();

	$favourites = CFavorite::get('web.favorite.screenids');

	if ($favourites) {
		$screenIds = $slideshowIds = array();

		foreach ($favourites as $favourite) {
			if ($favourite['source'] === 'screenid') {
				$screenIds[$favourite['value']] = $favourite['value'];
			}
		}

		$dbScreens = API::Screen()->get(array(
			'output' => array('screenid', 'name'),
			'screenids' => $screenIds,
			'preservekeys' => true
		));

		foreach ($favourites as $favourite) {
			$sourceId = $favourite['value'];

			if ($favourite['source'] === 'slideshowid') {
				if (slideshow_accessible($sourceId, PERM_READ)) {
					$dbSlideshow = get_slideshow_by_slideshowid($sourceId);

					if ($dbSlideshow) {
						$slideshows[] = array(
							'id' => $dbSlideshow['slideshowid'],
							'label' => $dbSlideshow['name']
						);
					}
				}
			}
			else {
				if (isset($dbScreens[$sourceId])) {
					$dbScreen = $dbScreens[$sourceId];

					$screens[] = array(
						'id' => $dbScreen['screenid'],
						'label' => $dbScreen['name']
					);
				}
			}
		}
	}

	return array(
		'screens' => $screens,
		'slideshows' => $slideshows
	);
}

/**
 * Get favourite screens and slide shows.
 *
 * @return CList
 */
function getFavouriteScreens() {
	$data = getFavouriteScreensData();

	$favourites = new CList(null, 'favorites', _('No screens added.'));

	if ($data['screens']) {
		foreach ($data['screens'] as $screen) {
			$favourites->addItem(new CLink($screen['label'], 'screens.php?elementid='.$screen['id']), 'nowrap');
		}
	}

	if ($data['slideshows']) {
		foreach ($data['slideshows'] as $slideshow) {
			$favourites->addItem(new CLink($slideshow['label'], 'slides.php?elementid='.$slideshow['id']), 'nowrap');
		}
	}

	return $favourites;
}

function make_system_status($filter) {
	$config = select_config();

	$ackParams = array();
	if (!empty($filter['screenid'])) {
		$ackParams['screenid'] = $filter['screenid'];
	}

	$table = new CTableInfo(_('No host groups found.'));

	// set trigger severities as table header starting from highest severity
	$header = array();

	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$header[] = ($filter['severity'] === null || isset($filter['severity'][$severity]))
			? getSeverityName($severity, $config)
			: null;
	}
	krsort($header);
	array_unshift($header, _('Host group'));

	$table->setHeader($header);

	// get host groups
	$groups = API::HostGroup()->get(array(
		'groupids' => $filter['groupids'],
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored_hosts' => true,
		'output' => array('groupid', 'name'),
		'preservekeys' => true
	));

	CArrayHelper::sort($groups, array(
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	));

	$groupIds = array();
	foreach ($groups as $group) {
		$groupIds[$group['groupid']] = $group['groupid'];

		$group['tab_priority'] = array(
			TRIGGER_SEVERITY_DISASTER => array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array()),
			TRIGGER_SEVERITY_HIGH => array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array()),
			TRIGGER_SEVERITY_AVERAGE => array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array()),
			TRIGGER_SEVERITY_WARNING => array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array()),
			TRIGGER_SEVERITY_INFORMATION => array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array()),
			TRIGGER_SEVERITY_NOT_CLASSIFIED => array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array())
		);
		$groups[$group['groupid']] = $group;
	}

	// get triggers
	$triggers = API::Trigger()->get(array(
		'groupids' => $groupIds,
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored' => true,
		'maintenance' => $filter['maintenance'],
		'skipDependent' => true,
		'withLastEventUnacknowledged' => ($filter['extAck'] == EXTACK_OPTION_UNACK) ? true : null,
		'selectLastEvent' => array('eventid', 'acknowledged', 'objectid'),
		'expandDescription' => true,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'output' => array('triggerid', 'priority', 'state', 'description', 'error', 'value', 'lastchange'),
		'selectHosts' => array('name'),
		'selectGroups' => array('groupid'),
		'preservekeys' => true
	));

	$eventIds = array();

	foreach ($triggers as $triggerId => $trigger) {
		if ($trigger['lastEvent']) {
			$eventIds[$trigger['lastEvent']['eventid']] = $trigger['lastEvent']['eventid'];
		}

		$triggers[$triggerId]['event'] = $trigger['lastEvent'];
		unset($triggers[$triggerId]['lastEvent']);
	}

	// get acknowledges
	if ($eventIds) {
		$eventAcknowledges = API::Event()->get(array(
			'output' => array('eventid'),
			'eventids' => $eventIds,
			'select_acknowledges' => array('eventid', 'clock', 'message', 'alias', 'name', 'surname'),
			'preservekeys' => true
		));
	}

	// actions
	$actions = getEventActionsStatus($eventIds);

	// triggers
	foreach ($triggers as $trigger) {
		// event
		if ($trigger['event']) {
			$trigger['event']['acknowledges'] = isset($eventAcknowledges[$trigger['event']['eventid']])
				? $eventAcknowledges[$trigger['event']['eventid']]['acknowledges']
				: 0;
		}
		else {
			$trigger['event'] = array(
				'acknowledged' => false,
				'clock' => $trigger['lastchange'],
				'value' => $trigger['value']
			);
		}

		// groups
		foreach ($trigger['groups'] as $group) {
			if (!isset($groups[$group['groupid']])) {
				continue;
			}

			if (in_array($filter['extAck'], array(EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH))) {
				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count']++;

				if ($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count'] < 30) {
					$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers'][] = $trigger;
				}
			}

			if (in_array($filter['extAck'], array(EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH))
					&& isset($trigger['event']) && !$trigger['event']['acknowledged']) {
				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack']++;

				if ($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack'] < 30) {
					$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers_unack'][] = $trigger;
				}
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
				$allTriggersNum = new CSpan($allTriggersNum, 'pointer');
				$allTriggersNum->setHint(makeTriggersPopup($data['triggers'], $ackParams, $actions, $config));
			}

			$unackTriggersNum = $data['count_unack'];
			if ($unackTriggersNum) {
				$unackTriggersNum = new CSpan($unackTriggersNum, 'pointer red bold');
				$unackTriggersNum->setHint(makeTriggersPopup($data['triggers_unack'], $ackParams, $actions, $config));
			}

			switch ($filter['extAck']) {
				case EXTACK_OPTION_ALL:
					$groupRow->addItem(getSeverityCell($severity, $config, $allTriggersNum, !$allTriggersNum));
					break;

				case EXTACK_OPTION_UNACK:
					$groupRow->addItem(getSeverityCell($severity, $config, $unackTriggersNum, !$unackTriggersNum));
					break;

				case EXTACK_OPTION_BOTH:
					if ($unackTriggersNum) {
						$span = new CSpan(SPACE._('of').SPACE);
						$unackTriggersNum = new CSpan($unackTriggersNum);
					}
					else {
						$span = null;
						$unackTriggersNum = null;
					}

					$groupRow->addItem(getSeverityCell($severity,
						$config,
						array($unackTriggersNum, $span, $allTriggersNum),
						!$allTriggersNum
					));
			}
		}

		$table->addRow($groupRow);
	}

	$script = new CJsScript(get_js(
		'jQuery("#'.WIDGET_SYSTEM_STATUS.'_footer").html("'._s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)).'");'
	));

	return new CDiv(array($table, $script));
}

function make_status_of_zbx() {
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	$table = new CTableInfo();
	$table->setHeader(array(
		_('Parameter'),
		_('Value'),
		_('Details')
	));

	show_messages(); // because in function get_status(); function clear_messages() is called when fsockopen() fails.
	$status = get_status();

	$table->addRow(array(
		_('Zabbix server is running'),
		new CSpan($status['zabbix_server'], ($status['zabbix_server'] == _('Yes') ? 'off' : 'on')),
		isset($ZBX_SERVER, $ZBX_SERVER_PORT) ? $ZBX_SERVER.':'.$ZBX_SERVER_PORT : _('Zabbix server IP or port is not set!')
	));
	$title = new CSpan(_('Number of hosts (enabled/disabled/templates)'));
	$title->setAttribute('title', 'asdad');
	$table->addRow(array(_('Number of hosts (enabled/disabled/templates)'), $status['hosts_count'],
		array(
			new CSpan($status['hosts_count_monitored'], 'off'), ' / ',
			new CSpan($status['hosts_count_not_monitored'], 'on'), ' / ',
			new CSpan($status['hosts_count_template'], 'unknown')
		)
	));
	$title = new CSpan(_('Number of items (enabled/disabled/not supported)'));
	$title->setAttribute('title', _('Only items assigned to enabled hosts are counted'));
	$table->addRow(array($title, $status['items_count'],
		array(
			new CSpan($status['items_count_monitored'], 'off'), ' / ',
			new CSpan($status['items_count_disabled'], 'on'), ' / ',
			new CSpan($status['items_count_not_supported'], 'unknown')
		)
	));
	$title = new CSpan(_('Number of triggers (enabled/disabled [problem/ok])'));
	$title->setAttribute('title', _('Only triggers assigned to enabled hosts and depending on enabled items are counted'));
	$table->addRow(array($title, $status['triggers_count'],
		array(
			$status['triggers_count_enabled'], ' / ',
			$status['triggers_count_disabled'], ' [',
			new CSpan($status['triggers_count_on'], 'on'), ' / ',
			new CSpan($status['triggers_count_off'], 'off'), ']'
		)
	));
	$table->addRow(array(_('Number of users (online)'), $status['users_count'], new CSpan($status['users_online'], 'green')));
	$table->addRow(array(_('Required server performance, new values per second'), $status['qps_total'], ' - '));

	// check requirements
	if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN) {
		$frontendSetup = new CFrontendSetup();
		$reqs = $frontendSetup->checkRequirements();
		foreach ($reqs as $req) {
			if ($req['result'] != CFrontendSetup::CHECK_OK) {
				$class = ($req['result'] == CFrontendSetup::CHECK_WARNING) ? 'notice' : 'fail';
				$table->addRow(array(
					new CSpan($req['name'], $class),
					new CSpan($req['current'], $class),
					new CSpan($req['error'], $class)
				));
			}
		}
	}

	$script = new CJsScript(get_js(
		'jQuery("#'.WIDGET_ZABBIX_STATUS.'_footer").html("'._s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)).'");'
	));

	return new CDiv(array($table, $script));
}

/**
 * Create DIV with latest problem triggers.
 *
 * If no sortfield and sortorder are defined, the sort indicater in the column name will not be displayed.
 *
 * @param array  $filter['screenid']
 * @param array  $filter['groupids']
 * @param array  $filter['hostids']
 * @param array  $filter['maintenance']
 * @param int    $filter['extAck']
 * @param int    $filter['severity']
 * @param int    $filter['limit']
 * @param string $filter['sortfield']
 * @param string $filter['sortorder']
 * @param string $filter['backUrl']
 *
 * @return CDiv
 */
function make_latest_issues(array $filter = array()) {
	// hide the sort indicator if no sortfield and sortorder are given
	$showSortIndicator = isset($filter['sortfield']) || isset($filter['sortorder']);

	if (isset($filter['sortfield']) && $filter['sortfield'] !== 'lastchange') {
		$sortField = array($filter['sortfield'], 'lastchange');
		$sortOrder = array($filter['sortorder'], ZBX_SORT_DOWN);
	}
	else {
		$sortField = array('lastchange');
		$sortOrder = array(ZBX_SORT_DOWN);
	}

	$options = array(
		'groupids' => $filter['groupids'],
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored' => true,
		'maintenance' => $filter['maintenance'],
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		)
	);

	$triggers = API::Trigger()->get(array_merge($options, array(
		'withLastEventUnacknowledged' => (isset($filter['extAck']) && $filter['extAck'] == EXTACK_OPTION_UNACK)
			? true
			: null,
		'skipDependent' => true,
		'output' => array('triggerid', 'state', 'error', 'url', 'expression', 'description', 'priority', 'lastchange'),
		'selectHosts' => array('hostid', 'name'),
		'selectLastEvent' => array('eventid', 'acknowledged', 'objectid', 'clock', 'ns'),
		'sortfield' => $sortField,
		'sortorder' => $sortOrder,
		'limit' => isset($filter['limit']) ? $filter['limit'] : DEFAULT_LATEST_ISSUES_CNT,
		'preservekeys' => true
	)));

	$triggers = CMacrosResolverHelper::resolveTriggerUrl($triggers);

	// don't use withLastEventUnacknowledged and skipDependent because of performance issues
	$triggersTotalCount = API::Trigger()->get(array_merge($options, array(
		'countOutput' => true
	)));

	// get acknowledges
	$eventIds = array();
	foreach ($triggers as $trigger) {
		if ($trigger['lastEvent']) {
			$eventIds[] = $trigger['lastEvent']['eventid'];
		}
	}
	if ($eventIds) {
		$eventAcknowledges = API::Event()->get(array(
			'output' => array('eventid'),
			'eventids' => $eventIds,
			'select_acknowledges' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
	}

	foreach ($triggers as $tnum => $trigger) {
		// if trigger is lost (broken expression) we skip it
		if (empty($trigger['hosts'])) {
			unset($triggers[$tnum]);
			continue;
		}

		$host = reset($trigger['hosts']);
		$trigger['hostid'] = $host['hostid'];
		$trigger['hostname'] = $host['name'];

		if ($trigger['lastEvent']) {
			$trigger['lastEvent']['acknowledges'] = isset($eventAcknowledges[$trigger['lastEvent']['eventid']])
				? $eventAcknowledges[$trigger['lastEvent']['eventid']]['acknowledges']
				: null;
		}

		$triggers[$tnum] = $trigger;
	}

	$hostIds = zbx_objectValues($triggers, 'hostid');

	// get hosts
	$hosts = API::Host()->get(array(
		'hostids' => $hostIds,
		'output' => array('hostid', 'name', 'status', 'maintenance_status', 'maintenance_type', 'maintenanceid'),
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'preservekeys' => true
	));

	// actions
	$actions = getEventActionsStatHints($eventIds);

	// ack params
	$ackParams = isset($filter['screenid']) ? array('screenid' => $filter['screenid']) : array();

	$config = select_config();

	// indicator of sort field
	if ($showSortIndicator) {
		$sortDiv = new CDiv(SPACE, ($filter['sortorder'] === ZBX_SORT_DOWN) ? 'icon_sortdown default_cursor' : 'icon_sortup default_cursor');
		$sortDiv->addStyle('float: left');
		$hostHeaderDiv = new CDiv(array(_('Host'), SPACE));
		$hostHeaderDiv->addStyle('float: left');
		$issueHeaderDiv = new CDiv(array(_('Issue'), SPACE));
		$issueHeaderDiv->addStyle('float: left');
		$lastChangeHeaderDiv = new CDiv(array(_('Time'), SPACE));
		$lastChangeHeaderDiv->addStyle('float: left');
	}

	$table = new CTableInfo(_('No events found.'));
	$table->setHeader(array(
		($showSortIndicator && ($filter['sortfield'] === 'hostname')) ? array($hostHeaderDiv, $sortDiv) : _('Host'),
		($showSortIndicator && ($filter['sortfield'] === 'priority')) ? array($issueHeaderDiv, $sortDiv) : _('Issue'),
		($showSortIndicator && ($filter['sortfield'] === 'lastchange')) ? array($lastChangeHeaderDiv, $sortDiv) : _('Last change'),
		_('Age'),
		_('Info'),
		$config['event_ack_enable'] ? _('Ack') : null,
		_('Actions')
	));

	$scripts = API::Script()->getScriptsByHosts($hostIds);

	// triggers
	foreach ($triggers as $trigger) {
		$host = $hosts[$trigger['hostid']];

		$hostName = new CSpan($host['name'], 'link_menu');
		$hostName->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts[$host['hostid']]));

		// add maintenance icon with hint if host is in maintenance
		$maintenanceIcon = null;

		if ($host['maintenance_status']) {
			$maintenanceIcon = new CDiv(null, 'icon-maintenance-abs');

			// get maintenance
			$maintenances = API::Maintenance()->get(array(
				'maintenanceids' => $host['maintenanceid'],
				'output' => API_OUTPUT_EXTEND,
				'limit' => 1
			));
			if ($maintenance = reset($maintenances)) {
				$hint = $maintenance['name'].' ['.($host['maintenance_type']
					? _('Maintenance without data collection')
					: _('Maintenance with data collection')).']';

				if (isset($maintenance['description'])) {
					// double quotes mandatory
					$hint .= "\n".$maintenance['description'];
				}

				$maintenanceIcon->setHint($hint);
				$maintenanceIcon->addClass('pointer');
			}

			$hostName->addClass('left-to-icon-maintenance-abs');
		}

		$hostDiv = new CDiv(array($hostName, $maintenanceIcon), 'maintenance-abs-cont');

		// unknown triggers
		$unknown = SPACE;
		if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
			$unknown = new CDiv(SPACE, 'status_icon iconunknown');
			$unknown->setHint($trigger['error'], 'on');
		}

		// trigger has events
		if ($trigger['lastEvent']) {
			// description
			$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, array(
				'clock' => $trigger['lastEvent']['clock'],
				'ns' => $trigger['lastEvent']['ns']
			)));

			// ack
			$ack = getEventAckState($trigger['lastEvent'], $filter['backUrl'],
				true, $ackParams
			);
		}
		// trigger has no events
		else {
			// description
			$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, array(
				'clock' => $trigger['lastchange'],
				'ns' => '999999999'
			)));

			// ack
			$ack = new CSpan(_('No events'), 'unknown');
		}

		// description
		if (!zbx_empty($trigger['url'])) {
			$description = new CLink($description, $trigger['url'], null, null, true);
		}
		else {
			$description = new CSpan($description, 'pointer');
		}
		$description = new CCol($description, getSeverityStyle($trigger['priority']));
		if ($trigger['lastEvent']) {
			$description->setHint(
				make_popup_eventlist($trigger['triggerid'], $trigger['lastEvent']['eventid']), '', false
			);
		}

		// clock
		$clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $trigger['lastchange']),
			'events.php?filter_set=1&triggerid='.$trigger['triggerid'].'&source='.EVENT_SOURCE_TRIGGERS.
				'&show_unknown=1&hostid='.$trigger['hostid'].'&stime='.date(TIMESTAMP_FORMAT, $trigger['lastchange']).
				'&period='.ZBX_PERIOD_DEFAULT
		);

		// actions
		$actionHint = ($trigger['lastEvent'] && isset($actions[$trigger['lastEvent']['eventid']]))
			? $actions[$trigger['lastEvent']['eventid']]
			: SPACE;

		$table->addRow(array(
			$hostDiv,
			$description,
			$clock,
			zbx_date2age($trigger['lastchange']),
			$unknown,
			$ack,
			$actionHint
		));
	}

	// initialize blinking
	zbx_add_post_js('jqBlink.blink();');

	$script = new CJsScript(get_js(
		'jQuery("#'.WIDGET_LAST_ISSUES.'_footer").html("'._s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)).'");'
	));

	$infoDiv = new CDiv(_n('%1$d of %2$d issue is shown', '%1$d of %2$d issues are shown', count($triggers), $triggersTotalCount));
	$infoDiv->addStyle('text-align: right; padding-right: 3px;');

	return new CDiv(array($table, $infoDiv, $script));
}

/**
 * Generate table for dashboard triggers popup.
 *
 * @see make_system_status
 *
 * @param array $triggers
 * @param array $ackParams
 * @param array $actions
 * @param array $config
 *
 * @return CTableInfo
 */
function makeTriggersPopup(array $triggers, array $ackParams, array $actions, array $config) {
	$popupTable = new CTableInfo();
	$popupTable->setAttribute('style', 'width: 400px;');
	$popupTable->setHeader(array(
		_('Host'),
		_('Issue'),
		_('Age'),
		_('Info'),
		$config['event_ack_enable'] ? _('Ack') : null,
		_('Actions')
	));

	CArrayHelper::sort($triggers, array(array('field' => 'lastchange', 'order' => ZBX_SORT_DOWN)));

	foreach ($triggers as $trigger) {
		// unknown triggers
		$unknown = SPACE;
		if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
			$unknown = new CDiv(SPACE, 'status_icon iconunknown');
			$unknown->setHint($trigger['error'], 'on');
		}

		// ack
		if ($config['event_ack_enable']) {
			$ack = isset($trigger['event']['eventid'])
				? getEventAckState($trigger['event'], 'zabbix.php?action=dashboard.view', true, $ackParams)
				: _('No events');
		}
		else {
			$ack = null;
		}

		// action
		$action = (isset($trigger['event']['eventid']) && isset($actions[$trigger['event']['eventid']]))
			? $actions[$trigger['event']['eventid']]
			: _('-');

		$popupTable->addRow(array(
			$trigger['hosts'][0]['name'],
			getSeverityCell($trigger['priority'], $config, $trigger['description']),
			zbx_date2age($trigger['lastchange']),
			$unknown,
			$ack,
			$action
		));
	}

	return $popupTable;
}
