<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
			$favourites->addItem(new CLink($item['label'], 'history.php?action=showgraph&itemid='.$item['id']), 'nowrap');
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
			$favourites->addItem(new CLink($map['label'], 'maps.php?sysmapid='.$map['id']), 'nowrap');
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
	$ackParams = array();
	if (!empty($filter['screenid'])) {
		$ackParams['screenid'] = $filter['screenid'];
	}

	$table = new CTableInfo(_('No host groups found.'));
	$table->setHeader(array(
		_('Host group'),
		(is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_DISASTER])) ? getSeverityCaption(TRIGGER_SEVERITY_DISASTER) : null,
		(is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_HIGH])) ? getSeverityCaption(TRIGGER_SEVERITY_HIGH) : null,
		(is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE])) ? getSeverityCaption(TRIGGER_SEVERITY_AVERAGE) : null,
		(is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_WARNING])) ? getSeverityCaption(TRIGGER_SEVERITY_WARNING) : null,
		(is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION])) ? getSeverityCaption(TRIGGER_SEVERITY_INFORMATION) : null,
		(is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED])) ? getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED) : null
	));

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
		'selectLastEvent' => API_OUTPUT_EXTEND,
		'expandDescription' => true,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'output' =>  API_OUTPUT_EXTEND,
		'selectHosts' => array('name'),
		'selectGroups' => array('groupid'),
		'preservekeys' => true
	));

	// get acknowledges
	$eventIds = array();
	foreach ($triggers as $tnum => $trigger) {
		if (!empty($trigger['lastEvent'])) {
			$eventIds[$trigger['lastEvent']['eventid']] = $trigger['lastEvent']['eventid'];
		}

		$triggers[$tnum]['event'] = $trigger['lastEvent'];
		unset($triggers[$tnum]['lastEvent']);
	}
	if ($eventIds) {
		$eventAcknowledges = API::Event()->get(array(
			'output' => array('eventid'),
			'eventids' => $eventIds,
			'select_acknowledges' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
	}

	// actions
	$actions = getEventActionsStatus($eventIds);

	// triggers
	foreach ($triggers as $trigger) {
		// event
		if (empty($trigger['event'])) {
			$trigger['event'] = array(
				'acknowledged' => false,
				'clock' => $trigger['lastchange'],
				'value' => $trigger['value']
			);
		}
		else {
			$trigger['event']['acknowledges'] = isset($eventAcknowledges[$trigger['event']['eventid']])
				? $eventAcknowledges[$trigger['event']['eventid']]['acknowledges']
				: 0;
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

		$name = new CLink($group['name'],
			'tr_status.php?groupid='.$group['groupid'].'&hostid=0&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
		);
		$groupRow->addItem($name);

		foreach ($group['tab_priority'] as $severity => $data) {
			if (!is_null($filter['severity']) && !isset($filter['severity'][$severity])) {
				continue;
			}

			$allTriggersNum = $data['count'];
			if ($allTriggersNum) {
				$allTriggersNum = new CSpan($allTriggersNum, 'pointer');
				$allTriggersNum->setHint(makeTriggersPopup($data['triggers'], $ackParams, $actions));
			}

			$unackTriggersNum = $data['count_unack'];
			if ($unackTriggersNum) {
				$unackTriggersNum = new CSpan($unackTriggersNum, 'pointer red bold');
				$unackTriggersNum->setHint(makeTriggersPopup($data['triggers_unack'], $ackParams, $actions));
			}

			switch ($filter['extAck']) {
				case EXTACK_OPTION_ALL:
					$groupRow->addItem(getSeverityCell($severity, $allTriggersNum, !$allTriggersNum));
					break;

				case EXTACK_OPTION_UNACK:
					$groupRow->addItem(getSeverityCell($severity, $unackTriggersNum, !$unackTriggersNum));
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

					$groupRow->addItem(getSeverityCell($severity, array($unackTriggersNum, $span, $allTriggersNum), !$allTriggersNum));
					break;
			}
		}

		$table->addRow($groupRow);
	}

	$script = new CJSScript(get_js(
		'jQuery("#'.WIDGET_SYSTEM_STATUS.'_footer").html("'._s('Updated: %s', zbx_date2str(_('H:i:s'))).'");'
	));

	return new CDiv(array($table, $script));
}

function make_hoststat_summary($filter) {
	$table = new CTableInfo(_('No host groups found.'));
	$table->setHeader(array(
		_('Host group'),
		_('Without problems'),
		_('With problems'),
		_('Total')
	));

	// get host groups
	$groups = API::HostGroup()->get(array(
		'groupids' => $filter['groupids'],
		'monitored_hosts' => 1,
		'output' => array('groupid', 'name')
	));
	$groups = zbx_toHash($groups, 'groupid');

	CArrayHelper::sort($groups, array(
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	));

	// get hosts
	$hosts = API::Host()->get(array(
		'groupids' => zbx_objectValues($groups, 'groupid'),
		'hostids' => !empty($filter['hostids']) ? $filter['hostids'] : null,
		'monitored_hosts' => true,
		'filter' => array('maintenance_status' => $filter['maintenance']),
		'output' => array('hostid', 'name'),
		'selectGroups' => array('groupid')
	));
	$hosts = zbx_toHash($hosts, 'hostid');
	CArrayHelper::sort($hosts, array('name'));

	// get triggers
	$triggers = API::Trigger()->get(array(
		'monitored' => true,
		'maintenance' => $filter['maintenance'],
		'expandData' => true,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'output' => array('triggerid', 'priority'),
		'selectHosts' => array('hostid')
	));

	if ($filter['extAck']) {
		$triggers_unack = API::Trigger()->get(array(
			'monitored' => true,
			'maintenance' => $filter['maintenance'],
			'withLastEventUnacknowledged' => true,
			'selectHosts' => array('hostid'),
			'filter' => array(
				'priority' => $filter['severity'],
				'value' => TRIGGER_VALUE_TRUE
			),
			'output' => array('triggerid')
		));
		$triggers_unack = zbx_toHash($triggers_unack, 'triggerid');
		foreach ($triggers_unack as $tunack) {
			foreach ($tunack['hosts'] as $unack_host) {
				$hosts_with_unack_triggers[$unack_host['hostid']] = $unack_host['hostid'];
			}
		}
	}

	$hosts_data = array();
	$problematic_host_list = array();
	$lastUnack_host_list = array();
	$highest_severity = array();
	$highest_severity2 = array();

	foreach ($triggers as $trigger) {
		foreach ($trigger['hosts'] as $trigger_host) {
			if (!isset($hosts[$trigger_host['hostid']])) {
				continue;
			}
			else {
				$host = $hosts[$trigger_host['hostid']];
			}

			if ($filter['extAck'] && isset($hosts_with_unack_triggers[$host['hostid']])) {
				if (!isset($lastUnack_host_list[$host['hostid']])) {
					$lastUnack_host_list[$host['hostid']] = array();
					$lastUnack_host_list[$host['hostid']]['host'] = $host['name'];
					$lastUnack_host_list[$host['hostid']]['hostid'] = $host['hostid'];
					$lastUnack_host_list[$host['hostid']]['severities'] = array();
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_DISASTER] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_HIGH] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_AVERAGE] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_WARNING] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_INFORMATION] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;
				}
				if (isset($triggers_unack[$trigger['triggerid']])) {
					$lastUnack_host_list[$host['hostid']]['severities'][$trigger['priority']]++;
				}

				foreach ($host['groups'] as $gnum => $group) {
					if (!isset($highest_severity2[$group['groupid']])) {
						$highest_severity2[$group['groupid']] = 0;
					}

					if ($trigger['priority'] > $highest_severity2[$group['groupid']]) {
						$highest_severity2[$group['groupid']] = $trigger['priority'];
					}

					if (!isset($hosts_data[$group['groupid']])) {
						$hosts_data[$group['groupid']] = array(
							'problematic' => 0,
							'ok' => 0,
							'lastUnack' => 0,
							'hostids_all' => array(),
							'hostids_unack' => array()
						);
					}

					if (!isset($hosts_data[$group['groupid']]['hostids_unack'][$host['hostid']])) {
						$hosts_data[$group['groupid']]['hostids_unack'][$host['hostid']] = $host['hostid'];
						$hosts_data[$group['groupid']]['lastUnack']++;
					}
				}
			}

			if (!isset($problematic_host_list[$host['hostid']])) {
				$problematic_host_list[$host['hostid']] = array();
				$problematic_host_list[$host['hostid']]['host'] = $host['name'];
				$problematic_host_list[$host['hostid']]['hostid'] = $host['hostid'];
				$problematic_host_list[$host['hostid']]['severities'] = array();
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_DISASTER] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_HIGH] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_AVERAGE] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_WARNING] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_INFORMATION] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;
			}
			$problematic_host_list[$host['hostid']]['severities'][$trigger['priority']]++;

			foreach ($host['groups'] as $gnum => $group) {
				if (!isset($highest_severity[$group['groupid']])) {
					$highest_severity[$group['groupid']] = 0;
				}

				if ($trigger['priority'] > $highest_severity[$group['groupid']]) {
					$highest_severity[$group['groupid']] = $trigger['priority'];
				}

				if (!isset($hosts_data[$group['groupid']])) {
					$hosts_data[$group['groupid']] = array(
						'problematic' => 0,
						'ok' => 0,
						'lastUnack' => 0,
						'hostids_all' => array(),
						'hostids_unack' => array()
					);
				}

				if (!isset($hosts_data[$group['groupid']]['hostids_all'][$host['hostid']])) {
					$hosts_data[$group['groupid']]['hostids_all'][$host['hostid']] = $host['hostid'];
					$hosts_data[$group['groupid']]['problematic']++;
				}
			}
		}
	}

	foreach ($hosts as $host) {
		foreach ($host['groups'] as $group) {
			if (!isset($groups[$group['groupid']])) {
				continue;
			}

			if (!isset($groups[$group['groupid']]['hosts'])) {
				$groups[$group['groupid']]['hosts'] = array();
			}
			$groups[$group['groupid']]['hosts'][$host['hostid']] = array('hostid' => $host['hostid']);

			if (!isset($highest_severity[$group['groupid']])) {
				$highest_severity[$group['groupid']] = 0;
			}

			if (!isset($hosts_data[$group['groupid']])) {
				$hosts_data[$group['groupid']] = array('problematic' => 0, 'ok' => 0, 'lastUnack' => 0);
			}

			if (!isset($problematic_host_list[$host['hostid']])) {
				$hosts_data[$group['groupid']]['ok']++;
			}
		}
	}

	foreach ($groups as $group) {
		if (!isset($hosts_data[$group['groupid']])) {
			continue;
		}

		$group_row = new CRow();

		$name = new CLink($group['name'],
			'tr_status.php?groupid='.$group['groupid'].'&hostid=0&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
		);
		$group_row->addItem($name);
		$group_row->addItem(new CCol($hosts_data[$group['groupid']]['ok'], 'normal'));

		if ($filter['extAck']) {
			if ($hosts_data[$group['groupid']]['lastUnack']) {
				$table_inf = new CTableInfo();
				$table_inf->setAttribute('style', 'width: 400px;');
				$table_inf->setHeader(array(
					_('Host'),
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_DISASTER]) ? getSeverityCaption(TRIGGER_SEVERITY_DISASTER) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_HIGH]) ? getSeverityCaption(TRIGGER_SEVERITY_HIGH) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE]) ? getSeverityCaption(TRIGGER_SEVERITY_AVERAGE) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_WARNING]) ? getSeverityCaption(TRIGGER_SEVERITY_WARNING) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION]) ? getSeverityCaption(TRIGGER_SEVERITY_INFORMATION) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED]) ? getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED) : null
				));
				$popup_rows = 0;

				foreach ($group['hosts'] as $host) {
					$hostid = $host['hostid'];
					if (!isset($lastUnack_host_list[$hostid])) {
						continue;
					}

					if ($popup_rows >= ZBX_WIDGET_ROWS) {
						break;
					}
					$popup_rows++;

					$host_data = $lastUnack_host_list[$hostid];

					$r = new CRow();
					$r->addItem(new CLink($host_data['host'], 'tr_status.php?groupid='.$group['groupid'].'&hostid='.
						$hostid.'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
					));

					foreach ($lastUnack_host_list[$host['hostid']]['severities'] as $severity => $trigger_count) {
						if (!is_null($filter['severity']) && !isset($filter['severity'][$severity])) {
							continue;
						}
						$r->addItem(new CCol($trigger_count, getSeverityStyle($severity, $trigger_count)));
					}
					$table_inf->addRow($r);
				}
				$lastUnack_count = new CSpan($hosts_data[$group['groupid']]['lastUnack'], 'pointer red bold');
				$lastUnack_count->setHint($table_inf);
			}
			else {
				$lastUnack_count = 0;
			}
		}

		// if hostgroup contains problematic hosts, hint should be built
		if ($hosts_data[$group['groupid']]['problematic']) {
			$table_inf = new CTableInfo();
			$table_inf->setAttribute('style', 'width: 400px;');
			$table_inf->setHeader(array(
				_('Host'),
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_DISASTER]) ? getSeverityCaption(TRIGGER_SEVERITY_DISASTER) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_HIGH]) ? getSeverityCaption(TRIGGER_SEVERITY_HIGH) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE]) ? getSeverityCaption(TRIGGER_SEVERITY_AVERAGE) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_WARNING]) ? getSeverityCaption(TRIGGER_SEVERITY_WARNING) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION]) ? getSeverityCaption(TRIGGER_SEVERITY_INFORMATION) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED]) ? getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED) : null
			));
			$popup_rows = 0;

			foreach ($group['hosts'] as $host) {
				$hostid = $host['hostid'];
				if (!isset($problematic_host_list[$hostid])) {
					continue;
				}
				if ($popup_rows >= ZBX_WIDGET_ROWS) {
					break;
				}
				$popup_rows++;

				$host_data = $problematic_host_list[$hostid];

				$r = new CRow();
				$r->addItem(new CLink($host_data['host'], 'tr_status.php?groupid='.$group['groupid'].'&hostid='.$hostid.
					'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
				));

				foreach ($problematic_host_list[$host['hostid']]['severities'] as $severity => $trigger_count) {
					if (!is_null($filter['severity'])&&!isset($filter['severity'][$severity])) {
						continue;
					}
					$r->addItem(new CCol($trigger_count, getSeverityStyle($severity, $trigger_count)));
				}
				$table_inf->addRow($r);
			}
			$problematic_count = new CSpan($hosts_data[$group['groupid']]['problematic'], 'pointer');
			$problematic_count->setHint($table_inf);
		}
		else {
			$problematic_count = 0;
		}

		switch ($filter['extAck']) {
			case EXTACK_OPTION_ALL:
				$group_row->addItem(new CCol(
					$problematic_count,
					getSeverityStyle($highest_severity[$group['groupid']], $hosts_data[$group['groupid']]['problematic']))
				);
				$group_row->addItem($hosts_data[$group['groupid']]['problematic'] + $hosts_data[$group['groupid']]['ok']);
				break;
			case EXTACK_OPTION_UNACK:
				$group_row->addItem(new CCol(
					$lastUnack_count,
					getSeverityStyle((isset($highest_severity2[$group['groupid']]) ? $highest_severity2[$group['groupid']] : 0),
						$hosts_data[$group['groupid']]['lastUnack']))
				);
				$group_row->addItem($hosts_data[$group['groupid']]['lastUnack'] + $hosts_data[$group['groupid']]['ok']);
				break;
			case EXTACK_OPTION_BOTH:
				$unackspan = $lastUnack_count ? new CSpan(array($lastUnack_count, SPACE._('of').SPACE)) : null;
				$group_row->addItem(new CCol(array(
					$unackspan, $problematic_count),
					getSeverityStyle($highest_severity[$group['groupid']], $hosts_data[$group['groupid']]['problematic']))
				);
				$group_row->addItem($hosts_data[$group['groupid']]['problematic'] + $hosts_data[$group['groupid']]['ok']);
				break;
		}

		$table->addRow($group_row);
	}

	$script = new CJSScript(get_js(
		'jQuery("#'.WIDGET_HOST_STATUS.'_footer").html("'._s('Updated: %s', zbx_date2str(_('H:i:s'))).'");'
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
	$title = new CSpan(_('Number of hosts (monitored/not monitored/templates)'));
	$title->setAttribute('title', 'asdad');
	$table->addRow(array(_('Number of hosts (monitored/not monitored/templates)'), $status['hosts_count'],
		array(
			new CSpan($status['hosts_count_monitored'], 'off'), ' / ',
			new CSpan($status['hosts_count_not_monitored'], 'on'), ' / ',
			new CSpan($status['hosts_count_template'], 'unknown')
		)
	));
	$title = new CSpan(_('Number of items (monitored/disabled/not supported)'));
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
		$frontendSetup = new FrontendSetup();
		$reqs = $frontendSetup->checkRequirements();
		foreach ($reqs as $req) {
			if ($req['result'] != FrontendSetup::CHECK_OK) {
				$class = ($req['result'] == FrontendSetup::CHECK_WARNING) ? 'notice' : 'fail';
				$table->addRow(array(
					new CSpan($req['name'], $class),
					new CSpan($req['current'], $class),
					new CSpan($req['error'], $class)
				));
			}
		}
	}

	$script = new CJSScript(get_js(
		'jQuery("#'.WIDGET_ZABBIX_STATUS.'_footer").html("'._s('Updated: %s', zbx_date2str(_('H:i:s'))).'");'
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
		'limit' => isset($filter['limit']) ? $filter['limit'] : DEFAULT_LATEST_ISSUES_CNT
	)));

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
			$unknown->setHint($trigger['error'], '', 'on');
		}

		// trigger has events
		if ($trigger['lastEvent']) {
			// description
			$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, array(
				'clock' => $trigger['lastEvent']['clock'],
				'ns' => $trigger['lastEvent']['ns']
			)));

			// ack
			$ack = getEventAckState($trigger['lastEvent'], empty($filter['backUrl']) ? true : $filter['backUrl'],
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
			$description = new CLink($description, resolveTriggerUrl($trigger), null, null, true);
		}
		else {
			$description = new CSpan($description, 'pointer');
		}
		$description = new CCol($description, getSeverityStyle($trigger['priority']));
		if ($trigger['lastEvent']) {
			$description->setHint(
				make_popup_eventlist($trigger['triggerid'], $trigger['lastEvent']['eventid']),
				'', '', false
			);
		}

		// clock
		$clock = new CLink(zbx_date2str(_('d M Y H:i:s'), $trigger['lastchange']),
			'events.php?triggerid='.$trigger['triggerid'].'&source='.EVENT_SOURCE_TRIGGERS.'&show_unknown=1'.
				'&hostid='.$trigger['hostid'].'&stime='.date(TIMESTAMP_FORMAT, $trigger['lastchange']).
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

	$script = new CJSScript(get_js(
		'jQuery("#'.WIDGET_LAST_ISSUES.'_footer").html("'._s('Updated: %s', zbx_date2str(_('H:i:s'))).'");'
	));

	$infoDiv = new CDiv(_n('%1$d of %2$d issue is shown', '%1$d of %2$d issues are shown', count($triggers), $triggersTotalCount));
	$infoDiv->addStyle('text-align: right; padding-right: 3px;');

	return new CDiv(array($table, $infoDiv, $script));
}

/**
 * Create and return a DIV with web monitoring overview.
 *
 * @param array $filter
 * @param array $filter['groupids']
 * @param bool  $filter['maintenance']
 *
 * @return CDiv
 */
function make_webmon_overview($filter) {
	$groups = API::HostGroup()->get(array(
		'groupids' => $filter['groupids'],
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored_hosts' => true,
		'with_monitored_httptests' => true,
		'output' => array('groupid', 'name'),
		'preservekeys' => true
	));

	CArrayHelper::sort($groups, array(
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	));

	$groupIds = array_keys($groups);

	$availableHosts = API::Host()->get(array(
		'groupids' => $groupIds,
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored_hosts' => true,
		'filter' => array('maintenance_status' => $filter['maintenance']),
		'output' => array('hostid'),
		'preservekeys' => true
	));
	$availableHostIds = array_keys($availableHosts);

	$table = new CTableInfo(_('No web scenarios found.'));
	$table->setHeader(array(
		_('Host group'),
		_('Ok'),
		_('Failed'),
		_('Unknown')
	));

	$data = array();

	// fetch links between HTTP tests and host groups
	$result = DbFetchArray(DBselect(
		'SELECT DISTINCT ht.httptestid,hg.groupid'.
		' FROM httptest ht,hosts_groups hg'.
		' WHERE ht.hostid=hg.hostid'.
			' AND '.dbConditionInt('hg.hostid', $availableHostIds).
			' AND '.dbConditionInt('hg.groupid', $groupIds)
	));

	// fetch HTTP test execution data
	$httpTestData = Manager::HttpTest()->getLastData(zbx_objectValues($result, 'httptestid'));

	foreach ($result as $row) {
		if (isset($httpTestData[$row['httptestid']]) && $httpTestData[$row['httptestid']]['lastfailedstep'] !== null) {
			if ($httpTestData[$row['httptestid']]['lastfailedstep'] != 0) {
				$data[$row['groupid']]['failed'] = isset($data[$row['groupid']]['failed'])
					? ++$data[$row['groupid']]['failed']
					: 1;
			}
			else {
				$data[$row['groupid']]['ok'] = isset($data[$row['groupid']]['ok'])
					? ++$data[$row['groupid']]['ok']
					: 1;
			}
		}
		else {
			$data[$row['groupid']]['unknown'] = isset($data[$row['groupid']]['unknown'])
				? ++$data[$row['groupid']]['unknown']
				: 1;
		}
	}

	foreach ($groups as $group) {
		if (!empty($data[$group['groupid']])) {
			$table->addRow(array(
				$group['name'],
				new CSpan(empty($data[$group['groupid']]['ok']) ? 0 : $data[$group['groupid']]['ok'], 'off'),
				new CSpan(
					empty($data[$group['groupid']]['failed']) ? 0 : $data[$group['groupid']]['failed'],
					empty($data[$group['groupid']]['failed']) ? 'off' : 'on'
				),
				new CSpan(empty($data[$group['groupid']]['unknown']) ? 0 : $data[$group['groupid']]['unknown'], 'unknown')
			));
		}
	}

	$script = new CJSScript(get_js(
		'jQuery("#'.WIDGET_WEB_OVERVIEW.'_footer").html("'._s('Updated: %s', zbx_date2str(_('H:i:s'))).'");'
	));

	return new CDiv(array($table, $script));
}

function make_discovery_status() {
	$options = array(
		'filter' => array('status' => DHOST_STATUS_ACTIVE),
		'selectDHosts' => array('druleid', 'dhostid', 'status'),
		'output' => API_OUTPUT_EXTEND
	);
	$drules = API::DRule()->get($options);

	CArrayHelper::sort($drules, array(
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	));

	foreach ($drules as $drnum => $drule) {
		$drules[$drnum]['up'] = 0;
		$drules[$drnum]['down'] = 0;

		foreach ($drule['dhosts'] as $dhost){
			if (DRULE_STATUS_DISABLED == $dhost['status']) {
				$drules[$drnum]['down']++;
			}
			else {
				$drules[$drnum]['up']++;
			}
		}
	}

	$header = array(
		new CCol(_('Discovery rule'), 'center'),
		new CCol(_x('Up', 'discovery results in dashboard')),
		new CCol(_x('Down', 'discovery results in dashboard'))
	);

	$table = new CTableInfo();
	$table->setHeader($header,'header');

	foreach ($drules as $drule) {
		$table->addRow(array(
			new CLink($drule['name'], 'discovery.php?druleid='.$drule['druleid']),
			new CSpan($drule['up'], 'green'),
			new CSpan($drule['down'], ($drule['down'] > 0) ? 'red' : 'green')
		));
	}

	$script = new CJSScript(get_js(
		'jQuery("#'.WIDGET_DISCOVERY_STATUS.'_footer").html("'._s('Updated: %s', zbx_date2str(_('H:i:s'))).'");'
	));

	return new CDiv(array($table, $script));
}

/**
 * Generate table for dashboard triggers popup.
 *
 * @see make_system_status
 *
 * @param array $triggers
 * @param array $ackParams
 * @param array $actions
 *
 * @return CTableInfo
 */
function makeTriggersPopup(array $triggers, array $ackParams, array $actions) {
	$config = select_config();

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
			$unknown->setHint($trigger['error'], '', 'on');
		}

		// ack
		if ($config['event_ack_enable']) {
			$ack = isset($trigger['event']['eventid'])
				? getEventAckState($trigger['event'], true, true, $ackParams)
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
			getSeverityCell($trigger['priority'], $trigger['description']),
			zbx_date2age($trigger['lastchange']),
			$unknown,
			$ack,
			$action
		));
	}

	return $popupTable;
}
